<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace Joomla\Plugin\Task\WishboxVkRetailCrm\Extension;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Event\SubscriberInterface;
use Throwable;
use WishboxVkRetailCrmLibrary\Service\VkMarketServiceAwareInterface;
use WishboxVkRetailCrmLibrary\Service\VkMarketServiceAwareTrait;
use WishboxVkRetailCrmLibrary\Service\VkOrderImportServiceAwareInterface;
use WishboxVkRetailCrmLibrary\Service\VkOrderImportServiceAwareTrait;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Provides scheduled synchronization between RetailCRM and VK Market.
 *
 * @since 1.0.0
 */
final class WishboxVkRetailCrm extends CMSPlugin implements
	SubscriberInterface,
	VkMarketServiceAwareInterface,
	VkOrderImportServiceAwareInterface
{
	use TaskPluginTrait;
	use VkMarketServiceAwareTrait;
	use VkOrderImportServiceAwareTrait;

	/**
	 * @var array<string, array{langConstPrefix: string, method: string, form?: string}>
	 *
	 * @since 1.0.0
	 */
	protected const array TASKS_MAP = [
		'plg_task_wishboxvkretailcrm_update_products' => [
			'langConstPrefix' => 'PLG_TASK_WISHBOXVKRETAILCRM_UPDATE_PRODUCTS',
			'form'            => 'update_products',
			'method'          => 'updateProducts',
		],
		'plg_task_wishboxvkretailcrm_import_new_orders' => [
			'langConstPrefix' => 'PLG_TASK_WISHBOXVKRETAILCRM_IMPORT_NEW_ORDERS',
			'form'            => 'import_new_orders',
			'method'          => 'importNewOrders',
		],
	];

	/**
	 * @var boolean
	 *
	 * @since 1.0.0
	 */
	protected $autoloadLanguage = true;

	/**
	 * @return array<string, string>
	 *
	 * @since 1.0.0
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onTaskOptionsList'    => 'advertiseRoutines',
			'onExecuteTask'        => 'standardRoutineHandler',
			'onContentPrepareForm' => 'enhanceTaskItemForm',
		];
	}

	/**
	 * Update VK Market prices and availability from RetailCRM offers.
	 *
	 * @throws \Exception
	 * @since        1.0.0
	 *
	 * @noinspection PhpUnusedPrivateMethodInspection
	 */
	private function updateProducts(ExecuteTaskEvent $event): int
	{
		try
		{
			$params = $event->getArgument('params');
			$limit = (int) ($params->limit ?? $this->params->get('product_limit', 1000));
			$result = $this->getVkMarketService()
				->updateProducts($limit);

			$this->logTask(
				Text::sprintf(
					'PLG_TASK_WISHBOXVKRETAILCRM_UPDATE_PRODUCTS_RESULT',
					$result->updated,
					$result->skipped,
					$result->failed
				),
				$result->failed > 0 ? 'warning' : 'info'
			);

			foreach ($result->errors as $message)
			{
				$this->logTask($message, 'error');
			}

			return $result->failed > 0 ? Status::KNOCKOUT : Status::OK;
		}
		catch (Throwable $throwable)
		{
			$this->logTask((string) $throwable, 'error');

			return Status::KNOCKOUT;
		}
	}

	/**
	 * Import new VK Market orders into RetailCRM.
	 *
	 * @throws \Exception
	 * @since        1.0.0
	 *
	 * @noinspection PhpUnusedPrivateMethodInspection
	 */
	private function importNewOrders(ExecuteTaskEvent $event): int
	{
		try
		{
			$params = $event->getArgument('params');
			$limit = (int) ($params->limit ?? $this->params->get('order_import_limit', 10));
			$result = $this->getVkOrderImportService()
				->importNewOrders($limit);

			$this->logTask(
				Text::sprintf(
					'PLG_TASK_WISHBOXVKRETAILCRM_IMPORT_NEW_ORDERS_RESULT',
					$result->created,
					$result->skipped,
					$result->failed
				),
				$result->failed > 0 ? 'warning' : 'info'
			);

			foreach ($result->errors as $vkOrderId => $message)
			{
				$this->logTask('VK order #' . $vkOrderId . ': ' . $message, 'error');
			}

			return $result->failed > 0 ? Status::KNOCKOUT : Status::OK;
		}
		catch (Throwable $throwable)
		{
			$this->logTask((string) $throwable, 'error');

			return Status::KNOCKOUT;
		}
	}
}
