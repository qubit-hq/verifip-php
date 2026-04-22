<?php

declare(strict_types=1);

namespace VerifIP;

use VerifIP\Exceptions\AuthenticationException;
use VerifIP\Exceptions\InvalidRequestException;
use VerifIP\Exceptions\RateLimitException;
use VerifIP\Exceptions\ServerException;
use VerifIP\Exceptions\VerifIPException;
use VerifIP\Models\BatchResponse;
use VerifIP\Models\CheckResponse;
use VerifIP\Models\HealthResponse;
use VerifIP\Models\RateLimitInfo;

/**
 * Client for the VerifIP IP fraud scoring API.
 *
 * Uses only ext-curl -- no Guzzle or other HTTP libraries required.
 */
class Client
{
    private const VERSION = '0.1.0';
    private const USER_AGENT = 'verifip-php/0.1.1';
    private const RETRYABLE_STATUS_CODES = [429, 500, 502, 503, 504];

    private ?RateLimitInfo $rateLimit = null;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl = 'https://api.verifip.com',
        private readonly float $timeout = 30.0,
        private readonly int $maxRetries = 3,
    ) {
        if ($apiKey === '') {
            throw new \InvalidArgumentException('apiKey is required');
        }
    }

    /**
     * Check a single IP address for fraud risk.
     *
     * @throws InvalidRequestException If the IP is malformed or reserved.
     * @throws AuthenticationException If the API key is invalid or disabled.
     * @throws RateLimitException If the daily limit is exceeded.
     */
    public function check(string $ip): CheckResponse
    {
        if ($ip === '') {
            throw new \InvalidArgumentException('ip is required');
        }

        $data = $this->request('GET', '/v1/check?ip=' . urlencode($ip));

        return CheckResponse::fromArray($data);
    }

    /**
     * Check multiple IP addresses in a single request.
     *
     * Requires Starter plan or higher. Maximum 100 IPs per request.
     *
     * @param list<string> $ips List of IPv4/IPv6 addresses (1-100).
     *
     * @throws \InvalidArgumentException If the list is empty or exceeds 100.
     */
    public function checkBatch(array $ips): BatchResponse
    {
        if ($ips === []) {
            throw new \InvalidArgumentException('ips list is required and cannot be empty');
        }
        if (count($ips) > 100) {
            throw new \InvalidArgumentException('Maximum 100 IPs per batch request');
        }

        $body = json_encode(['ips' => $ips], JSON_THROW_ON_ERROR);
        $data = $this->request('POST', '/v1/check/batch', body: $body);

        return BatchResponse::fromArray($data);
    }

    /**
     * Check API server health status. Does not require authentication.
     */
    public function health(): HealthResponse
    {
        $data = $this->request('GET', '/health', auth: false);

        return HealthResponse::fromArray($data);
    }

    /**
     * Get rate limit info from the most recent API response.
     */
    public function getRateLimit(): ?RateLimitInfo
    {
        return $this->rateLimit;
    }

    /**
     * Perform an HTTP request. Override in tests to mock responses.
     *
     * @return array{status: int, body: string, headers: array<string, string>}
     */
    protected function doRequest(string $method, string $url, array $headers, ?string $body): array
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => (int) ceil($this->timeout),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADER => true,
            CURLOPT_CUSTOMREQUEST => $method,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $rawResponse = curl_exec($ch);

        if ($rawResponse === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new VerifIPException(
                "Connection error: {$error}",
                statusCode: 0,
                errorCode: 'connection_error',
            );
        }

        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        /** @var string $rawResponse */
        $rawHeaders = substr($rawResponse, 0, $headerSize);
        $responseBody = substr($rawResponse, $headerSize);

        $parsedHeaders = $this->parseHeaders($rawHeaders);

        return [
            'status' => $statusCode,
            'body' => $responseBody,
            'headers' => $parsedHeaders,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?string $body = null, bool $auth = true): array
    {
        $url = rtrim($this->baseUrl, '/') . $path;
        $lastError = null;

        for ($attempt = 0; $attempt <= $this->maxRetries; $attempt++) {
            $headers = [
                'User-Agent: ' . self::USER_AGENT,
                'Accept: application/json',
            ];

            if ($body !== null) {
                $headers[] = 'Content-Type: application/json';
            }

            if ($auth) {
                $headers[] = 'Authorization: Bearer ' . $this->apiKey;
            }

            try {
                $response = $this->doRequest($method, $url, $headers, $body);
            } catch (VerifIPException $e) {
                $lastError = $e;
                if ($attempt < $this->maxRetries) {
                    $this->sleep(0.5 * (2 ** $attempt));
                    continue;
                }
                throw $e;
            }

            $statusCode = $response['status'];
            $responseBody = $response['body'];
            $responseHeaders = $response['headers'];

            $this->updateRateLimit($responseHeaders);

            // Success
            if ($statusCode >= 200 && $statusCode < 300) {
                return $responseBody !== '' ? json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR) : [];
            }

            // Error
            $errorData = [];
            try {
                $errorData = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                // Ignore malformed JSON in error responses
            }

            $errorCode = (string) ($errorData['error'] ?? '');
            $message = (string) ($errorData['message'] ?? $responseBody);
            $retryAfter = isset($errorData['retry_after']) ? (int) $errorData['retry_after'] : null;

            $exception = self::makeException($statusCode, $errorCode, $message, $retryAfter);

            if (in_array($statusCode, self::RETRYABLE_STATUS_CODES, true) && $attempt < $this->maxRetries) {
                $lastError = $exception;
                $delay = $retryAfter ?? 0.5 * (2 ** $attempt);
                $delay = min($delay, 30);
                $delay += $delay * 0.25 * (mt_rand(0, 100) / 100);
                $this->sleep($delay);
                continue;
            }

            throw $exception;
        }

        throw $lastError ?? new VerifIPException('Request failed after retries');
    }

    private function updateRateLimit(array $headers): void
    {
        $info = RateLimitInfo::fromHeaders($headers);
        if ($info !== null) {
            $this->rateLimit = $info;
        }
    }

    /**
     * @return array<string, string>
     */
    private function parseHeaders(string $rawHeaders): array
    {
        $headers = [];
        foreach (explode("\r\n", $rawHeaders) as $line) {
            if (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $headers[trim($key)] = trim($value);
            }
        }

        return $headers;
    }

    /**
     * Sleep for a number of seconds. Extracted for testability.
     */
    protected function sleep(float $seconds): void
    {
        usleep((int) ($seconds * 1_000_000));
    }

    private static function makeException(int $status, string $code, string $message, ?int $retryAfter): VerifIPException
    {
        $args = [
            'statusCode' => $status,
            'errorCode' => $code,
            'retryAfter' => $retryAfter,
        ];

        return match (true) {
            $status === 400 => new InvalidRequestException($message, ...$args),
            $status === 401, $status === 403 => new AuthenticationException($message, ...$args),
            $status === 429 => new RateLimitException($message, ...$args),
            $status >= 500 => new ServerException($message, ...$args),
            default => new VerifIPException($message, ...$args),
        };
    }

    public function __toString(): string
    {
        return sprintf('VerifIP\Client(baseUrl=%s)', $this->baseUrl);
    }
}
