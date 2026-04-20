<?php

declare(strict_types=1);

namespace VerifIP\Models;

/**
 * Rate limit information parsed from response headers.
 */
final class RateLimitInfo
{
    public function __construct(
        public int $limit = 0,
        public int $remaining = 0,
        public ?\DateTimeImmutable $reset = null,
    ) {}

    /**
     * Parse rate limit info from HTTP response headers.
     *
     * @param array<string, string> $headers Header name => value (case-insensitive keys supported)
     */
    public static function fromHeaders(array $headers): ?self
    {
        // Normalize header keys to lowercase for case-insensitive lookup
        $normalized = [];
        foreach ($headers as $key => $value) {
            $normalized[strtolower($key)] = $value;
        }

        $limit = $normalized['x-ratelimit-limit'] ?? null;
        if ($limit === null) {
            return null;
        }

        $remaining = $normalized['x-ratelimit-remaining'] ?? '0';
        $resetTimestamp = $normalized['x-ratelimit-reset'] ?? null;

        $resetDt = null;
        if ($resetTimestamp !== null) {
            try {
                $resetDt = (new \DateTimeImmutable())->setTimestamp((int) $resetTimestamp);
            } catch (\Exception) {
                // Ignore malformed timestamps
            }
        }

        return new self(
            limit: (int) $limit,
            remaining: (int) $remaining,
            reset: $resetDt,
        );
    }
}
