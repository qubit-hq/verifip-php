<?php

declare(strict_types=1);

namespace VerifIP\Models;

/**
 * Response from a fraud report submission.
 */
final class ReportResponse
{
    public function __construct(
        public string $requestId = '',
        public string $status = '',
        public string $message = '',
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            requestId: (string) ($data['request_id'] ?? ''),
            status: (string) ($data['status'] ?? ''),
            message: (string) ($data['message'] ?? ''),
        );
    }
}
