<?php
/**
 * Joomla! Framework Website
 *
 * @copyright  Copyright (C) 2014 - 2017 Open Source Matters, Inc. All rights reserved.
 * @license    http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License Version 2 or Later
 */

namespace Joomla\FrameworkWebsite\Model;

use Joomla\Database\DatabaseDriver;
use Joomla\Database\Mysql\MysqlQuery;
use Joomla\Model\{
	DatabaseModelInterface, DatabaseModelTrait
};

/**
 * Model class for releases
 */
class ReleaseModel implements DatabaseModelInterface
{
	use DatabaseModelTrait;

	/**
	 * Instantiate the model.
	 *
	 * @param   DatabaseDriver  $db  The database adapter.
	 */
	public function __construct(DatabaseDriver $db)
	{
		$this->setDb($db);
	}

	/**
	 * Add a release for a package
	 *
	 * @param   \stdClass  $package  The package to add the release for
	 * @param   string     $version  The package's release version
	 *
	 * @return  void
	 */
	public function addRelease(\stdClass $package, string $version)
	{
		$db = $this->getDb();

		$data = (object) [
			'package_id' => $package->id,
			'version'    => $version,
		];

		$db->insertObject('#__releases', $data);
	}

	/**
	 * Fetches the latest releases for each Framework package
	 *
	 * @param   \stdClass[]  $packages  The packages to fetch the releases for
	 *
	 * @return  \stdClass[]
	 */
	public function getLatestReleases(array $packages) : array
	{
		$db = $this->getDb();

		/** @var MysqlQuery $subQuery */
		$subQuery = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__releases'))
			->order('package_id ASC, version DESC');

		/** @var MysqlQuery $query */
		$query = $db->getQuery(true)
			->select('*')
			->from('(' . (string) $subQuery . ') AS sub');

		$releases = $db->setQuery($query)->loadObjectList('id');

		$reports = [];

		// Loop through the releases and build the reports
		foreach ($releases as $release)
		{
			// Skip if package is already included
			if (isset($reports[$release->package_id]))
			{
				continue;
			}

			if ($release->total_lines > 0)
			{
				$release->lines_percentage = round($release->lines_covered / $release->total_lines * 100, 2);
			}
			else
			{
				$release->lines_percentage = 0;
			}

			$release->package = $packages[$release->package_id];
			unset($release->package_id);

			$reports[$release->package->id] = $release;
		}

		return $reports;
	}

	/**
	 * Get the release history for a package
	 *
	 * @param   \stdClass  $package  The package to retrieve the history for
	 *
	 * @return  \stdClass[]
	 *
	 * @throws  \RuntimeException
	 */
	public function getPackageHistory(\stdClass $package) : array
	{
		// Get the package data for the package specified via the route
		$db = $this->getDb();

		/** @var MysqlQuery $query */
		$query = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__releases'))
			->where($db->quoteName('package_id') . ' = :packageId')
			->order('version ASC');

		$query->bind('packageId', $package->id, \PDO::PARAM_INT);

		$releases = $db->setQuery($query)->loadObjectList();

		// Bail if we don't have any data for the given package
		if (!count($releases))
		{
			throw new \RuntimeException(sprintf('Unable to find release data for the `%s` package', $package->display), 404);
		}

		// Loop through the packs and get the reports
		$i = 0;

		$reports = [];

		foreach ($releases as $release)
		{
			$release->lines_percentage = 0;

			if ($release->total_lines > 0)
			{
				$release->lines_percentage = $release->lines_covered / $release->total_lines * 100;
			}

			// Compute the delta to the previous build
			if ($i !== 0)
			{
				$previous = $reports[$i - 1];

				$release->newTests      = 0;
				$release->newAssertions = 0;
				$release->addedCoverage = 0;

				if (isset($release->tests) && isset($previous->tests))
				{
					$release->newTests = $release->tests - $previous->tests;
				}

				if (isset($release->assertions) && isset($previous->assertions))
				{
					$release->newAssertions = $release->assertions - $previous->assertions;
				}

				if (isset($release->lines_percentage) && isset($previous->lines_percentage))
				{
					$release->addedCoverage = $release->lines_percentage - $previous->lines_percentage;
				}
			}

			$reports[$i] = $release;
			$i++;
		}

		return $reports;
	}

	/**
	 * Check if the package has a release at the given version
	 *
	 * @param   \stdClass  $package  The package to check for the release on
	 * @param   string     $version  The version to check for
	 *
	 * @return  boolean
	 */
	public function hasRelease(\stdClass $package, string $version) : bool
	{
		$db = $this->getDb();

		/** @var MysqlQuery $query */
		$query = $db->getQuery(true)
			->select($db->quoteName('id'))
			->from($db->quoteName('#__releases'))
			->where($db->quoteName('package_id') . ' = :packageId')
			->where($db->quoteName('version') . ' = :version')
			->bind('packageId', $package->id, \PDO::PARAM_INT)
			->bind('version', $version, \PDO::PARAM_STR);

		$id = $db->setQuery($query)->loadResult();

		return $id !== null;
	}
}
