<?php

declare(strict_types=1);

namespace VerifIP\Models;

/**
 * Response from an email risk check.
 */
final class EmailResponse
{
    public function __construct(
        public string $requestId = '',
        public string $email = '',
        public int $riskScore = 0,
        public bool $validSyntax = false,
        public bool $mxFound = false,
        public bool $isDisposable = false,
        public bool $isFreeProvider = false,
        public bool $isRoleBased = false,
        public int $domainAgeDays = 0,
        public string $domain = '',
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
            email: (string) ($data['email'] ?? ''),
            riskScore: (int) ($data['risk_score'] ?? 0),
            validSyntax: (bool) ($data['valid_syntax'] ?? false),
            mxFound: (bool) ($data['mx_found'] ?? false),
            isDisposable: (bool) ($data['is_disposable'] ?? false),
            isFreeProvider: (bool) ($data['is_free_provider'] ?? false),
            isRoleBased: (bool) ($data['is_role_based'] ?? false),
            domainAgeDays: (int) ($data['domain_age_days'] ?? 0),
            domain: (string) ($data['domain'] ?? ''),
            signalBreakdown: (array) ($data['signal_breakdown'] ?? []),
            error: isset($data['error']) ? (string) $data['error'] : null,
        );
    }
}
