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
 * Summarizes one RetailCRM-to-VK product synchronization run.
 *
 * @since 1.0.0
 */
final class ProductSyncResult
{
	public int $updated = 0;

	public int $skipped = 0;

	public int $failed = 0;

	/**
	 * @var list<string>
	 *
	 * @since 1.0.0
	 */
	public array $errors = [];
}
