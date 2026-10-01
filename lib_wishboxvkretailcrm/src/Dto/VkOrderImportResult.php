<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace WishboxVkRetailCrmLibrary\Dto;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Summarizes one VK-to-RetailCRM order import run.
 *
 * @since 1.0.0
 */
final class VkOrderImportResult
{
	/**
	 * Number of RetailCRM orders created.
	 *
	 * @since 1.0.0
	 */
	public int $created = 0;

	/**
	 * Number of previously imported VK orders skipped.
	 *
	 * @since 1.0.0
	 */
	public int $skipped = 0;

	/**
	 * Number of VK orders that could not be imported.
	 *
	 * @since 1.0.0
	 */
	public int $failed = 0;

	/**
	 * @var array<int, int> RetailCRM order IDs indexed by VK order ID.
	 *
	 * @since 1.0.0
	 */
	public array $orderIds = [];

	/**
	 * @var array<int, string> Error messages indexed by VK order ID.
	 *
	 * @since 1.0.0
	 */
	public array $errors = [];
}
