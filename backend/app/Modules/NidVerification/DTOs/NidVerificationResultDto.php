<?php

namespace App\Modules\NidVerification\DTOs;

/**
 * Class NidVerificationResultDto
 *
 * Normalized, minimized result returned by NID verification adapters.
 * Never carries raw upstream government payloads or sensitive credentials.
 */
final class NidVerificationResultDto
{
    public function __construct(
        public readonly string $status,
        public readonly bool $isValid,
        public readonly string $providerCode,
        public readonly bool $isMock = true,
        public readonly ?string $verificationToken = null,
        public readonly ?int $httpStatus = 200,
        public readonly ?int $responseTimeMs = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
        public readonly array $matchedFields = [],
        public readonly ?string $payloadHash = null,
    ) {}
}
