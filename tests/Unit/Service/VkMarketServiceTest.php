<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace Tests\Unit\Service;

use Joomla\Registry\Registry;
use PHPUnit\Framework\TestCase;
use RetailCrm\Api\Client;
use RetailCrm\Api\Model\Entity\Pagination;
use RetailCrm\Api\Model\Entity\Store\OfferPrice;
use RetailCrm\Api\Model\Entity\Store\Product;
use RetailCrm\Api\Model\Entity\Store\StoreOffer;
use RetailCrm\Api\Model\Request\Store\OffersRequest;
use RetailCrm\Api\Model\Response\Store\OffersResponse;
use RetailCrm\Api\ResourceGroup\Store;
use WishboxVkLibrary\Service\VkMarketService as VkApiMarketService;
use WishboxVkRetailCrmLibrary\Repositories\RetailCrmOfferRepository;
use WishboxVkRetailCrmLibrary\Service\VkMarketService;

final class VkMarketServiceTest extends TestCase
{
	public function testUpdatesOnlyMappedPriceAndAvailability(): void
	{
		$availableOffer = $this->createOffer(101, 1490.0, 3.0);
		$unavailableOffer = $this->createOffer(102, 990.0, 0.0);
		$unmappedOffer = $this->createOffer(0, 500.0, 2.0);
		$response = new OffersResponse();
		$response->offers = [$availableOffer, $unavailableOffer, $unmappedOffer];
		$response->pagination = new Pagination();
		$response->pagination->totalPageCount = 1;
		$store = $this->createMock(Store::class);
		$store->expects(self::once())
			->method('offers')
			->with(self::callback($this->isOffersRequestForSite('shop')))
			->willReturn($response);

		$client = $this->createStub(Client::class);
		$client->store = $store;
		$params = new Registry([
			'retailcrm_site'       => 'shop',
			'vk_item_property'     => 'vk_market_item_id',
			'retailcrm_price_type' => 'base',
		]);
		$repository = new RetailCrmOfferRepository($client, $params);
		$vkApiMarketService = new VkApiMarketService();
		$service = new VkMarketService($repository, $vkApiMarketService);

		$result = $service->updateProducts();

		self::assertSame(2, $result->updated);
		self::assertSame(1, $result->skipped);
		self::assertSame(0, $result->failed);
		self::assertCount(1, $vkApiMarketService->batches);
		self::assertSame(101, $vkApiMarketService->batches[0][0]->itemId);
		self::assertSame(1490.0, $vkApiMarketService->batches[0][0]->price);
		self::assertFalse($vkApiMarketService->batches[0][0]->deleted);
		self::assertSame(0.0, $vkApiMarketService->batches[0][0]->oldPrice);
		self::assertSame(102, $vkApiMarketService->batches[0][1]->itemId);
		self::assertTrue($vkApiMarketService->batches[0][1]->deleted);
	}

	public function testRecordsFailedVkBatch(): void
	{
		$response = new OffersResponse();
		$response->offers = [$this->createOffer(101, 1490.0, 3.0)];
		$response->pagination = new Pagination();
		$response->pagination->totalPageCount = 1;
		$store = $this->createStub(Store::class);
		$store->method('offers')->willReturn($response);
		$client = $this->createStub(Client::class);
		$client->store = $store;
		$repository = new RetailCrmOfferRepository($client, new Registry());
		$vkApiMarketService = new VkApiMarketService();
		$vkApiMarketService->fail = true;
		$service = new VkMarketService($repository, $vkApiMarketService);

		$result = $service->updateProducts();

		self::assertSame(0, $result->updated);
		self::assertSame(1, $result->failed);
		self::assertSame(['VK request failed.'], $result->errors);
	}

	private function createOffer(int $vkItemId, float $priceValue, float $quantity): StoreOffer
	{
		$price = new OfferPrice();
		$price->priceType = 'base';
		$price->price = $priceValue;
		$product = new Product();
		$product->active = true;
		$offer = new StoreOffer();
		$offer->properties = $vkItemId > 0 ? ['vk_market_item_id' => $vkItemId] : [];
		$offer->prices = [$price];
		$offer->quantity = $quantity;
		$offer->active = true;
		$offer->product = $product;

		return $offer;
	}

	private function isOffersRequestForSite(string $site): callable
	{
		return static fn (OffersRequest $request): bool => $request->limit === 100
			&& $request->page === 1
			&& $request->filter->sites === [$site];
	}
}
