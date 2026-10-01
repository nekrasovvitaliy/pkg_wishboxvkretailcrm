<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace WishboxVkRetailCrmLibrary\Service;

use Joomla\Registry\Registry;
use RetailCrm\Api\Exception\Api\AccountDoesNotExistException;
use RetailCrm\Api\Exception\Api\ApiErrorException;
use RetailCrm\Api\Exception\Api\MissingCredentialsException;
use RetailCrm\Api\Exception\Api\MissingParameterException;
use RetailCrm\Api\Exception\Api\ValidationException;
use RetailCrm\Api\Exception\Client\HandlerException;
use RetailCrm\Api\Exception\Client\HttpClientException;
use RetailCrm\Api\Interfaces\ApiExceptionInterface;
use RetailCrm\Api\Interfaces\ClientExceptionInterface;
use RetailCrm\Api\Model\Entity\Orders\Delivery\OrderDeliveryAddress;
use RetailCrm\Api\Model\Entity\Orders\Delivery\SerializedOrderDelivery;
use RetailCrm\Api\Model\Entity\Orders\Items\Offer;
use RetailCrm\Api\Model\Entity\Orders\Items\OrderProduct;
use RetailCrm\Api\Model\Entity\Orders\MarketplaceData;
use RetailCrm\Api\Model\Entity\Orders\Order;
use RetailCrm\Api\Model\Entity\Store\StoreOffer;
use stdClass;
use Throwable;
use WishboxVkLibrary\Service\VkOrderService;
use WishboxVkRetailCrmLibrary\Dto\VkOrderImportResult;
use WishboxVkRetailCrmLibrary\Exception\VkOrderImportException;
use WishboxVkRetailCrmLibrary\Repositories\RetailCrmOfferRepository;
use WishboxVkRetailCrmLibrary\Repositories\RetailCrmOrderRepository;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Imports new VK Market orders into RetailCRM.
 *
 * @since 1.0.0
 */
final readonly class VkOrderImportService
{
	/**
	 * @since 1.0.0
	 */
	public function __construct(
		private VkOrderService           $vkOrderService,
		private RetailCrmOfferRepository $retailCrmOfferRepository,
		private RetailCrmOrderRepository $retailCrmOrderRepository,
		private Registry                 $params
	) {
	}

	/**
	 * Import one page of VK group orders that do not yet exist in RetailCRM.
	 *
	 * @since 1.0.0
	 */
	public function importNewOrders(int $limit = 10, int $offset = 0): VkOrderImportResult
	{
		$limit = min(100, max(1, $limit));
		$response = $this->vkOrderService->getGroupOrders($limit, max(0, $offset));
		$result = new VkOrderImportResult();

		foreach ($response->items ?? [] as $vkOrder)
		{
			if (!$vkOrder instanceof stdClass)
			{
				$result->failed++;
				$result->errors[0] = 'VK returned an invalid order item.';

				continue;
			}

			$vkOrderId = (int) ($vkOrder->id ?? 0);

			if ($vkOrderId < 1)
			{
				$result->failed++;
				$result->errors[0] = 'VK returned an order without an ID.';

				continue;
			}

			try
			{
				$externalId = $this->getExternalOrderId($vkOrder);

				if ($this->retailCrmOrderRepository->orderExists($externalId))
				{
					$result->skipped++;

					continue;
				}

				$vkOrderItems = $this->vkOrderService->getOrderItems(
					(int) ($vkOrder->user_id ?? 0),
					$vkOrderId
				);
				$retailCrmOrder = $this->buildRetailCrmOrder($vkOrder, $vkOrderItems, $externalId);
				$retailCrmOrderId = $this->retailCrmOrderRepository->createOrder(
					$retailCrmOrder,
					$this->getRetailCrmSite()
				);

				$result->created++;
				$result->orderIds[$vkOrderId] = $retailCrmOrderId;
			}
			catch (Throwable $throwable)
			{
				$result->failed++;
				$result->errors[$vkOrderId] = $throwable->getMessage();
			}
		}

		return $result;
	}

	/**
	 * Convert a VK order to the RetailCRM SDK order entity.
	 *
	 * @param   stdClass        $vkOrder
	 * @param   list<stdClass>  $vkOrderItems  VK order items.
	 * @param   string          $externalId
	 *
	 * @return Order
	 * @throws AccountDoesNotExistException
	 * @throws ApiErrorException
	 * @throws MissingCredentialsException
	 * @throws MissingParameterException
	 * @throws ValidationException
	 * @throws HandlerException
	 * @throws HttpClientException
	 * @throws ApiExceptionInterface
	 * @throws ClientExceptionInterface
	 * @since 1.0.0
	 */
	private function buildRetailCrmOrder(
		stdClass $vkOrder,
		array $vkOrderItems,
		string $externalId
	): Order {
		$vkItemIds = array_map(
			fn (stdClass $vkOrderItem): int => $this->getVkMarketItemId($vkOrderItem),
			$vkOrderItems
		);
		$offers = $this->retailCrmOfferRepository->getOffersByVkItemIds(
			$vkItemIds,
			(int) $this->params->get('retailcrm_offer_lookup_limit', 10000)
		);
		$orderItems = [];

		foreach ($vkOrderItems as $vkOrderItem)
		{
			$vkItemId = $this->getVkMarketItemId($vkOrderItem);

			if ($vkItemId < 1 || !isset($offers[$vkItemId]))
			{
				throw new VkOrderImportException(
					'RetailCRM offer was not found for VK Market item ' . $vkItemId . '.'
				);
			}

			$orderItems[] = $this->buildRetailCrmOrderItem($vkOrderItem, $offers[$vkItemId]);
		}

		if ($orderItems === [])
		{
			throw new VkOrderImportException('VK order does not contain any products.');
		}

		$recipient = $vkOrder->recipient ?? new stdClass();
		$nameParts = preg_split('/\s+/u', trim((string) ($recipient->name ?? '')), 3, PREG_SPLIT_NO_EMPTY) ?: [];
		$order = new Order();
		$order->externalId = $externalId;
		$order->site = $this->getRetailCrmSite();
		$order->currency = (string) $this->params->get('order_currency', 'RUB');
		$order->orderType = (string) $this->params->get('retailcrm_order_type', 'eshop-individual');
		$order->orderMethod = (string) $this->params->get('retailcrm_order_method', 'shopping-cart');
		$order->status = (string) $this->params->get('retailcrm_order_status', 'new');
		$order->firstName = (string) ($recipient->first_name ?? $nameParts[0] ?? 'VK');
		$order->lastName = (string) ($recipient->last_name ?? $nameParts[1] ?? 'Customer');
		$order->patronymic = (string) ($recipient->second_name ?? $nameParts[2] ?? '');
		$order->phone = (string) ($recipient->phone ?? '');
		$order->email = (string) ($recipient->email ?? '');
		$order->customerComment = (string) ($vkOrder->comment ?? '');
		$order->managerComment = 'Imported from VK Market order #' . $externalId
			. '; VK user #' . (int) ($vkOrder->user_id ?? 0)
			. '; VK status ' . (int) ($vkOrder->status ?? 0) . '.';
		$order->items = $orderItems;
		$order->delivery = $this->buildDelivery($vkOrder);
		$marketplaceCode = trim((string) $this->params->get('retailcrm_marketplace_code'));

		if ($marketplaceCode !== '')
		{
			$order->marketplace = new MarketplaceData();
			$order->marketplace->code = $marketplaceCode;
			$order->marketplace->orderId = $externalId;
		}

		$vkOrderField = trim((string) $this->params->get('retailcrm_vk_order_field'));

		if ($vkOrderField !== '')
		{
			$order->customFields = [$vkOrderField => (string) ($vkOrder->id ?? '')];
		}

		return $order;
	}

	private function buildRetailCrmOrderItem(stdClass $vkOrderItem, StoreOffer $retailCrmOffer): OrderProduct
	{
		$offer = new Offer();
		$offer->id = $retailCrmOffer->id;
		$offer->externalId = $retailCrmOffer->externalId ?? '';
		$item = new OrderProduct();
		$item->offer = $offer;
		$item->quantity = max(1.0, (float) ($vkOrderItem->quantity ?? 1));
		$item->initialPrice = $this->getVkItemPrice($vkOrderItem)
			?? $this->retailCrmOfferRepository->getPrice($retailCrmOffer)
			?? 0.0;
		$item->productName = (string) (
			$vkOrderItem->item->title
			?? $retailCrmOffer->name
			?? ('VK Market item #' . $this->getVkMarketItemId($vkOrderItem))
		);

		return $item;
	}

	private function buildDelivery(stdClass $vkOrder): SerializedOrderDelivery
	{
		$vkDelivery = $vkOrder->delivery ?? new stdClass();
		$vkAddress = $vkDelivery->address ?? new stdClass();
		$address = new OrderDeliveryAddress();
		$address->city = (string) ($vkAddress->city ?? '');
		$address->street = (string) ($vkAddress->street ?? '');
		$address->building = (string) ($vkAddress->house ?? $vkAddress->building ?? '');
		$address->flat = (string) ($vkAddress->apartment ?? $vkAddress->flat ?? '');
		$address->text = (string) ($vkAddress->address ?? '');
		$address->notes = (string) ($vkDelivery->comment ?? '');
		$delivery = new SerializedOrderDelivery();
		$delivery->address = $address;
		$delivery->cost = $this->getMoneyAmount($vkDelivery->price ?? 0) ?? 0.0;

		return $delivery;
	}

	private function getVkMarketItemId(stdClass $vkOrderItem): int
	{
		return (int) ($vkOrderItem->item_id ?? $vkOrderItem->item->id ?? $vkOrderItem->id ?? 0);
	}

	private function getVkItemPrice(stdClass $vkOrderItem): ?float
	{
		return $this->getMoneyAmount($vkOrderItem->price ?? $vkOrderItem->item->price ?? null);
	}

	private function getMoneyAmount(mixed $price): ?float
	{
		if (is_object($price) && isset($price->amount) && is_numeric($price->amount))
		{
			return (float) $price->amount / 100;
		}

		return is_numeric($price) ? (float) $price : null;
	}

	private function getExternalOrderId(stdClass $vkOrder): string
	{
		$externalId = trim((string) ($vkOrder->display_order_id ?? ''));

		return $externalId !== '' ? $externalId : 'vk-' . (int) $vkOrder->id;
	}

	/**
	 * @throws VkOrderImportException When the RetailCRM site code is missing.
	 *
	 * @since 1.0.0
	 */
	private function getRetailCrmSite(): string
	{
		$site = trim((string) $this->params->get('retailcrm_site'));

		if ($site === '')
		{
			throw new VkOrderImportException('RetailCRM site code must be configured.');
		}

		return $site;
	}
}
