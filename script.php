<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Version;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

return new class implements ServiceProviderInterface
{
	/**
	 * Register the package installer script.
	 *
	 * @param   Container  $container  Dependency injection container.
	 *
	 * @return void
	 */
	public function register(Container $container): void
	{
		$container->set(
			InstallerScriptInterface::class,
			new class ($container->get(AdministratorApplication::class)) implements InstallerScriptInterface
			{
				/**
				 * Minimum supported Joomla version.
				 */
				private const MINIMUM_JOOMLA = '4.4.3';

				/**
				 * Minimum supported PHP version.
				 */
				private const MINIMUM_PHP = '8.5';

				/**
				 * Constructor.
				 *
				 * @param   AdministratorApplication  $app  Administrator application.
				 */
				public function __construct(private AdministratorApplication $app)
				{
				}

				/**
				 * Handle package installation.
				 *
				 * @param   InstallerAdapter  $adapter  Installer adapter.
				 *
				 * @return boolean
				 */
				public function install(InstallerAdapter $adapter): bool
				{
					return true;
				}

				/**
				 * Handle package update.
				 *
				 * @param   InstallerAdapter  $adapter  Installer adapter.
				 *
				 * @return boolean
				 */
				public function update(InstallerAdapter $adapter): bool
				{
					return true;
				}

				/**
				 * Handle package removal.
				 *
				 * @param   InstallerAdapter  $adapter  Installer adapter.
				 *
				 * @return boolean
				 */
				public function uninstall(InstallerAdapter $adapter): bool
				{
					return true;
				}

				/**
				 * Validate the runtime before installation or update.
				 *
				 * @param   string            $type     Operation type.
				 * @param   InstallerAdapter  $adapter  Installer adapter.
				 *
				 * @return boolean
				 */
				public function preflight(string $type, InstallerAdapter $adapter): bool
				{
					if ($type === 'uninstall')
					{
						return true;
					}

					return $this->checkCompatibility();
				}

				/**
				 * Complete an installer operation.
				 *
				 * @param   string            $type     Operation type.
				 * @param   InstallerAdapter  $adapter  Installer adapter.
				 *
				 * @return boolean
				 */
				public function postflight(string $type, InstallerAdapter $adapter): bool
				{
					return true;
				}

				/**
				 * Check Joomla and PHP runtime versions.
				 *
				 * @return boolean
				 */
				private function checkCompatibility(): bool
				{
					if (!(new Version())->isCompatible(self::MINIMUM_JOOMLA))
					{
						$this->app->enqueueMessage(
							Text::sprintf(
								'PKG_WISHBOXVKRETAILCRM_ERROR_COMPATIBLE_JOOMLA',
								self::MINIMUM_JOOMLA
							),
							'error'
						);

						return false;
					}

					if (version_compare(PHP_VERSION, self::MINIMUM_PHP, '<'))
					{
						$this->app->enqueueMessage(
							Text::sprintf(
								'PKG_WISHBOXVKRETAILCRM_ERROR_COMPATIBLE_PHP',
								self::MINIMUM_PHP
							),
							'error'
						);

						return false;
					}

					return true;
				}
			}
		);
	}
};
