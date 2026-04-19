<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use VerifIP\Client;
use VerifIP\Exceptions\AuthenticationException;
use VerifIP\Exceptions\InvalidRequestException;
use VerifIP\Exceptions\RateLimitException;
use VerifIP\Exceptions\VerifIPException;

$client = new Client(
    apiKey: 'vip_your_api_key_here',
    baseUrl: 'http://localhost:8080',
);

try {
    // Single IP check
    $result = $client->check('185.220.101.1');

    echo "IP:          {$result->ip}\n";
    echo "Fraud Score: {$result->fraudScore}/100\n";
    echo "Tor:         " . ($result->isTor ? 'Yes' : 'No') . "\n";
    echo "VPN:         " . ($result->isVpn ? 'Yes' : 'No') . "\n";
    echo "Proxy:       " . ($result->isProxy ? 'Yes' : 'No') . "\n";
    echo "Datacenter:  " . ($result->isDatacenter ? 'Yes' : 'No') . "\n";
    echo "Country:     {$result->countryName} ({$result->countryCode})\n";
    echo "ISP:         {$result->isp} (AS{$result->asn})\n";
    echo "Type:        {$result->connectionType}\n";
    echo "Signals:     " . json_encode($result->signalBreakdown) . "\n";

    // Rate limit info
    $rateLimit = $client->getRateLimit();
    if ($rateLimit !== null) {
        echo "\nRate Limit:  {$rateLimit->remaining}/{$rateLimit->limit} remaining\n";
    }
} catch (AuthenticationException $e) {
    echo "Auth error: {$e->getMessage()}\n";
} catch (RateLimitException $e) {
    echo "Rate limited. Retry after: {$e->retryAfter} seconds\n";
} catch (InvalidRequestException $e) {
    echo "Bad request: {$e->getMessage()}\n";
} catch (VerifIPException $e) {
    echo "API error ({$e->statusCode}): {$e->getMessage()}\n";
}
