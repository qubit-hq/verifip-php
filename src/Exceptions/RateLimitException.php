<?php

declare(strict_types=1);

namespace VerifIP\Exceptions;

/**
 * Raised on 429 (rate limit exceeded). Check retryAfter for wait time.
 */
class RateLimitException extends VerifIPException {}
