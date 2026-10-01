<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use RetailCrm\Api\Enum\ByIdentifier;
use RetailCrm\Api\Model\Entity\Orders\Items\Offer;
use RetailCrm\Api\Model\Entity\Orders\Items\OrderProduct;
use RetailCrm\Api\Model\Entity\Orders\Order;
use RetailCrm\Api\Model\Request\BySiteRequest;
use Throwable;
use WishboxVkRetailCrmLibrary\Factory\RetailCrmClientFactory;
use WishboxVkRetailCrmLibrary\Repositories\RetailCrmOrderRepository;

final class RetailCrmOrderRepositoryTest extends TestCase
{
	public function testCreatesOrderInRealRetailCrm(): void
	{
		if ($this->getEnvironmentVariable('RETAILCRM_RUN_WRITE_TESTS') !== '1')
		{
			self::markTestSkipped('Set RETAILCRM_RUN_WRITE_TESTS=1 to create a real RetailCRM order.');
		}

		$apiUrl = $this->requireEnvironmentVariable('RETAILCRM_API_URL');
		$apiKey = $this->requireEnvironmentVariable('RETAILCRM_API_KEY');
		$site = $this->requireEnvironmentVariable('RETAILCRM_SITE');
		$offerId = (int) $this->requireEnvironmentVariable('RETAILCRM_TEST_OFFER_ID');
		$externalId = 'wishbox-integration-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(4));
		$offer = new Offer();
		$offer->id = $offerId;
		$orderProduct = new OrderProduct();
		$orderProduct->offer = $offer;
		$orderProduct->quantity = 1.0;
		$orderProduct->initialPrice = (float) ($this->getEnvironmentVariable('RETAILCRM_TEST_PRICE') ?: 1);
		$orderProduct->productName = 'WishBox integration test';
		$order = new Order();
		$order->externalId = $externalId;
		$order->site = $site;
		$order->orderType = $this->requireEnvironmentVariable('RETAILCRM_ORDER_TYPE');
		$order->orderMethod = $this->requireEnvironmentVariable('RETAILCRM_ORDER_METHOD');
		$order->status = $this->requireEnvironmentVariable('RETAILCRM_ORDER_STATUS');
		$order->currency = $this->getEnvironmentVariable('RETAILCRM_ORDER_CURRENCY') ?: 'RUB';
		$order->firstName = 'WishBox';
		$order->lastName = 'Integration Test';
		$order->managerComment = 'Created by the pkg_wishboxvkretailcrm integration test.';
		$client = RetailCrmClientFactory::createClient(
			$apiUrl,
			$apiKey,
			$this->getEnvironmentVariable('RETAILCRM_PROXY_TOKEN')
		);
		$repository = new RetailCrmOrderRepository($client);

		try
		{
			$orderId = $repository->createOrder($order, $site);
		}
		catch (Throwable $throwable)
		{
			fwrite(
				STDERR,
				sprintf(
					"\nRetailCRM createOrder failed:\n"
					. "  API URL: %s\n"
					. "  Site: %s\n"
					. "  External ID: %s\n"
					. "  Offer ID: %d\n"
					. "  Exception: %s\n"
					. "  Code: %s\n"
					. "  Message: %s\n",
					$apiUrl,
					$site,
					$externalId,
					$offerId,
					$throwable::class,
					(string) $throwable->getCode(),
					$throwable->getMessage()
				)
			);

			throw $throwable;
		}

		self::assertGreaterThan(0, $orderId);

		try
		{
			$getResponse = $client->orders->get(
				$externalId,
				new BySiteRequest(ByIdentifier::EXTERNAL_ID, $site)
			);
		}
		catch (Throwable $throwable)
		{
			fwrite(
				STDERR,
				sprintf(
					"\nRetailCRM order read failed:\n"
					. "  API URL: %s\n"
					. "  Site: %s\n"
					. "  External ID: %s\n"
					. "  Exception: %s\n"
					. "  Code: %s\n"
					. "  Message: %s\n",
					$apiUrl,
					$site,
					$externalId,
					$throwable::class,
					(string) $throwable->getCode(),
					$throwable->getMessage()
				)
			);

			throw $throwable;
		}

		self::assertInstanceOf(Order::class, $getResponse->order);
		self::assertSame($orderId, $getResponse->order->id);
		self::assertSame($externalId, $getResponse->order->externalId);
		self::assertNotSame('', (string) $getResponse->order->site);

		fwrite(
			STDOUT,
			sprintf(
				"\nRetailCRM order created and read successfully:\n"
				. "  ID: %d\n"
				. "  External ID: %s\n"
				. "  Requested site: %s\n"
				. "  Returned site: %s\n",
				$orderId,
				$externalId,
				$site,
				(string) $getResponse->order->site
			)
		);
	}

	private function requireEnvironmentVariable(string $name): string
	{
		$value = trim($this->getEnvironmentVariable($name));

		if ($value === '')
		{
			self::markTestSkipped('Set ' . $name . ' to run the RetailCRM integration test.');
		}

		return $value;
	}

	private function getEnvironmentVariable(string $name): string
	{
		return (string) ($_ENV[$name] ?? $_SERVER[$name] ?? getenv($name));
	}
}
