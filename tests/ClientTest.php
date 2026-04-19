<?php

declare(strict_types=1);

namespace VerifIP\Tests;

use PHPUnit\Framework\TestCase;
use VerifIP\Client;
use VerifIP\Exceptions\AuthenticationException;
use VerifIP\Exceptions\InvalidRequestException;
use VerifIP\Exceptions\RateLimitException;
use VerifIP\Exceptions\ServerException;
use VerifIP\Exceptions\VerifIPException;

/**
 * Testable client that overrides doRequest to avoid real HTTP calls.
 */
class MockClient extends Client
{
    /** @var array{status: int, body: string, headers: array<string, string>} */
    public array $mockResponse = ['status' => 200, 'body' => '{}', 'headers' => []];

    /** @var list<array{method: string, url: string, headers: list<string>, body: ?string}> */
    public array $requestLog = [];

    public function __construct(string $apiKey = 'vip_testkey', string $baseUrl = 'http://localhost:8080')
    {
        parent::__construct($apiKey, $baseUrl, maxRetries: 0);
    }

    protected function doRequest(string $method, string $url, array $headers, ?string $body): array
    {
        $this->requestLog[] = [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
        ];

        return $this->mockResponse;
    }

    protected function sleep(float $seconds): void
    {
        // No-op in tests
    }

    public function setMockResponse(int $status, array $data, array $headers = []): void
    {
        $defaultHeaders = [
            'X-RateLimit-Limit' => '1000',
            'X-RateLimit-Remaining' => '999',
            'X-RateLimit-Reset' => '1713052800',
        ];

        $this->mockResponse = [
            'status' => $status,
            'body' => json_encode($data, JSON_THROW_ON_ERROR),
            'headers' => array_merge($defaultHeaders, $headers),
        ];
    }
}

final class ClientTest extends TestCase
{
    private const SAMPLE_CHECK_RESPONSE = [
        'request_id' => 'test-uuid',
        'ip' => '185.220.101.1',
        'fraud_score' => 70,
        'is_proxy' => true,
        'is_vpn' => true,
        'is_tor' => true,
        'is_datacenter' => true,
        'country_code' => 'DE',
        'country_name' => 'Germany',
        'region' => 'Brandenburg',
        'city' => 'Brandenburg',
        'isp' => 'Stiftung Erneuerbare Freiheit',
        'asn' => 60729,
        'connection_type' => 'Data Center',
        'hostname' => 'tor-exit.example.org',
        'signal_breakdown' => [
            'tor_exit' => 25,
            'vpn_detected' => 20,
            'proxy_detected' => 15,
            'datacenter_ip' => 10,
        ],
    ];

    private const SAMPLE_HEALTH_RESPONSE = [
        'status' => 'ok',
        'version' => '1.0.0',
        'data_loaded_at' => '2026-04-19T12:00:00Z',
        'redis' => 'ok',
        'postgres' => 'ok',
        'uptime_seconds' => 3600,
    ];

    // -- check() tests --

    public function testCheckSuccess(): void
    {
        $client = new MockClient();
        $client->setMockResponse(200, self::SAMPLE_CHECK_RESPONSE);

        $result = $client->check('185.220.101.1');

        $this->assertSame(70, $result->fraudScore);
        $this->assertTrue($result->isTor);
        $this->assertTrue($result->isVpn);
        $this->assertTrue($result->isProxy);
        $this->assertTrue($result->isDatacenter);
        $this->assertSame('DE', $result->countryCode);
        $this->assertSame('Germany', $result->countryName);
        $this->assertSame(60729, $result->asn);
        $this->assertSame('Data Center', $result->connectionType);
        $this->assertSame(25, $result->signalBreakdown['tor_exit']);
    }

    public function testCheckSendsCorrectRequest(): void
    {
        $client = new MockClient();
        $client->setMockResponse(200, self::SAMPLE_CHECK_RESPONSE);

        $client->check('8.8.8.8');

        $this->assertCount(1, $client->requestLog);
        $req = $client->requestLog[0];
        $this->assertSame('GET', $req['method']);
        $this->assertStringContains('/v1/check?ip=8.8.8.8', $req['url']);
        $this->assertContainsHeader('Authorization: Bearer vip_testkey', $req['headers']);
        $this->assertContainsHeader('User-Agent: verifip-php/0.1.0', $req['headers']);
    }

    public function testCheckParsesRateLimit(): void
    {
        $client = new MockClient();
        $client->setMockResponse(200, self::SAMPLE_CHECK_RESPONSE);

        $client->check('8.8.8.8');

        $rateLimit = $client->getRateLimit();
        $this->assertNotNull($rateLimit);
        $this->assertSame(1000, $rateLimit->limit);
        $this->assertSame(999, $rateLimit->remaining);
    }

    public function testCheckEmptyIpThrows(): void
    {
        $client = new MockClient();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ip is required');
        $client->check('');
    }

    public function testCheckInvalidIpThrows400(): void
    {
        $client = new MockClient();
        $client->setMockResponse(400, [
            'error' => 'invalid_ip',
            'message' => 'Invalid IP address',
        ]);

        $this->expectException(InvalidRequestException::class);
        try {
            $client->check('not-an-ip');
        } catch (InvalidRequestException $e) {
            $this->assertSame(400, $e->statusCode);
            throw $e;
        }
    }

    public function testCheckAuthError(): void
    {
        $client = new MockClient(apiKey: 'vip_badkey');
        $client->setMockResponse(401, [
            'error' => 'invalid_api_key',
            'message' => 'Invalid API key',
        ]);

        $this->expectException(AuthenticationException::class);
        $client->check('8.8.8.8');
    }

    public function testCheckRateLimitError(): void
    {
        $client = new MockClient();
        $client->setMockResponse(429, [
            'error' => 'rate_limit_exceeded',
            'message' => 'Limit exceeded',
            'retry_after' => 3600,
        ]);

        try {
            $client->check('8.8.8.8');
            $this->fail('Expected RateLimitException');
        } catch (RateLimitException $e) {
            $this->assertSame(429, $e->statusCode);
            $this->assertSame(3600, $e->retryAfter);
        }
    }

    public function testCheckServerError(): void
    {
        $client = new MockClient();
        $client->setMockResponse(500, [
            'error' => 'internal_error',
            'message' => 'Server error',
        ]);

        $this->expectException(ServerException::class);
        $client->check('8.8.8.8');
    }

    // -- checkBatch() tests --

    public function testBatchSuccess(): void
    {
        $client = new MockClient();
        $secondResult = array_merge(self::SAMPLE_CHECK_RESPONSE, [
            'ip' => '8.8.8.8',
            'fraud_score' => 0,
        ]);
        $client->setMockResponse(200, [
            'results' => [self::SAMPLE_CHECK_RESPONSE, $secondResult],
        ]);

        $result = $client->checkBatch(['185.220.101.1', '8.8.8.8']);

        $this->assertCount(2, $result->results);
        $this->assertSame(70, $result->results[0]->fraudScore);
        $this->assertSame(0, $result->results[1]->fraudScore);
        $this->assertSame('8.8.8.8', $result->results[1]->ip);
    }

    public function testBatchSendsPost(): void
    {
        $client = new MockClient();
        $client->setMockResponse(200, ['results' => []]);

        $client->checkBatch(['1.2.3.4']);

        $req = $client->requestLog[0];
        $this->assertSame('POST', $req['method']);
        $this->assertStringContains('/v1/check/batch', $req['url']);
        $this->assertNotNull($req['body']);
        $decoded = json_decode($req['body'], true);
        $this->assertSame(['1.2.3.4'], $decoded['ips']);
    }

    public function testBatchEmptyThrows(): void
    {
        $client = new MockClient();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be empty');
        $client->checkBatch([]);
    }

    public function testBatchOver100Throws(): void
    {
        $client = new MockClient();

        $ips = [];
        for ($i = 0; $i <= 100; $i++) {
            $ips[] = "1.2.3.{$i}";
        }

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Maximum 100');
        $client->checkBatch($ips);
    }

    // -- health() tests --

    public function testHealthSuccess(): void
    {
        $client = new MockClient();
        $client->setMockResponse(200, self::SAMPLE_HEALTH_RESPONSE);

        $result = $client->health();

        $this->assertSame('ok', $result->status);
        $this->assertSame('1.0.0', $result->version);
        $this->assertSame(3600, $result->uptimeSeconds);
        $this->assertSame('ok', $result->redis);
        $this->assertSame('ok', $result->postgres);
    }

    public function testHealthNoAuth(): void
    {
        $client = new MockClient();
        $client->setMockResponse(200, self::SAMPLE_HEALTH_RESPONSE);

        $client->health();

        $req = $client->requestLog[0];
        foreach ($req['headers'] as $header) {
            $this->assertStringNotContainsString('Authorization', $header);
        }
    }

    // -- constructor tests --

    public function testEmptyApiKeyThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('apiKey is required');
        new Client('');
    }

    public function testGetRateLimitNullByDefault(): void
    {
        $client = new MockClient();
        $this->assertNull($client->getRateLimit());
    }

    // -- helpers --

    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertStringContainsString($needle, $haystack);
    }

    private function assertContainsHeader(string $expected, array $headers): void
    {
        $this->assertContains($expected, $headers, "Expected header '{$expected}' not found in: " . implode(', ', $headers));
    }
}
