<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace WishboxVkLibrary\Dto;

/**
 * Test substitute for the DTO supplied by the WishBox VK library.
 */
final readonly class VkMarketItemUpdate
{
	public function __construct(
		public int $itemId,
		public float $price,
		public float $oldPrice = 0.0,
		public bool $deleted = false
	) {
	}
}
