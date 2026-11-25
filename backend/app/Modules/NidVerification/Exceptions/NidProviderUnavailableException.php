<?php

namespace App\Modules\NidVerification\Exceptions;

/**
 * Class NidProviderUnavailableException
 *
 * Raised when the upstream NID verification gateway times out, is unreachable, or is unconfigured.
 */
class NidProviderUnavailableException extends NidVerificationException
{
    public function __construct(
        string $message = 'NID verification service is currently unavailable.',
        string $errorCode = 'PROVIDER_UNAVAILABLE',
        int $httpStatus = 503,
        bool $retryable = true,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $errorCode, $httpStatus, $retryable, $previous);
    }
}
