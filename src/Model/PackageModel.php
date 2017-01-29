<?php
/**
 * Joomla! Framework Website
 *
 * @copyright  Copyright (C) 2014 - 2017 Open Source Matters, Inc. All rights reserved.
 * @license    http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License Version 2 or Later
 */

namespace Joomla\FrameworkWebsite\Model;

use Joomla\FrameworkWebsite\PackageAware;
use Joomla\Model\{
	DatabaseModelInterface, DatabaseModelTrait
};

/**
 * Model class for the package view
 *
 * @since  1.0
 */
class PackageModel implements DatabaseModelInterface
{
	use DatabaseModelTrait, PackageAware;

	/**
	 * Instantiate the model.
	 *
	 * @param   DatabaseDriver  $db  The database adapter.
	 *
	 * @since   1.0
	 */
	public function __construct(DatabaseDriver $db)
	{
		$this->setDb($db);
	}

	/**
	 * Fetches the requested data
	 *
	 * @param   string  $package  The package to request data for
	 *
	 * @return  array
	 *
	 * @since   1.0
	 * @throws  \RuntimeException
	 */
	public function getPackage(string $package) : array
	{
		// Get the package data for the package specified via the route
		$db = $this->getDb();

		$query = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__packages'))
			->where($db->quoteName('package') . ' = ' . $db->quote($package));

		$packs = $db->setQuery($query)->loadObjectList();

		// Bail if we don't have any data for the given package
		if (!count($packs))
		{
			throw new \RuntimeException(sprintf('Unable to find package data for the specified package `%s`', $package), 404);
		}

		// Loop through the packs and get the reports
		$i = 0;

		foreach ($packs as $pack)
		{
			$query->clear()
				->select('*')
				->from($db->quoteName('#__test_results'))
				->where($db->quoteName('package_id') . ' = ' . (int) $pack->id);

			$result = $db->setQuery($query)->loadObject();

			// If we didn't get any data, build a new object
			if (!$result)
			{
				$result = new \stdClass;
			}
			// Otherwise compute report percentages
			else
			{
				if ($result->total_lines > 0)
				{
					$result->lines_percentage = round($result->lines_covered / $result->total_lines * 100, 2);
				}
				else
				{
					$result->lines_percentage = 0;
				}
			}

			$result->version = $pack->version;

			$result->repoName = $this->getPackages()->get('packages.' . $pack->package . '.repo', $pack->package);

			// Compute the delta to the previous build
			if ($i !== 0)
			{
				$previous = $reports[$i - 1];

				$result->newTests      = 0;
				$result->newAssertions = 0;
				$result->addedCoverage = 0;

				if (isset($result->tests) && isset($previous->tests))
				{
					$result->newTests = $result->tests - $previous->tests;
				}

				if (isset($result->assertions) && isset($previous->assertions))
				{
					$result->newAssertions = $result->assertions - $previous->assertions;
				}

				if (isset($result->lines_percentage) && isset($previous->lines_percentage))
				{
					$result->addedCoverage = $result->lines_percentage - $previous->lines_percentage;
				}
			}

			$reports[$i] = $result;
			$i++;
		}

		return $reports;
	}
}
