<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace Tests\Stubs\WishboxVkLibrary\Service;

use stdClass;

/**
 * Supplies deterministic VK orders without making network requests.
 */
final class VkOrderService
{
	public int $itemsRequestCount = 0;

	/**
	 * @param   array<int, list<stdClass>>  $itemsByOrderId  Items indexed by VK order ID.
	 */
	public function __construct(
		private readonly stdClass $ordersResponse,
		private readonly array $itemsByOrderId = []
	) {
	}

	public function getGroupOrders(int $limit = 10, int $offset = 0): stdClass
	{
		return $this->ordersResponse;
	}

	/** @return list<stdClass> */
	public function getOrderItems(int $userId, int $orderId, int $limit = 1000, int $offset = 0): array
	{
		$this->itemsRequestCount++;

		return $this->itemsByOrderId[$orderId] ?? [];
	}
}
