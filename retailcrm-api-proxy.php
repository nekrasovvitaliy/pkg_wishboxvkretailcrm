<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

declare(strict_types=1);

const RETAILCRM_UPSTREAM = 'https://your-account.retailcrm.ru';
const PROXY_SHARED_SECRET = '';
const MAX_REQUEST_BODY_BYTES = 10485760;

if (PROXY_SHARED_SECRET === '')
{
	respondWithError(503, 'proxy_not_configured', 'Configure PROXY_SHARED_SECRET.');
}

$proxyToken = (string) ($_SERVER['HTTP_X_RETAILCRM_PROXY_TOKEN'] ?? '');

if ($proxyToken === '' || !hash_equals(PROXY_SHARED_SECRET, $proxyToken))
{
	respondWithError(403, 'forbidden', 'Invalid proxy token.');
}

if (!extension_loaded('curl'))
{
	respondWithError(500, 'proxy_configuration_error', 'The curl PHP extension is required.');
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if (!in_array($method, ['GET', 'POST'], true))
{
	header('Allow: GET, POST');
	respondWithError(405, 'method_not_allowed', 'Only GET and POST requests are allowed.');
}

$contentLength = filter_var($_SERVER['CONTENT_LENGTH'] ?? 0, FILTER_VALIDATE_INT);

if ($contentLength !== false && $contentLength > MAX_REQUEST_BODY_BYTES)
{
	respondWithError(413, 'request_too_large', 'The request body is too large.');
}

$requestPath = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
$apiPath = $scriptName !== '' && str_starts_with($requestPath, $scriptName)
	? substr($requestPath, strlen($scriptName))
	: (string) ($_SERVER['PATH_INFO'] ?? '');
$apiPath = '/' . ltrim($apiPath, '/');

if (!preg_match('#^/api/v5(?:/|$)[a-zA-Z0-9_./{}-]*$#', $apiPath) || str_contains($apiPath, '..'))
{
	respondWithError(400, 'invalid_api_path', 'Only RetailCRM API v5 paths are allowed.');
}

$queryString = (string) ($_SERVER['QUERY_STRING'] ?? '');
$upstreamUrl = rtrim(RETAILCRM_UPSTREAM, '/') . $apiPath
	. ($queryString !== '' ? '?' . $queryString : '');
$requestHeaders = [];

foreach (getallheaders() as $name => $value)
{
	if (in_array(strtolower($name), ['accept', 'content-type', 'user-agent', 'x-api-key'], true))
	{
		$requestHeaders[] = $name . ': ' . $value;
	}
}

$curl = curl_init($upstreamUrl);

if ($curl === false)
{
	respondWithError(500, 'proxy_initialization_error', 'Failed to initialize curl.');
}

$responseHeaders = [];
$requestBody = file_get_contents('php://input');

curl_setopt_array(
	$curl,
	[
		CURLOPT_CONNECTTIMEOUT => 30,
		CURLOPT_CUSTOMREQUEST  => $method,
		CURLOPT_ENCODING       => '',
		CURLOPT_HEADERFUNCTION => static function ($curlHandle, string $headerLine) use (&$responseHeaders): int
		{
			$length = strlen($headerLine);
			$headerLine = trim($headerLine);

			if (str_contains($headerLine, ':'))
			{
				[$name, $value] = explode(':', $headerLine, 2);

				if (in_array(strtolower(trim($name)), ['content-type', 'retry-after', 'x-request-id'], true))
				{
					$responseHeaders[] = trim($name) . ': ' . trim($value);
				}
			}

			return $length;
		},
		CURLOPT_HTTPHEADER     => $requestHeaders,
		CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_SSL_VERIFYHOST => 2,
		CURLOPT_SSL_VERIFYPEER => true,
		CURLOPT_TIMEOUT        => 80,
	]
);

if ($method === 'POST' && $requestBody !== false && $requestBody !== '')
{
	curl_setopt($curl, CURLOPT_POSTFIELDS, $requestBody);
}

$responseBody = curl_exec($curl);
$httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
$curlErrno = curl_errno($curl);

if ($responseBody === false)
{
	respondWithError(
		502,
		'upstream_connection_error',
		'Could not connect to the RetailCRM API.',
		['curl_errno' => $curlErrno]
	);
}

http_response_code($httpCode > 0 ? $httpCode : 502);

foreach ($responseHeaders as $responseHeader)
{
	header($responseHeader, false);
}

echo $responseBody;

/**
 * @param   array<string, int|string>  $details  Safe diagnostic fields.
 */
function respondWithError(int $status, string $code, string $description, array $details = []): never
{
	http_response_code($status);
	header('Content-Type: application/json; charset=utf-8');

	echo json_encode(
		[
			'error' => [
				'code'        => $code,
				'description' => $description,
			] + $details,
		],
		JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	);

	exit;
}
