<?php

namespace App\Modules\NidVerification\Adapters;

use App\Modules\NidVerification\Contracts\NidVerificationServiceInterface;
use App\Modules\NidVerification\DTOs\NidVerificationRequestDto;
use App\Modules\NidVerification\DTOs\NidVerificationResultDto;
use App\Modules\NidVerification\DTOs\ProviderHealthDto;
use App\Modules\NidVerification\Exceptions\NidProviderUnavailableException;
use App\Modules\NidVerification\Exceptions\NidVerificationException;
use App\Modules\NidVerification\Models\NidVerification;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Class AuthorizedGovernmentNidAdapter
 *
 * Production integration adapter for the officially authorized Bangladesh NID verification service.
 * Fails closed until official API endpoint documentation, credentials, and approval are configured.
 */
class AuthorizedGovernmentNidAdapter implements NidVerificationServiceInterface
{
    public const PROVIDER_CODE = 'GOV_NID_API';

    public function verifyNid(NidVerificationRequestDto $requestDto): NidVerificationResultDto
    {
        $config = config('services.nid', []);
        $baseUrl = trim((string) ($config['base_url'] ?? ''));
        $verifyPath = trim((string) ($config['verify_path'] ?? ''));
        $apiKey = trim((string) ($config['api_key'] ?? ''));

        if ($baseUrl === '' || $verifyPath === '' || $apiKey === '') {
            throw new NidProviderUnavailableException(
                message: 'Official Bangladesh NID verification API is not yet configured or authorized.',
                errorCode: 'PROVIDER_NOT_CONFIGURED',
                httpStatus: 503,
                retryable: false,
            );
        }

        $timeoutSeconds = max(1, (int) ($config['timeout_seconds'] ?? 5));
        $startTime = microtime(true);
        $endpoint = rtrim($baseUrl, '/') . '/' . ltrim($verifyPath, '/');

        try {
            $pendingRequest = Http::timeout($timeoutSeconds)
                ->acceptJson()
                ->withHeaders([
                    'X-API-Key' => $apiKey,
                ]);

            if (!empty($config['ssl_cert_path']) && !empty($config['ssl_key_path'])) {
                $pendingRequest = $pendingRequest->withOptions([
                    'cert' => $config['ssl_cert_path'],
                    'ssl_key' => $config['ssl_key_path'],
                ]);
            }

            $response = $pendingRequest->post($endpoint, array_filter([
                'nid' => $requestDto->nidNumber,
                'dob' => $requestDto->dateOfBirth,
                'name_en' => $requestDto->nameEn,
                'reference_id' => $requestDto->verificationReference,
            ], fn ($value) => $value !== null && $value !== ''));
        } catch (ConnectionException $e) {
            throw new NidProviderUnavailableException(
                message: 'Timeout or connection failure reaching authorized NID verification gateway.',
                errorCode: 'PROVIDER_TIMEOUT',
                httpStatus: 504,
                retryable: true,
                previous: $e,
            );
        }

        $elapsedMs = max(1, (int) round((microtime(true) - $startTime) * 1000));
        $httpStatus = $response->status();

        if ($httpStatus >= 500 || $httpStatus === 429) {
            throw new NidProviderUnavailableException(
                message: 'Authorized NID verification gateway returned temporary error.',
                errorCode: $httpStatus === 429 ? 'UPSTREAM_RATE_LIMITED' : 'UPSTREAM_SERVER_ERROR',
                httpStatus: $httpStatus,
                retryable: true,
            );
        }

        if ($httpStatus === 401 || $httpStatus === 403) {
            throw new NidVerificationException(
                message: 'Authorized NID verification gateway rejected credentials.',
                errorCode: 'PROVIDER_AUTH_FAILED',
                httpStatus: $httpStatus,
                retryable: false,
            );
        }

        $body = $response->json() ?? [];
        $verified = (bool) ($body['verified'] ?? false);
        $requiresReview = (bool) ($body['manual_review_required'] ?? false);

        $status = $verified
            ? NidVerification::STATUS_VERIFIED
            : ($requiresReview ? NidVerification::STATUS_MANUAL_REVIEW_REQUIRED : NidVerification::STATUS_FAILED);

        return new NidVerificationResultDto(
            status: $status,
            isValid: $verified,
            providerCode: self::PROVIDER_CODE,
            isMock: false,
            verificationToken: $body['verification_reference'] ?? $body['transaction_id'] ?? null,
            httpStatus: $httpStatus,
            responseTimeMs: $elapsedMs,
            errorCode: $verified ? null : ($body['error_code'] ?? 'VERIFICATION_FAILED'),
            errorMessage: $verified ? null : ($body['message'] ?? 'Citizen credentials could not be verified.'),
            matchedFields: [
                'nid_found' => (bool) ($body['nid_found'] ?? $verified),
                'dob_matched' => (bool) ($body['dob_matched'] ?? $verified),
                'name_matched' => (bool) ($body['name_matched'] ?? false),
            ],
            payloadHash: hash('sha256', (string) $response->body()),
        );
    }

    public function getHealthStatus(): ProviderHealthDto
    {
        $configured = $this->isProviderAvailable();

        return new ProviderHealthDto(
            providerCode: self::PROVIDER_CODE,
            isMock: false,
            available: $configured,
            status: $configured ? 'CONFIGURED' : 'NOT_CONFIGURED',
            message: $configured
                ? 'Authorized Government NID gateway configuration detected.'
                : 'Awaiting official MoDMR / Election Commission NID API documentation, authorization, and credentials.',
            checkedAt: now()->toIso8601String(),
        );
    }

    public function isProviderAvailable(): bool
    {
        $config = config('services.nid', []);

        return trim((string) ($config['base_url'] ?? '')) !== ''
            && trim((string) ($config['verify_path'] ?? '')) !== ''
            && trim((string) ($config['api_key'] ?? '')) !== '';
    }

    public function getProviderCode(): string
    {
        return self::PROVIDER_CODE;
    }

    public function isMockProvider(): bool
    {
        return false;
    }
}
