<?php

declare(strict_types=1);

namespace VerifIP\Models;

/**
 * Response from a URL reputation check.
 */
final class URLResponse
{
    public function __construct(
        public string $requestId = '',
        public string $url = '',
        public int $riskScore = 0,
        public bool $isPhishing = false,
        public bool $isMalware = false,
        public string $safeBrowsingThreat = '',
        public bool $inPhishtank = false,
        public bool $spamhausDbl = false,
        public int $domainAgeDays = 0,
        public bool $sslValid = false,
        public string $sslIssuer = '',
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
            url: (string) ($data['url'] ?? ''),
            riskScore: (int) ($data['risk_score'] ?? 0),
            isPhishing: (bool) ($data['is_phishing'] ?? false),
            isMalware: (bool) ($data['is_malware'] ?? false),
            safeBrowsingThreat: (string) ($data['safe_browsing_threat'] ?? ''),
            inPhishtank: (bool) ($data['in_phishtank'] ?? false),
            spamhausDbl: (bool) ($data['spamhaus_dbl'] ?? false),
            domainAgeDays: (int) ($data['domain_age_days'] ?? 0),
            sslValid: (bool) ($data['ssl_valid'] ?? false),
            sslIssuer: (string) ($data['ssl_issuer'] ?? ''),
            signalBreakdown: (array) ($data['signal_breakdown'] ?? []),
            error: isset($data['error']) ? (string) $data['error'] : null,
        );
    }
}
