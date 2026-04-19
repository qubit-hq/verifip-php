# VerifIP PHP SDK

Official PHP SDK for the [VerifIP](https://verifip.com) IP fraud scoring API.

## Requirements

- PHP 8.1 or higher
- ext-curl
- ext-json

## Installation

```bash
composer require qubit-hq/verifip
```

## Quick Start

```php
use VerifIP\Client;

$client = new Client(apiKey: 'vip_your_api_key');

$result = $client->check('185.220.101.1');

echo $result->fraudScore;   // 70
echo $result->isTor;        // true
echo $result->countryCode;  // "DE"
```

## Methods

### `check(string $ip): CheckResponse`

Check a single IP address for fraud risk.

```php
$result = $client->check('8.8.8.8');

$result->requestId;       // string - unique request ID
$result->ip;              // string - the checked IP
$result->fraudScore;      // int    - 0-100 risk score
$result->isProxy;         // bool
$result->isVpn;           // bool
$result->isTor;           // bool
$result->isDatacenter;    // bool
$result->countryCode;     // string - ISO 3166-1 alpha-2
$result->countryName;     // string
$result->region;          // string
$result->city;            // string
$result->isp;             // string
$result->asn;             // int
$result->connectionType;  // string
$result->hostname;        // string
$result->signalBreakdown; // array<string, int>
$result->error;           // ?string
```

### `checkBatch(array $ips): BatchResponse`

Check multiple IPs in a single request (1-100 IPs). Requires Starter plan or higher.

```php
$batch = $client->checkBatch(['185.220.101.1', '8.8.8.8']);

foreach ($batch->results as $result) {
    echo "{$result->ip}: {$result->fraudScore}\n";
}
```

### `health(): HealthResponse`

Check API server health. Does not require authentication.

```php
$health = $client->health();

echo $health->status;        // "ok"
echo $health->version;       // "1.0.0"
echo $health->uptimeSeconds; // 3600
```

### `getRateLimit(): ?RateLimitInfo`

Get rate limit info from the most recent API response.

```php
$rateLimit = $client->getRateLimit();

if ($rateLimit !== null) {
    echo $rateLimit->limit;     // 1000
    echo $rateLimit->remaining; // 999
    echo $rateLimit->reset;     // DateTimeImmutable
}
```

## Error Handling

All API errors throw exceptions that extend `VerifIPException`:

```php
use VerifIP\Exceptions\AuthenticationException;
use VerifIP\Exceptions\InvalidRequestException;
use VerifIP\Exceptions\RateLimitException;
use VerifIP\Exceptions\ServerException;
use VerifIP\Exceptions\VerifIPException;

try {
    $result = $client->check($ip);
} catch (AuthenticationException $e) {
    // 401/403 - invalid or disabled API key
} catch (RateLimitException $e) {
    // 429 - rate limit exceeded
    echo "Retry after: {$e->retryAfter} seconds";
} catch (InvalidRequestException $e) {
    // 400 - invalid IP, bad request
} catch (ServerException $e) {
    // 5xx - server error
} catch (VerifIPException $e) {
    // Other API errors
    echo $e->statusCode;
    echo $e->errorCode;
}
```

## Configuration

```php
$client = new Client(
    apiKey: 'vip_your_api_key',
    baseUrl: 'https://api.verifip.com',  // default
    timeout: 30.0,                        // seconds, default 30
    maxRetries: 3,                        // retry on 429/5xx, default 3
);
```

### Retry Behavior

The client automatically retries on 429 and 5xx status codes with exponential backoff:

- Base delay: `0.5 * 2^attempt` seconds
- Maximum delay: 30 seconds
- Jitter: up to 25% of the delay is added randomly
- On 429 responses, the `retry_after` value from the API is used when available

## Rate Limits

Rate limit headers (`X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`) are automatically parsed from every API response. Access them via `getRateLimit()` after any API call.
