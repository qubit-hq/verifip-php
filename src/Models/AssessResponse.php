<?php

declare(strict_types=1);

namespace VerifIP\Models;

/**
 * Response from a unified risk assessment.
 */
final class AssessResponse
{
    public function __construct(
        public string $requestId = '',
        public int $overallRisk = 0,
        public ?CheckResponse $ip = null,
        public ?EmailResponse $email = null,
        public ?PhoneResponse $phone = null,
        public ?URLResponse $url = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            requestId: (string) ($data['request_id'] ?? ''),
            overallRisk: (int) ($data['overall_risk'] ?? 0),
            ip: isset($data['ip']) ? CheckResponse::fromArray($data['ip']) : null,
            email: isset($data['email']) ? EmailResponse::fromArray($data['email']) : null,
            phone: isset($data['phone']) ? PhoneResponse::fromArray($data['phone']) : null,
            url: isset($data['url']) ? URLResponse::fromArray($data['url']) : null,
        );
    }
}
