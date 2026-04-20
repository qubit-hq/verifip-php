<?php

declare(strict_types=1);

namespace VerifIP\Models;

/**
 * Response from a batch IP check.
 */
final class BatchResponse
{
    /**
     * @param list<CheckResponse> $results
     */
    public function __construct(
        public array $results = [],
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $results = array_map(
            static fn(array $item): CheckResponse => CheckResponse::fromArray($item),
            $data['results'] ?? [],
        );

        return new self(results: $results);
    }
}
