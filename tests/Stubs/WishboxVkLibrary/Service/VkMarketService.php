<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace WishboxVkLibrary\Service;

use RuntimeException;

/**
 * Records VK product batches without making network requests.
 */
final class VkMarketService
{
	/** @var list<list<object>> */
	public array $batches = [];

	public bool $fail = false;

	/**
	 * @param   list<object>  $items  Product updates.
	 *
	 * @return list<bool>
	 */
	public function editProducts(array $items): array
	{
		if ($this->fail)
		{
			throw new RuntimeException('VK request failed.');
		}

		$this->batches[] = $items;

		return array_fill(0, count($items), true);
	}
}
