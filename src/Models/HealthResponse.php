<?php

declare(strict_types=1);

namespace VerifIP\Models;

/**
 * Response from the health check endpoint.
 */
final class HealthResponse
{
    public function __construct(
        public string $status = '',
        public string $version = '',
        public string $dataLoadedAt = '',
        public string $redis = '',
        public string $postgres = '',
        public int $uptimeSeconds = 0,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            status: (string) ($data['status'] ?? ''),
            version: (string) ($data['version'] ?? ''),
            dataLoadedAt: (string) ($data['data_loaded_at'] ?? ''),
            redis: (string) ($data['redis'] ?? ''),
            postgres: (string) ($data['postgres'] ?? ''),
            uptimeSeconds: (int) ($data['uptime_seconds'] ?? 0),
        );
    }
}
