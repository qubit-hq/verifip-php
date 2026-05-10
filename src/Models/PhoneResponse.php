<?php

declare(strict_types=1);

namespace VerifIP\Models;

/**
 * Response from a phone number risk check.
 */
final class PhoneResponse
{
    public function __construct(
        public string $requestId = '',
        public string $phone = '',
        public int $riskScore = 0,
        public bool $validFormat = false,
        public string $lineType = '',
        public string $carrier = '',
        public string $countryCode = '',
        public bool $isVoip = false,
        /** @var array<string, int> */
        public array $signalBreakdown = [],
        public ?string $error = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            requestId: (string) ($data['request_id'] ?? ''),
            phone: (string) ($data['phone'] ?? ''),
            riskScore: (int) ($data['risk_score'] ?? 0),
            validFormat: (bool) ($data['valid_format'] ?? false),
            lineType: (string) ($data['line_type'] ?? ''),
            carrier: (string) ($data['carrier'] ?? ''),
            countryCode: (string) ($data['country_code'] ?? ''),
            isVoip: (bool) ($data['is_voip'] ?? false),
            signalBreakdown: (array) ($data['signal_breakdown'] ?? []),
            error: isset($data['error']) ? (string) $data['error'] : null,
        );
    }
}
