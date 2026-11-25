<?php

namespace App\Modules\NidVerification\Adapters;

use App\Modules\NidVerification\Contracts\NidVerificationServiceInterface;
use App\Modules\NidVerification\DTOs\NidVerificationRequestDto;
use App\Modules\NidVerification\DTOs\NidVerificationResultDto;
use App\Modules\NidVerification\DTOs\ProviderHealthDto;
use App\Modules\NidVerification\Exceptions\NidProviderUnavailableException;
use App\Modules\NidVerification\Models\NidVerification;
use Illuminate\Support\Str;

/**
 * Class MockNidAdapter
 *
 * Local development and automated testing adapter.
 * Strictly separated from production government integration and always marks records with is_mock = true.
 */
class MockNidAdapter implements NidVerificationServiceInterface
{
    public const PROVIDER_CODE = 'MOCK_ADAPTER';

    public function verifyNid(NidVerificationRequestDto $requestDto): NidVerificationResultDto
    {
        $startTime = microtime(true);

        // Simulate realistic non-blocking latency in local dev (zero delay during automated tests)
        if (!app()->environment('testing')) {
            usleep(random_int(50000, 120000));
        }

        $nid = preg_replace('/[^0-9]/', '', $requestDto->nidNumber);
        $elapsedMs = fn (): int => max(1, (int) round((microtime(true) - $startTime) * 1000));

        if (!in_array(strlen($nid), [10, 13, 17], true)) {
            return new NidVerificationResultDto(
                status: NidVerification::STATUS_FAILED,
                isValid: false,
                providerCode: self::PROVIDER_CODE,
                isMock: true,
                httpStatus: 422,
                responseTimeMs: $elapsedMs(),
                errorCode: 'INVALID_NID_FORMAT',
                errorMessage: 'NID must be 10, 13, or 17 numeric digits.',
                matchedFields: ['nid_format_valid' => false],
            );
        }

        // Deterministic negative fixture: NID not found
        if ($nid === '0000000000' || str_ends_with($nid, '0000000')) {
            return new NidVerificationResultDto(
                status: NidVerification::STATUS_FAILED,
                isValid: false,
                providerCode: self::PROVIDER_CODE,
                isMock: true,
                httpStatus: 404,
                responseTimeMs: $elapsedMs(),
                errorCode: 'NID_NOT_FOUND',
                errorMessage: 'Simulated mock registry did not find this NID record.',
                matchedFields: ['nid_format_valid' => true, 'nid_found' => false],
                payloadHash: hash('sha256', self::PROVIDER_CODE . ':NOT_FOUND:' . $requestDto->verificationReference),
            );
        }

        // Deterministic negative fixture: Deceased citizen
        if ($nid === '9999999999') {
            return new NidVerificationResultDto(
                status: NidVerification::STATUS_FAILED,
                isValid: false,
                providerCode: self::PROVIDER_CODE,
                isMock: true,
                httpStatus: 200,
                responseTimeMs: $elapsedMs(),
                errorCode: 'CITIZEN_DECEASED',
                errorMessage: 'Simulated mock registry indicates citizen is deceased.',
                matchedFields: ['nid_format_valid' => true, 'nid_found' => true, 'alive_status' => false],
                payloadHash: hash('sha256', self::PROVIDER_CODE . ':DECEASED:' . $requestDto->verificationReference),
            );
        }

        // Deterministic manual review fixture: Demographic mismatch
        if (
            $nid === '8888888888'
            || $requestDto->dateOfBirth === '1900-01-01'
            || ($requestDto->nameEn && str_contains(strtoupper($requestDto->nameEn), 'MISMATCH'))
        ) {
            return new NidVerificationResultDto(
                status: NidVerification::STATUS_MANUAL_REVIEW_REQUIRED,
                isValid: false,
                providerCode: self::PROVIDER_CODE,
                isMock: true,
                httpStatus: 200,
                responseTimeMs: $elapsedMs(),
                errorCode: 'DEMOGRAPHIC_MISMATCH',
                errorMessage: 'Simulated demographic mismatch requires designated officer manual review.',
                matchedFields: [
                    'nid_format_valid' => true,
                    'nid_found' => true,
                    'dob_matched' => false,
                    'name_matched' => false,
                ],
                payloadHash: hash('sha256', self::PROVIDER_CODE . ':MISMATCH:' . $requestDto->verificationReference),
            );
        }

        // Deterministic transient error fixture: Timeout / gateway unavailable (triggers retry loop)
        if (
            $nid === '7777777777'
            || ($requestDto->nameEn && str_contains(strtoupper($requestDto->nameEn), 'TIMEOUT'))
        ) {
            throw new NidProviderUnavailableException(
                message: 'Simulated upstream NID gateway timeout.',
                errorCode: 'PROVIDER_TIMEOUT',
                httpStatus: 504,
                retryable: true,
            );
        }

        $token = 'MOCK-NID-' . strtoupper(Str::random(12));
        $matchedFields = [
            'nid_format_valid' => true,
            'nid_found' => true,
            'dob_matched' => $requestDto->dateOfBirth !== null,
            'name_matched' => $requestDto->nameEn !== null,
            'simulated_mode' => true,
        ];

        return new NidVerificationResultDto(
            status: NidVerification::STATUS_VERIFIED,
            isValid: true,
            providerCode: self::PROVIDER_CODE,
            isMock: true,
            verificationToken: $token,
            httpStatus: 200,
            responseTimeMs: $elapsedMs(),
            matchedFields: $matchedFields,
            payloadHash: hash('sha256', json_encode([
                'provider' => self::PROVIDER_CODE,
                'status' => NidVerification::STATUS_VERIFIED,
                'ref' => $requestDto->verificationReference,
                'token' => $token,
            ])),
        );
    }

    public function getHealthStatus(): ProviderHealthDto
    {
        return new ProviderHealthDto(
            providerCode: self::PROVIDER_CODE,
            isMock: true,
            available: true,
            status: 'MOCK_AVAILABLE',
            message: 'Development Mock NID Adapter active. Results are simulated and NOT genuine government verification.',
            checkedAt: now()->toIso8601String(),
        );
    }

    public function isProviderAvailable(): bool
    {
        return true;
    }

    public function getProviderCode(): string
    {
        return self::PROVIDER_CODE;
    }

    public function isMockProvider(): bool
    {
        return true;
    }
}
