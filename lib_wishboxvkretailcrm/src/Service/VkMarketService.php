<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace WishboxVkRetailCrmLibrary\Service;

use Throwable;
use WishboxVkLibrary\Dto\VkMarketItemUpdate;
use WishboxVkLibrary\Service\VkMarketService as VkApiMarketService;
use WishboxVkRetailCrmLibrary\Dto\ProductSyncResult;
use WishboxVkRetailCrmLibrary\Repositories\RetailCrmOfferRepository;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Synchronises RetailCRM offer prices and availability with VK Market.
 *
 * @since 1.0.0
 */
final class VkMarketService
{
	private const int VK_BATCH_SIZE = 25;

	/**
	 * @since 1.0.0
	 */
	public function __construct(
		private readonly RetailCrmOfferRepository $retailCrmOfferRepository,
		private readonly VkApiMarketService $vkApiMarketService
	) {
	}

	/**
	 * Update only price and availability for mapped RetailCRM offers.
	 *
	 * @since 1.0.0
	 */
	public function updateProducts(int $maxOffers = 1000): ProductSyncResult
	{
		$result = new ProductSyncResult();
		$updates = [];

		foreach ($this->retailCrmOfferRepository->getOffers($maxOffers) as $offer)
		{
			$vkItemId = $this->retailCrmOfferRepository->getVkItemId($offer);
			$price = $this->retailCrmOfferRepository->getPrice($offer);

			if ($vkItemId < 1 || $price === null)
			{
				$result->skipped++;

				continue;
			}

			$updates[] = new VkMarketItemUpdate(
				itemId: $vkItemId,
				price: $price,
				deleted: !$this->retailCrmOfferRepository->isAvailable($offer)
			);
		}

		foreach (array_chunk($updates, self::VK_BATCH_SIZE) as $batch)
		{
			try
			{
				$this->vkApiMarketService->editProducts($batch);
				$result->updated += count($batch);
			}
			catch (Throwable $throwable)
			{
				$result->failed += count($batch);
				$result->errors[] = $throwable->getMessage();
			}
		}

		return $result;
	}
}
