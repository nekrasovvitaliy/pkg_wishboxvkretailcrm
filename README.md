# WishBox VK RetailCRM

Joomla-пакет для двусторонней интеграции VK Маркета и RetailCRM:

- обновляет в VK Маркете только цену и наличие товаров из RetailCRM;
- импортирует новые заказы VK Маркета в RetailCRM;
- принимает событие `market_order_new` через VK Callback API;
- поддерживает плановый импорт заказов через Joomla Scheduler.

## Зависимости

- PHP 8.5 или новее;
- Joomla с компонентом Scheduler;
- установленный пакет `pkg_wishboxvk` и библиотека `WishboxVkLibrary`;
- официальный PHP SDK `retailcrm/api-client-php` версии 6.15 или новее.

SDK должен быть доступен через Composer autoloader сайта. Зависимость также
объявлена в корневом `composer.json` проекта.

## Связь товаров RetailCRM и VK

В RetailCRM создайте пользовательское свойство предложения, содержащее числовой
ID товара VK Маркета. По умолчанию интеграция ищет свойство с кодом:

```text
vk_market_item_id
```

Код свойства можно изменить в параметре плагина «Свойство RetailCRM с ID товара
VK». Сначала проверяются свойства offer, затем свойства родительского product.

Цена берётся из типа цены `base` или из типа, указанного в настройках. Если такого
типа нет, используется первая доступная цена предложения.

Товар считается доступным, когда предложение и родительский товар активны, а
`quantity` предложения больше нуля. В VK отправляются только:

- `price`;
- `deleted` — обратный признак наличия.

Название, описание, фотографии и старая цена не изменяются.

## Импорт заказов

Заказы загружаются методами `market.getGroupOrders` и `market.getOrderItems` из
`WishboxVkLibrary`, после чего создаются через официальный RetailCRM SDK.

Позиции заказа сопоставляются с предложениями RetailCRM через то же свойство
`vk_market_item_id`. Значение `display_order_id` VK используется как
`externalId` заказа RetailCRM, что предотвращает повторный импорт.

В настройках необходимо указать действующие коды RetailCRM:

- сайта;
- типа заказа;
- способа оформления;
- начального статуса;
- валюты.

Код marketplace и пользовательское поле для ID заказа VK необязательны.

## Состав пакета

```text
pkg_wishboxvkretailcrm/
├── lib_wishboxvkretailcrm/          # адаптеры RetailCRM и сервисы интеграции
├── plg_task_wishboxvkretailcrm/     # задачи Joomla Scheduler
├── plg_webservices_wishboxvkretailcrm/ # endpoint VK Callback API
├── language/
├── tests/                             # PHPUnit-тесты без сетевых запросов
├── build.xml                         # сборка пакета через Apache Ant
├── script.php                        # проверка версий Joomla и PHP
└── pkg_wishboxvkretailcrm.xml
```

Task-плагин предоставляет две задачи:

1. `Update VK Market products from RetailCRM`;
2. `Import VK Market orders into RetailCRM`.

Webservices-плагин регистрирует публичный маршрут:

```text
POST /api/index.php/v1/wishboxvkretailcrm/callback
```

Этот URL необходимо указать в настройках Callback API сообщества VK.

## Прокси VK API

Файл `vk-api-proxy.php` можно отдельно разместить на HTTPS-сервере, который
имеет доступ к `https://api.vk.com/method/`. Перед публикацией обязательно
укажите разрешённый IP приложения в `ALLOWED_CLIENT_IPS` либо задайте
`PROXY_SHARED_SECRET`. Без одного из этих ограничений прокси откажется работать.

VK API method передаётся после имени файла, например:

```text
POST https://proxy.example/vk-api-proxy.php/market.getGroupOrders
```

Адрес прокси указывается в поле «URL API VK» настроек task- и
webservices-плагинов. Например:

```text
https://proxy.example/vk-api-proxy.php/
```

Если в `vk-api-proxy.php` задан `PROXY_SHARED_SECRET`, укажите такое же значение
в поле «Токен прокси VK». Клиент передаст его в заголовке
`X-VK-Proxy-Token`. Поле можно оставить пустым при авторизации прокси по IP.
Прокси не хранит и не журналирует access token VK.

## Прокси RetailCRM API

Файл `retailcrm-api-proxy.php` размещается на HTTPS-сервере, имеющем доступ к
RetailCRM. В файле укажите URL аккаунта без `/api/v5` и общий секрет:

```php
const RETAILCRM_UPSTREAM = 'https://your-account.retailcrm.ru';
const PROXY_SHARED_SECRET = 'change-this-secret';
```

В поле «URL API RetailCRM» укажите URL файла без `/api/v5`:

```text
https://proxy.example/retailcrm-api-proxy.php
```

В поле «Токен прокси RetailCRM» укажите значение `PROXY_SHARED_SECRET`.
RetailCRM SDK самостоятельно добавит к URL путь `/api/v5/...`, а интеграция
передаст токен в заголовке `X-RetailCRM-Proxy-Token`.

## Сборка

Из корня проекта:

```bash
ant
```

Готовый установочный архив будет создан в корне проекта под именем
`pkg_wishboxvkretailcrm.zip`. Промежуточные архивы расширений удаляются после
сборки.

## Integration-тест RetailCRM

Обычная команда `composer test` использует только mocks и не выполняет сетевые
запросы. Отдельный integration-тест подключается к настоящей RetailCRM и создаёт
заказ с уникальным `externalId`. Скопируйте `.env.example` в `.env`, заполните
параметры тестового аккаунта и явно разрешите запись:

```bash
cp .env.example .env
```

В `.env` установите `RETAILCRM_RUN_WRITE_TESTS=1`, затем выполните:

```bash
composer test:integration
```

Для работы через `retailcrm-api-proxy.php` укажите его URL в
`RETAILCRM_API_URL` и заполните `RETAILCRM_PROXY_TOKEN`. Локальный `.env`
исключён из Git. Созданный заказ тест не удаляет; он помечается комментарием
`Created by the pkg_wishboxvkretailcrm integration test.`

RetailCRM SDK требует сгенерированные сериализаторы моделей. Они создаются
автоматически после `composer install` и `composer update`. Для ручного запуска:

```bash
composer retailcrm:generate-models
```

## Тесты

Тесты используют PHPUnit 12.5 и PHP 8.5. VK и RetailCRM заменяются тестовыми
ответами, поэтому реальные API-запросы не выполняются.

```bash
composer install
composer test
```

Сначала установите `pkg_wishboxvk`, затем `pkg_wishboxvkretailcrm`.

## Лицензия

GNU General Public License version 2 или более поздняя версия.
