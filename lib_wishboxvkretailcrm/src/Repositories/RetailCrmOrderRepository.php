<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace WishboxVkRetailCrmLibrary\Repositories;

use RetailCrm\Api\Client;
use RetailCrm\Api\Exception\Api\AccountDoesNotExistException;
use RetailCrm\Api\Exception\Api\ApiErrorException;
use RetailCrm\Api\Exception\Api\MissingCredentialsException;
use RetailCrm\Api\Exception\Api\MissingParameterException;
use RetailCrm\Api\Exception\Api\ValidationException;
use RetailCrm\Api\Exception\Client\HandlerException;
use RetailCrm\Api\Exception\Client\HttpClientException;
use RetailCrm\Api\Interfaces\ApiExceptionInterface;
use RetailCrm\Api\Interfaces\ClientExceptionInterface;
use RetailCrm\Api\Model\Entity\Orders\Order;
use RetailCrm\Api\Model\Filter\Orders\OrderFilter;
use RetailCrm\Api\Model\Request\Orders\OrdersCreateRequest;
use RetailCrm\Api\Model\Request\Orders\OrdersRequest;
use WishboxVkRetailCrmLibrary\Exception\VkOrderImportException;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Reads and creates RetailCRM orders through the official PHP SDK.
 *
 * @since 1.0.0
 */
final readonly class RetailCrmOrderRepository
{
	/**
	 * @since 1.0.0
	 */
	public function __construct(private Client $retailCrmClient)
	{
	}

	/**
	 * Check whether an order with the supplied external ID already exists.
	 *
	 * @since 1.0.0
	 */
	public function orderExists(string $externalId): bool
	{
		$request = new OrdersRequest();
		$request->limit = 20;
		$request->page = 1;
		$request->filter = new OrderFilter();
		$request->filter->externalIds = [$externalId];
		try
		{
			$response = $this->retailCrmClient->orders->list($request);
		}
		catch (AccountDoesNotExistException $e)
		{
			$message = $e->getMessage();
			$message = $e->getMessage();
		}
		catch (ApiErrorException $e)
		{
			$message = $e->getMessage();
			$message = $e->getMessage();
		}
		catch (MissingCredentialsException $e)
		{
			$message = $e->getMessage();
			$message = $e->getMessage();
		}
		catch (MissingParameterException $e)
		{
			$message = $e->getMessage();
			$message = $e->getMessage();
		}
		catch (ValidationException $e)
		{
			$message = $e->getMessage();
			$message = $e->getMessage();
		}
		catch (HandlerException $e)
		{
			$message = $e->getMessage();
			$message = $e->getMessage();
		}
		catch (HttpClientException $e)
		{
			$message = $e->getMessage();
			$message = $e->getMessage();
		}
		catch (ApiExceptionInterface $e)
		{
			$message = $e->getMessage();
			$message = $e->getMessage();
		}
		catch (ClientExceptionInterface $e)
		{
			$message = $e->getMessage();
			$message = $e->getMessage();
		}

		return is_array($response->orders ?? null) && $response->orders !== [];
	}

	/**
	 * Create a RetailCRM order and return its internal ID.
	 *
	 * @param   Order   $order
	 * @param   string  $site
	 *
	 * @return int
	 * @throws AccountDoesNotExistException
	 * @throws ApiErrorException
	 * @throws MissingCredentialsException
	 * @throws MissingParameterException
	 * @throws ValidationException
	 * @throws HandlerException
	 * @throws HttpClientException
	 * @throws ApiExceptionInterface
	 * @throws ClientExceptionInterface
	 *
	 * @since 1.0.0
	 */
	public function createOrder(Order $order, string $site): int
	{
		$request = new OrdersCreateRequest();
		$request->order = $order;
		$request->site = $site;
		$response = $this->retailCrmClient->orders->create($request);
		$orderId = $response->id ?? 0;

		if ($orderId < 1)
		{
			throw new VkOrderImportException('RetailCRM did not return the created order ID.');
		}

		return $orderId;
	}
}
