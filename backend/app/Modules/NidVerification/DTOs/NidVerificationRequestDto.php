<?php

namespace App\Modules\NidVerification\DTOs;

/**
 * Class NidVerificationRequestDto
 *
 * Encapsulates an incoming National ID verification request without exposing raw NID in debug output.
 */
final class NidVerificationRequestDto
{
    public function __construct(
        public readonly string $nidNumber,
        public readonly ?string $dateOfBirth = null,
        public readonly ?string $nameEn = null,
        public readonly ?string $nameBn = null,
        public readonly ?string $verificationReference = null,
    ) {}

    /**
     * Prevent accidental leakage of raw NID in logs or debug dumps.
     */
    public function __debugInfo(): array
    {
        $len = strlen($this->nidNumber);
        $masked = $len > 4
            ? substr($this->nidNumber, 0, 2) . str_repeat('*', $len - 4) . substr($this->nidNumber, -2)
            : '****';

        return [
            'nidNumber' => $masked,
            'dateOfBirth' => $this->dateOfBirth,
            'nameEn' => $this->nameEn,
            'verificationReference' => $this->verificationReference,
        ];
    }
}
