<?php
/**
 * Joomla! Framework Website
 *
 * @copyright  Copyright (C) 2014 - 2017 Open Source Matters, Inc. All rights reserved.
 * @license    http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License Version 2 or Later
 */

namespace Joomla\FrameworkWebsite\Command\Router;

use Joomla\Controller\AbstractController;
use Joomla\DI\{
	ContainerAwareInterface, ContainerAwareTrait
};
use Joomla\FrameworkWebsite\CommandInterface;
use Joomla\Router\Router;

/**
 * Router cache command
 *
 * @method         \Joomla\FrameworkWebsite\CliApplication  getApplication()  Get the application object.
 * @property-read  \Joomla\FrameworkWebsite\CliApplication  $app              Application object
 */
class CacheCommand extends AbstractController implements CommandInterface, ContainerAwareInterface
{
	use ContainerAwareTrait;

	/**
	 * Execute the controller.
	 *
	 * @return  boolean
	 */
	public function execute()
	{
		$this->getApplication()->outputTitle('Cache Router');

		// Check if caching is enabled
		if ($this->getApplication()->get('router.cache', false) === false)
		{
			$this->getApplication()->out('<info>Router caching is disabled.</info>');

			return true;
		}

		$this->getApplication()->out('<info>Resetting Router Cache</info>');

		$compiledFile = JPATH_ROOT . '/cache/CompiledRouter.php';

		// First remove the compiled router file
		if (file_exists($compiledFile) && !@unlink($compiledFile))
		{
			$this->getApplication()->out('<error>Error removing compiled router file</error>');

			return false;
		}

		// Clear the stat cache just to make sure we're clear
		clearstatcache();

		// Force reload the router service
		/** @var Router $router */
		$router = $this->getContainer()->getNewInstance('application.router');

		// Now compile it
		file_put_contents($compiledFile, $this->compileRouter($router));

		$this->getApplication()->out('<info>The router has been cached.</info>');

		return true;
	}

	/**
	 * Get the command's description
	 *
	 * @return  string
	 */
	public function getDescription() : string
	{
		return 'Resets the router cache.';
	}

	/**
	 * Get the command's title
	 *
	 * @return  string
	 */
	public function getTitle() : string
	{
		return 'Reset Router Cache';
	}

	/**
	 * Compile the router into a PHP file
	 *
	 * @param   Router  $router  THe router to be compiled
	 *
	 * @return  string
	 */
	private function compileRouter(Router $router) : string
	{
		// Make the routes data available for the compiled router
		$refl = new \ReflectionClass($router);

		$routesProperty = $refl->getProperty('routes');
		$routesProperty->setAccessible(true);

		$routeData = $routesProperty->getValue($router);

		$router = <<<PHP
<?php

/**
 * Compiled application router, this file is auto-generated by the `router:cache` command.
 */
class CompiledRouter extends Joomla\Router\Router
{
	protected \$routes = [

PHP;

		foreach ($routeData as $method => $routes)
		{
			$router .= "\t\t'$method' => [\n";

			foreach ($routes as $route)
			{
				$router .= "\t\t\t" . $this->formatValue($route) . ",\n";
			}

			$router .= "\t\t],\n";
		}

		$router .= <<<PHP
	];
}

PHP;

		return $router;
	}


	/**
	 * Format a value for the string conversion
	 *
	 * @param   mixed  $value  The value to format
	 *
	 * @return  mixed  The formatted value
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	private function formatValue($value)
	{
		switch (gettype($value))
		{
			case 'string':
				return "'" . addcslashes($value, '\\\'') . "'";

			case 'array':
			case 'object':
				return $this->getArrayString((array) $value);

			case 'double':
			case 'integer':
				return $value;

			case 'boolean':
				return $value ? 'true' : 'false';
		}
	}

	/**
	 * Method to get an array as an exported string.
	 *
	 * @param   array  $a  The array to get as a string.
	 *
	 * @return  string
	 */
	private function getArrayString(array $a) : string
	{
		$s = '[';
		$i = 0;

		foreach ($a as $k => $v)
		{
			$s .= ($i) ? ', ' : '';
			$s .= "'" . addcslashes($k, '\\\'') . "' => ";
			$s .= $this->formatValue($v);

			$i++;
		}

		$s .= ']';

		return $s;
	}
}
