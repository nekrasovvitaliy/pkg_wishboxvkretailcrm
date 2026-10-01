<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

declare(strict_types=1);

const VK_API_UPSTREAM = 'https://api.vk.com/method';

// Configure at least an IP allowlist or a shared secret before publishing the proxy.
const ALLOWED_CLIENT_IPS = [
	// '203.0.113.10',
];

const PROXY_SHARED_SECRET = '';
const PROXY_SECRET_HEADER = 'HTTP_X_VK_PROXY_TOKEN';
const MAX_REQUEST_BODY_BYTES = 1048576;
const ALLOWED_HTTP_METHODS = ['GET', 'POST'];
const FORWARDED_REQUEST_HEADERS = [
	'accept',
	'authorization',
	'content-type',
	'user-agent',
];
const FORWARDED_RESPONSE_HEADERS = [
	'content-type',
	'retry-after',
	'x-request-id',
];

authorizeProxyRequest();

if (!extension_loaded('curl'))
{
	respondWithJson(500, 'proxy_configuration_error', 'The curl PHP extension is required.');
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if (!in_array($method, ALLOWED_HTTP_METHODS, true))
{
	header('Allow: GET, POST');
	respondWithJson(405, 'method_not_allowed', 'Only GET and POST requests are allowed.');
}

$contentLength = filter_var($_SERVER['CONTENT_LENGTH'] ?? 0, FILTER_VALIDATE_INT);

if ($contentLength !== false && $contentLength > MAX_REQUEST_BODY_BYTES)
{
	respondWithJson(413, 'request_too_large', 'The request body is too large.');
}

$vkMethod = getVkMethod();
$queryString = (string) ($_SERVER['QUERY_STRING'] ?? '');
$upstreamUrl = VK_API_UPSTREAM . '/' . $vkMethod . ($queryString !== '' ? '?' . $queryString : '');
$requestBody = file_get_contents('php://input');
$responseHeaders = [];
$curl = curl_init($upstreamUrl);

if ($curl === false)
{
	respondWithJson(500, 'proxy_initialization_error', 'Failed to initialize curl.');
}

curl_setopt_array(
	$curl,
	[
		CURLOPT_CONNECTTIMEOUT => 30,
		CURLOPT_CUSTOMREQUEST  => $method,
		CURLOPT_ENCODING       => '',
		CURLOPT_FOLLOWLOCATION => false,
		CURLOPT_HEADER         => false,
		CURLOPT_HEADERFUNCTION => static function ($curlHandle, string $headerLine) use (&$responseHeaders): int
		{
			$length = strlen($headerLine);
			$headerLine = trim($headerLine);

			if ($headerLine === '' || !str_contains($headerLine, ':'))
			{
				return $length;
			}

			[$name, $value] = explode(':', $headerLine, 2);
			$name = trim($name);

			if (in_array(strtolower($name), FORWARDED_RESPONSE_HEADERS, true))
			{
				$responseHeaders[] = $name . ': ' . trim($value);
			}

			return $length;
		},
		CURLOPT_HTTPHEADER     => getForwardHeaders(),
		CURLOPT_NOSIGNAL       => true,
		CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_SSL_VERIFYHOST => 2,
		CURLOPT_SSL_VERIFYPEER => true,
		CURLOPT_TIMEOUT        => 80,
	]
);

if ($requestBody !== false && $requestBody !== '' && $method === 'POST')
{
	curl_setopt($curl, CURLOPT_POSTFIELDS, $requestBody);
}

$responseBody = curl_exec($curl);
$curlErrno = curl_errno($curl);
$httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);

if ($responseBody === false)
{
	respondWithJson(
		502,
		'upstream_connection_error',
		'Could not connect to the VK API.',
		['curl_errno' => $curlErrno]
	);
}

http_response_code($httpCode > 0 ? $httpCode : 502);

foreach ($responseHeaders as $responseHeader)
{
	header($responseHeader, false);
}

echo $responseBody;

function authorizeProxyRequest(): void
{
	if (ALLOWED_CLIENT_IPS === [] && PROXY_SHARED_SECRET === '')
	{
		respondWithJson(
			503,
			'proxy_not_configured',
			'Configure ALLOWED_CLIENT_IPS or PROXY_SHARED_SECRET before using this proxy.'
		);
	}

	$clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
	$ipAllowed = ALLOWED_CLIENT_IPS !== [] && in_array($clientIp, ALLOWED_CLIENT_IPS, true);
	$providedSecret = (string) ($_SERVER[PROXY_SECRET_HEADER] ?? '');
	$secretAllowed = PROXY_SHARED_SECRET !== ''
		&& $providedSecret !== ''
		&& hash_equals(PROXY_SHARED_SECRET, $providedSecret);

	if (!$ipAllowed && !$secretAllowed)
	{
		respondWithJson(403, 'forbidden', 'The client is not allowed to use this proxy.');
	}
}

function getVkMethod(): string
{
	$pathInfo = (string) ($_SERVER['PATH_INFO'] ?? '');

	if ($pathInfo === '')
	{
		$requestPath = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
		$scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');

		if ($scriptName !== '' && str_starts_with($requestPath, $scriptName))
		{
			$pathInfo = substr($requestPath, strlen($scriptName));
		}
	}

	$vkMethod = trim($pathInfo, '/');

	if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_.]{1,127}$/', $vkMethod))
	{
		respondWithJson(400, 'invalid_vk_method', 'The URL must contain a valid VK API method name.');
	}

	return $vkMethod;
}

/**
 * Return the small request-header allowlist required by the VK API.
 *
 * @return list<string>
 */
function getForwardHeaders(): array
{
	$forwardHeaders = [];

	foreach (getRequestHeaders() as $name => $value)
	{
		if (in_array(strtolower($name), FORWARDED_REQUEST_HEADERS, true))
		{
			$forwardHeaders[] = $name . ': ' . $value;
		}
	}

	return $forwardHeaders;
}

/**
 * Read HTTP request headers on SAPIs that may not provide getallheaders().
 *
 * @return array<string, string>
 */
function getRequestHeaders(): array
{
	if (function_exists('getallheaders'))
	{
		$headers = getallheaders();

		return is_array($headers) ? $headers : [];
	}

	$headers = [];

	foreach ($_SERVER as $name => $value)
	{
		if (!str_starts_with($name, 'HTTP_') || !is_string($value))
		{
			continue;
		}

		$headerName = str_replace('_', '-', strtolower(substr($name, 5)));
		$headers[$headerName] = $value;
	}

	return $headers;
}

/**
 * Send a consistent proxy error without exposing credentials or request bodies.
 *
 * @param   array<string, int|string>  $details  Safe diagnostic fields.
 */
function respondWithJson(int $status, string $code, string $description, array $details = []): never
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
