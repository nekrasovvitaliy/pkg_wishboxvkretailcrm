<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace Tests\Unit\Service;

use Joomla\Registry\Registry;
use PHPUnit\Framework\TestCase;
use RetailCrm\Api\Client;
use RetailCrm\Api\Model\Entity\Orders\Order;
use RetailCrm\Api\Model\Entity\Pagination;
use RetailCrm\Api\Model\Entity\Store\OfferPrice;
use RetailCrm\Api\Model\Entity\Store\Product;
use RetailCrm\Api\Model\Entity\Store\StoreOffer;
use RetailCrm\Api\Model\Request\Orders\OrdersCreateRequest;
use RetailCrm\Api\Model\Response\Orders\OrdersCreateResponse;
use RetailCrm\Api\Model\Response\Orders\OrdersResponse;
use RetailCrm\Api\Model\Response\Store\OffersResponse;
use RetailCrm\Api\ResourceGroup\Orders;
use RetailCrm\Api\ResourceGroup\Store;
use stdClass;
use WishboxVkLibrary\Service\VkOrderService;
use WishboxVkRetailCrmLibrary\Repositories\RetailCrmOfferRepository;
use WishboxVkRetailCrmLibrary\Repositories\RetailCrmOrderRepository;
use WishboxVkRetailCrmLibrary\Service\VkOrderImportService;

final class VkOrderImportServiceTest extends TestCase
{
	public function testImportsVkOrderIntoRetailCrm(): void
	{
		$vkOrder = $this->createVkOrder();
		$vkOrderItem = $this->createVkOrderItem();
		$vkOrderService = new VkOrderService((object) ['items' => [$vkOrder]], [77 => [$vkOrderItem]]);
		$offerResponse = $this->createOfferResponse();
		$store = $this->createStub(Store::class);
		$store->method('offers')->willReturn($offerResponse);
		$ordersResponse = new OrdersResponse();
		$ordersResponse->orders = [];
		$createdRequest = null;
		$createResponse = new OrdersCreateResponse();
		$createResponse->id = 501;
		$orders = $this->createMock(Orders::class);
		$orders->expects(self::once())->method('list')->willReturn($ordersResponse);
		$orders->expects(self::once())
			->method('create')
			->willReturnCallback(
				static function (OrdersCreateRequest $request) use (&$createdRequest, $createResponse): OrdersCreateResponse {
					$createdRequest = $request;

					return $createResponse;
				}
			);

		$service = $this->createService($vkOrderService, $store, $orders);
		$result = $service->importNewOrders();

		self::assertSame(1, $result->created);
		self::assertSame(0, $result->skipped);
		self::assertSame([77 => 501], $result->orderIds);
		self::assertInstanceOf(OrdersCreateRequest::class, $createdRequest);
		self::assertSame('shop', $createdRequest->site);
		self::assertSame('VK-77', $createdRequest->order->externalId);
		self::assertSame('shop', $createdRequest->order->site);
		self::assertSame(2.0, $createdRequest->order->items[0]->quantity);
		self::assertSame(1490.0, $createdRequest->order->items[0]->initialPrice);
		self::assertSame(9001, $createdRequest->order->items[0]->offer->id);
		self::assertSame(5.0, $createdRequest->order->delivery->cost);
	}

	public function testSkipsPreviouslyImportedOrder(): void
	{
		$vkOrderService = new VkOrderService((object) ['items' => [$this->createVkOrder()]]);
		$store = $this->createMock(Store::class);
		$store->expects(self::never())->method('offers');
		$ordersResponse = new OrdersResponse();
		$ordersResponse->orders = [new Order()];
		$orders = $this->createMock(Orders::class);
		$orders->expects(self::once())->method('list')->willReturn($ordersResponse);
		$orders->expects(self::never())->method('create');

		$service = $this->createService($vkOrderService, $store, $orders);
		$result = $service->importNewOrders();

		self::assertSame(0, $result->created);
		self::assertSame(1, $result->skipped);
		self::assertSame(0, $result->failed);
		self::assertSame(0, $vkOrderService->itemsRequestCount);
	}

	private function createService(VkOrderService $vkOrderService, Store $store, Orders $orders): VkOrderImportService
	{
		$client = $this->createStub(Client::class);
		$client->store = $store;
		$client->orders = $orders;
		$params = new Registry([
			'retailcrm_site'               => 'shop',
			'vk_item_property'             => 'vk_market_item_id',
			'retailcrm_price_type'         => 'base',
			'retailcrm_offer_lookup_limit' => 100,
			'order_currency'               => 'RUB',
			'retailcrm_order_type'         => 'eshop-individual',
			'retailcrm_order_method'       => 'shopping-cart',
			'retailcrm_order_status'       => 'new',
		]);

		return new VkOrderImportService(
			$vkOrderService,
			new RetailCrmOfferRepository($client, $params),
			new RetailCrmOrderRepository($client),
			$params
		);
	}

	private function createOfferResponse(): OffersResponse
	{
		$price = new OfferPrice();
		$price->priceType = 'base';
		$price->price = 1490.0;
		$product = new Product();
		$product->active = true;
		$offer = new StoreOffer();
		$offer->id = 9001;
		$offer->externalId = 'SKU-1';
		$offer->name = 'Test product';
		$offer->properties = ['vk_market_item_id' => 321];
		$offer->prices = [$price];
		$offer->quantity = 10;
		$offer->active = true;
		$offer->product = $product;
		$response = new OffersResponse();
		$response->offers = [$offer];
		$response->pagination = new Pagination();
		$response->pagination->totalPageCount = 1;

		return $response;
	}

	private function createVkOrder(): stdClass
	{
		return (object) [
			'id'               => 77,
			'display_order_id' => 'VK-77',
			'user_id'          => 1001,
			'status'           => 0,
			'comment'          => 'Call before delivery',
			'recipient'        => (object) [
				'first_name' => 'Ivan',
				'last_name'  => 'Ivanov',
				'phone'      => '+79990000000',
			],
			'delivery'         => (object) [
				'price'   => (object) ['amount' => 500],
				'address' => (object) [
					'city'   => 'Moscow',
					'street' => 'Tverskaya',
					'house'  => '1',
				],
			],
		];
	}

	private function createVkOrderItem(): stdClass
	{
		return (object) [
			'item_id'  => 321,
			'quantity' => 2,
			'price'    => (object) ['amount' => 149000],
			'item'     => (object) ['title' => 'Test product'],
		];
	}
}
