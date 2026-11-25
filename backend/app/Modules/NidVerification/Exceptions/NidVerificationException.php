<?php

namespace App\Modules\NidVerification\Exceptions;

use RuntimeException;

/**
 * Class NidVerificationException
 *
 * Base exception for NID gateway integration errors. Messages must never contain raw NID or credentials.
 */
class NidVerificationException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'NID_PROVIDER_ERROR',
        public readonly int $httpStatus = 502,
        public readonly bool $retryable = false,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
