<?php

namespace App\Modules\NidVerification\Contracts;

use App\Modules\NidVerification\DTOs\NidVerificationRequestDto;
use App\Modules\NidVerification\DTOs\NidVerificationResultDto;
use App\Modules\NidVerification\DTOs\ProviderHealthDto;
use App\Modules\NidVerification\Exceptions\NidVerificationException;

/**
 * Interface NidVerificationServiceInterface
 *
 * Contract decoupling IDMS verification workflows from the underlying NID provider implementation.
 */
interface NidVerificationServiceInterface
{
    /**
     * Verify a citizen's National ID credentials against the configured provider.
     *
     * @throws NidVerificationException
     */
    public function verifyNid(NidVerificationRequestDto $requestDto): NidVerificationResultDto;

    /**
     * Return provider availability and operational mode metadata.
     */
    public function getHealthStatus(): ProviderHealthDto;

    /**
     * Quick availability check for the configured provider.
     */
    public function isProviderAvailable(): bool;

    /**
     * Identifier code for the provider (e.g. MOCK_ADAPTER, GOV_NID_API).
     */
    public function getProviderCode(): string;

    /**
     * Whether this adapter is a development/testing mock provider.
     */
    public function isMockProvider(): bool;
}
