<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\Database\DatabaseDriver;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

return new class implements ServiceProviderInterface
{
	/**
	 * Register the plugin installer script.
	 *
	 * @param   Container  $container  Dependency injection container.
	 *
	 * @return void
	 */
	public function register(Container $container): void
	{
		$container->set(
			InstallerScriptInterface::class,
			new class ($container->get(DatabaseDriver::class)) implements InstallerScriptInterface
			{
				/**
				 * Constructor.
				 *
				 * @param   DatabaseDriver  $db  Joomla database driver.
				 */
				public function __construct(private DatabaseDriver $db)
				{
				}

				/**
				 * Enable the plugin after installation.
				 *
				 * @param   InstallerAdapter  $adapter  Installer adapter.
				 *
				 * @return boolean
				 */
				public function install(InstallerAdapter $adapter): bool
				{
					$this->enablePlugin($adapter);

					return true;
				}

				/**
				 * Handle plugin update.
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
				 * Handle plugin removal.
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
				 * Prepare an installer operation.
				 *
				 * @param   string            $type     Operation type.
				 * @param   InstallerAdapter  $adapter  Installer adapter.
				 *
				 * @return boolean
				 */
				public function preflight(string $type, InstallerAdapter $adapter): bool
				{
					return true;
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
				 * Enable the installed plugin record.
				 *
				 * @param   InstallerAdapter  $adapter  Installer adapter.
				 *
				 * @return void
				 */
				private function enablePlugin(InstallerAdapter $adapter): void
				{
					$plugin          = new stdClass();
					$plugin->type    = 'plugin';
					$plugin->element = $adapter->getElement();
					$plugin->folder  = (string) $adapter->getParent()->manifest->attributes()['group'];
					$plugin->enabled = 1;

					$this->db->updateObject('#__extensions', $plugin, ['type', 'element', 'folder']);
				}
			}
		);
	}
};
