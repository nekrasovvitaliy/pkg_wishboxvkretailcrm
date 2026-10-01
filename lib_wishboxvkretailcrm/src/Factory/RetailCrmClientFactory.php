<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace WishboxVkRetailCrmLibrary\Factory;

use RetailCrm\Api\Builder\ClientBuilder;
use RetailCrm\Api\Builder\FormEncoderBuilder;
use RetailCrm\Api\Client;
use WishboxVkRetailCrmLibrary\Handler\RetailCrmProxyAuthenticatorHandler;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Creates a RetailCRM SDK client with optional proxy authentication.
 *
 * @since 1.0.0
 */
final class RetailCrmClientFactory
{
	/**
	 * @since 1.0.0
	 */
	public static function createClient(string $apiUrl, string $apiKey, string $proxyToken = ''): Client
	{
		return (new ClientBuilder())
			->setApiUrl($apiUrl)
			->setAuthenticatorHandler(new RetailCrmProxyAuthenticatorHandler($apiKey, $proxyToken))
			->setFormEncoder((new FormEncoderBuilder())->build())
			->build();
	}
}
