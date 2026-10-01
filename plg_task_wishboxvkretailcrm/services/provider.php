<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license         GNU General Public License version 2 or later;
 */

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\Task\WishboxVkRetailCrm\Extension\WishboxVkRetailCrm;
use Joomla\Registry\Registry;
use RetailCrm\Api\Client;
use WishboxVkLibrary\Api\VkApiClient;
use WishboxVkLibrary\Service\VkMarketService as VkApiMarketService;
use WishboxVkLibrary\Service\VkOrderService;
use WishboxVkRetailCrmLibrary\Factory\RetailCrmClientFactory;
use WishboxVkRetailCrmLibrary\Repositories\RetailCrmOfferRepository;
use WishboxVkRetailCrmLibrary\Repositories\RetailCrmOrderRepository;
use WishboxVkRetailCrmLibrary\Service\VkMarketService;
use WishboxVkRetailCrmLibrary\Service\VkOrderImportService;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die;

// phpcs:enable PSR1.Files.SideEffects

return new class implements ServiceProviderInterface {
	private const string PARAMS_SERVICE = 'wishboxvkretailcrm.params';

	/**
	 * Register the scheduled RetailCRM and VK synchronization services.
	 *
	 * @since 1.0.0
	 */
	public function register(Container $container): void
	{
		require_once JPATH_SITE . '/vendor/autoload.php';

		if (!class_exists(VkApiClient::class))
		{
			throw new RuntimeException('The WishBox VK library must be installed before enabling this plugin.');
		}

		$container->set(
			self::PARAMS_SERVICE,
			static function (): Registry {
				$config = (array) PluginHelper::getPlugin('task', 'wishboxvkretailcrm');

				return new Registry($config['params'] ?? '');
			}
		);

		$container->set(
			VkApiClient::class,
			static fn(Container $container): VkApiClient => new VkApiClient(
				accessToken: (string) $container->get(self::PARAMS_SERVICE)->get('access_token'),
				apiUrl: (string) $container->get(self::PARAMS_SERVICE)->get(
					'vk_api_url',
					'https://api.vk.com/method/'
				),
				proxyToken: (string) $container->get(self::PARAMS_SERVICE)->get('vk_proxy_token')
			)
		);

		$container->set(
			VkApiMarketService::class,
			static fn(Container $container): VkApiMarketService => new VkApiMarketService(
				$container->get(VkApiClient::class),
				(int) $container->get(self::PARAMS_SERVICE)->get('group_id')
			)
		);

		$container->set(
			VkOrderService::class,
			static fn(Container $container): VkOrderService => new VkOrderService(
				$container->get(VkApiClient::class),
				(int) $container->get(self::PARAMS_SERVICE)->get('group_id')
			)
		);

		$container->set(
			Client::class,
			static function (Container $container): Client {
				$params = $container->get(self::PARAMS_SERVICE);
				$apiUrl = trim((string) $params->get('retailcrm_api_url'));
				$apiKey = trim((string) $params->get('retailcrm_api_key'));
				$proxyToken = (string) $params->get('retailcrm_proxy_token');

				if ($apiUrl === '' || $apiKey === '')
				{
					throw new RuntimeException('RetailCRM API URL and API key must be configured.');
				}

				return RetailCrmClientFactory::createClient($apiUrl, $apiKey, $proxyToken);
			}
		);

		$container->set(
			RetailCrmOfferRepository::class,
			static fn(Container $container): RetailCrmOfferRepository => new RetailCrmOfferRepository(
				$container->get(Client::class),
				$container->get(self::PARAMS_SERVICE)
			)
		);

		$container->set(
			RetailCrmOrderRepository::class,
			static fn(Container $container): RetailCrmOrderRepository => new RetailCrmOrderRepository(
				$container->get(Client::class)
			)
		);

		$container->set(
			VkMarketService::class,
			static fn(Container $container): VkMarketService => new VkMarketService(
				$container->get(RetailCrmOfferRepository::class),
				$container->get(VkApiMarketService::class)
			)
		);

		$container->set(
			VkOrderImportService::class,
			static fn(Container $container): VkOrderImportService => new VkOrderImportService(
				$container->get(VkOrderService::class),
				$container->get(RetailCrmOfferRepository::class),
				$container->get(RetailCrmOrderRepository::class),
				$container->get(self::PARAMS_SERVICE)
			)
		);

		$container->set(
			PluginInterface::class,
			static function (Container $container): PluginInterface {
				$dispatcher           = $container->get(DispatcherInterface::class);
				$config               = (array) PluginHelper::getPlugin('task', 'wishboxvkretailcrm');
				$vkMarketService      = $container->get(VkMarketService::class);
				$vkOrderImportService = $container->get(VkOrderImportService::class);
				$plugin               = new WishboxVkRetailCrm($dispatcher, $config);

				$plugin->setApplication(Factory::getApplication());
				$plugin->setVkMarketService($vkMarketService);
				$plugin->setVkOrderImportService($vkOrderImportService);

				return $plugin;
			}
		);
	}
};
