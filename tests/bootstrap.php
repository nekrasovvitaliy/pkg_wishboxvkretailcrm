<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

define('_JEXEC', 1);

require dirname(__DIR__) . '/vendor/autoload.php';

$environmentFileDirectory = dirname(__DIR__);

if (is_file($environmentFileDirectory . '/.env'))
{
	Dotenv\Dotenv::createImmutable($environmentFileDirectory)->safeLoad();
}

class_alias(
	Tests\Stubs\WishboxVkLibrary\Dto\VkMarketItemUpdate::class,
	WishboxVkLibrary\Dto\VkMarketItemUpdate::class
);
class_alias(
	Tests\Stubs\WishboxVkLibrary\Service\VkMarketService::class,
	WishboxVkLibrary\Service\VkMarketService::class
);
class_alias(
	Tests\Stubs\WishboxVkLibrary\Service\VkOrderService::class,
	WishboxVkLibrary\Service\VkOrderService::class
);
