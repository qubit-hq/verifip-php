<?php

declare(strict_types=1);

namespace VerifIP\Models;

/**
 * Response from a WHOIS lookup.
 */
final class WHOISResponse
{
    public function __construct(
        public string $requestId = '',
        public string $ip = '',
        public string $networkCidr = '',
        public string $networkName = '',
        public string $orgName = '',
        public string $abuseContact = '',
        public string $rir = '',
        public string $allocationDate = '',
        public string $countryCode = '',
        public int $asn = 0,
        public string $asnOrg = '',
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            requestId: (string) ($data['request_id'] ?? ''),
            ip: (string) ($data['ip'] ?? ''),
            networkCidr: (string) ($data['network_cidr'] ?? ''),
            networkName: (string) ($data['network_name'] ?? ''),
            orgName: (string) ($data['org_name'] ?? ''),
            abuseContact: (string) ($data['abuse_contact'] ?? ''),
            rir: (string) ($data['rir'] ?? ''),
            allocationDate: (string) ($data['allocation_date'] ?? ''),
            countryCode: (string) ($data['country_code'] ?? ''),
            asn: (int) ($data['asn'] ?? 0),
            asnOrg: (string) ($data['asn_org'] ?? ''),
        );
    }
}
