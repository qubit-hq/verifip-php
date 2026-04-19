<?php

declare(strict_types=1);

namespace VerifIP\Models;

/**
 * Response from a single IP check.
 */
final readonly class CheckResponse
{
    public function __construct(
        public string $requestId = '',
        public string $ip = '',
        public int $fraudScore = 0,
        public bool $isProxy = false,
        public bool $isVpn = false,
        public bool $isTor = false,
        public bool $isDatacenter = false,
        public string $countryCode = '',
        public string $countryName = '',
        public string $region = '',
        public string $city = '',
        public string $isp = '',
        public int $asn = 0,
        public string $connectionType = '',
        public string $hostname = '',
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
            ip: (string) ($data['ip'] ?? ''),
            fraudScore: (int) ($data['fraud_score'] ?? 0),
            isProxy: (bool) ($data['is_proxy'] ?? false),
            isVpn: (bool) ($data['is_vpn'] ?? false),
            isTor: (bool) ($data['is_tor'] ?? false),
            isDatacenter: (bool) ($data['is_datacenter'] ?? false),
            countryCode: (string) ($data['country_code'] ?? ''),
            countryName: (string) ($data['country_name'] ?? ''),
            region: (string) ($data['region'] ?? ''),
            city: (string) ($data['city'] ?? ''),
            isp: (string) ($data['isp'] ?? ''),
            asn: (int) ($data['asn'] ?? 0),
            connectionType: (string) ($data['connection_type'] ?? ''),
            hostname: (string) ($data['hostname'] ?? ''),
            signalBreakdown: (array) ($data['signal_breakdown'] ?? []),
            error: isset($data['error']) ? (string) $data['error'] : null,
        );
    }
}
