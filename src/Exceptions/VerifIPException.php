<?php

declare(strict_types=1);

namespace VerifIP\Exceptions;

/**
 * Base exception for all VerifIP API errors.
 */
class VerifIPException extends \RuntimeException
{
    public function __construct(
        string $message = '',
        public readonly int $statusCode = 0,
        public readonly string $errorCode = '',
        public readonly ?int $retryAfter = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }
}
