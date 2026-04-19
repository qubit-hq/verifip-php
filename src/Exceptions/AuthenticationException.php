<?php

declare(strict_types=1);

namespace VerifIP\Exceptions;

/**
 * Raised on 401 (invalid API key) or 403 (key disabled).
 */
class AuthenticationException extends VerifIPException {}
