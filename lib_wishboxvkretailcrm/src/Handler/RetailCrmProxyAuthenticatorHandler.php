<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace WishboxVkRetailCrmLibrary\Handler;

use RetailCrm\Api\Handler\AbstractHandler;
use RetailCrm\Api\Model\RequestData;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Adds RetailCRM and proxy credentials to SDK requests.
 *
 * @since 1.0.0
 */
final class RetailCrmProxyAuthenticatorHandler extends AbstractHandler
{
	/**
	 * @since 1.0.0
	 */
	public function __construct(
		private readonly string $apiKey,
		private readonly string $proxyToken = ''
	) {
	}

	/**
	 * @inheritDoc
	 *
	 * @since 1.0.0
	 */
	public function handle($item)
	{
		if ($item instanceof RequestData && $item->request !== null)
		{
			$item->request = $item->request->withAddedHeader('X-Api-Key', $this->apiKey);

			if ($this->proxyToken !== '')
			{
				$item->request = $item->request->withAddedHeader(
					'X-RetailCRM-Proxy-Token',
					$this->proxyToken
				);
			}
		}

		return parent::handle($item);
	}
}
