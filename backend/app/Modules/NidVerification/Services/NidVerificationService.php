<?php

namespace App\Modules\NidVerification\Services;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\AuditLog;
use App\Core\Models\User;
use App\Core\Services\AdministrativeScopeService;
use App\Modules\Beneficiary\Models\Household;
use App\Modules\Beneficiary\Models\HouseholdMember;
use App\Modules\Beneficiary\Models\HouseholdVerification;
use App\Modules\NidVerification\Contracts\NidVerificationServiceInterface;
use App\Modules\NidVerification\DTOs\NidVerificationRequestDto;
use App\Modules\NidVerification\DTOs\NidVerificationResultDto;
use App\Modules\NidVerification\DTOs\ProviderHealthDto;
use App\Modules\NidVerification\Exceptions\NidVerificationException;
use App\Modules\NidVerification\Jobs\ProcessNidVerificationJob;
use App\Modules\NidVerification\Models\NidVerification;
use App\Modules\NidVerification\Models\NidVerificationAttempt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Class NidVerificationService
 *
 * Orchestrates NID format validation, rate limiting, retries/timeouts,
 * optional Redis queue execution, attempt history, manual review overrides, and audit logging.
 */
class NidVerificationService
{
    public function __construct(
        protected NidVerificationServiceInterface $adapter,
        protected AdministrativeScopeService $scopeService,
    ) {}

    /**
     * Validate Bangladesh NID format (10-digit Smart NID, 13-digit, or 17-digit legacy).
     */
    public function isValidFormat(string $nid): bool
    {
        return (bool) preg_match('/^(?:\d{10}|\d{13}|\d{17})$/', trim($nid));
    }

    /**
     * Initiate a new National ID verification request (synchronous by default, or queued via Redis).
     *
     * @throws ValidationException
     * @throws AuthorizationException
     * @throws HttpResponseException
     */
    public function initiateVerification(
        string $rawNid,
        ?string $dob = null,
        ?string $nameEn = null,
        ?Household $household = null,
        ?HouseholdMember $member = null,
        ?string $administrativeUnitId = null,
        ?User $user = null,
        bool $async = false
    ): NidVerification {
        $cleanNid = trim($rawNid);

        if (!$this->isValidFormat($cleanNid)) {
            throw ValidationException::withMessages([
                'nid_number' => 'Invalid National ID format. Must be 10, 13, or 17 numeric digits.',
            ]);
        }

        $this->enforceRateLimit($user);

        // Resolve and authorize organizational scope (always ensure valid administrative_unit_id for FK integrity)
        $resolvedUnitId = null;

        if ($administrativeUnitId !== null && $administrativeUnitId !== '') {
            $unit = AdministrativeUnit::find($administrativeUnitId);
            if (!$unit) {
                throw ValidationException::withMessages([
                    'administrative_unit_id' => 'Selected administrative unit does not exist.',
                ]);
            }
            if ($user && !$this->scopeService->canAccessScope($user, $unit->ancestry_path)) {
                throw new AuthorizationException('Target administrative unit is outside your authorized jurisdiction.');
            }
            $resolvedUnitId = $unit->id;
        } elseif ($household?->administrative_unit_id) {
            $resolvedUnitId = $household->administrative_unit_id;
        } elseif ($user) {
            if ($user->administrative_unit_id && AdministrativeUnit::whereKey($user->administrative_unit_id)->exists()) {
                $resolvedUnitId = $user->administrative_unit_id;
            } else {
                $ancestryPath = $user->getAdministrativeScope()['ancestry_path'] ?? '/';
                $resolvedUnitId = AdministrativeUnit::where('ancestry_path', $ancestryPath)->value('id');
            }
        }

        $nidHash = Household::hashNid($cleanNid);
        $nidMasked = Household::maskNid($cleanNid);
        $reference = NidVerification::generateReference();

        $verification = NidVerification::create([
            'household_id' => $household?->id,
            'household_member_id' => $member?->id,
            'administrative_unit_id' => $resolvedUnitId,
            'nid_hash' => $nidHash,
            'nid_masked' => $nidMasked,
            'nid_encrypted_payload' => $async ? NidVerification::encryptTemporaryPayload($cleanNid) : null,
            'date_of_birth' => $dob,
            'submitted_name_en' => $nameEn,
            'verification_status' => NidVerification::STATUS_PENDING,
            'verification_reference' => $reference,
            'provider_code' => $this->adapter->getProviderCode(),
            'is_mock' => $this->adapter->isMockProvider(),
            'attempt_count' => 0,
            'requested_by_user_id' => $user?->id,
        ]);

        if ($async) {
            AuditLog::log(
                'NID_VERIFICATION_QUEUED',
                NidVerification::class,
                $verification->id,
                null,
                [
                    'verification_reference' => $reference,
                    'nid_masked' => $nidMasked,
                    'status' => NidVerification::STATUS_PENDING,
                    'is_mock' => $this->adapter->isMockProvider(),
                ],
                $user,
                $resolvedUnitId
            );

            ProcessNidVerificationJob::dispatch($verification->id, $user?->id);

            return $verification->fresh(['attempts', 'administrativeUnit', 'household', 'requester']);
        }

        return $this->executeVerification($verification, $cleanNid, $user);
    }

    /**
     * Execute provider verification with bounded retries, timeout handling, and attempt logging.
     */
    public function executeVerification(
        NidVerification $verification,
        string $rawNid,
        ?User $actor = null
    ): NidVerification {
        $cleanNid = trim($rawNid);

        $verification->update([
            'verification_status' => NidVerification::STATUS_PROCESSING,
        ]);

        $maxRetries = max(0, (int) config('services.nid.max_retries', 2));
        $retryDelayMs = max(0, (int) config('services.nid.retry_delay_ms', 250));
        $baseAttemptCount = (int) $verification->attempt_count;
        $finalResult = null;

        for ($i = 0; $i <= $maxRetries; $i++) {
            $attemptNumber = $baseAttemptCount + $i + 1;
            $attemptStart = microtime(true);

            try {
                $dto = new NidVerificationRequestDto(
                    nidNumber: $cleanNid,
                    dateOfBirth: $verification->date_of_birth?->format('Y-m-d'),
                    nameEn: $verification->submitted_name_en,
                    verificationReference: $verification->verification_reference,
                );

                $result = $this->adapter->verifyNid($dto);

                NidVerificationAttempt::create([
                    'nid_verification_id' => $verification->id,
                    'attempt_number' => $attemptNumber,
                    'provider_code' => $result->providerCode,
                    'is_mock' => $result->isMock,
                    'status' => $result->status,
                    'http_status' => $result->httpStatus,
                    'response_time_ms' => $result->responseTimeMs ?? max(1, (int) round((microtime(true) - $attemptStart) * 1000)),
                    'error_code' => $result->errorCode,
                    'error_message' => $result->errorMessage,
                    'attempted_by_user_id' => $actor?->id,
                    'attempted_at' => now(),
                ]);

                if (!$result->isValid && $result->errorCode !== null) {
                    Log::warning('NID verification returned non-verified outcome.', [
                        'verification_id' => $verification->id,
                        'reference' => $verification->verification_reference,
                        'nid_masked' => $verification->nid_masked,
                        'provider_code' => $result->providerCode,
                        'is_mock' => $result->isMock,
                        'attempt_number' => $attemptNumber,
                        'status' => $result->status,
                        'error_code' => $result->errorCode,
                    ]);
                }

                $finalResult = $result;
                break;
            } catch (NidVerificationException $e) {
                $elapsedMs = max(1, (int) round((microtime(true) - $attemptStart) * 1000));
                $attemptStatus = $e->errorCode === 'PROVIDER_TIMEOUT' ? 'TIMEOUT' : 'ERROR';

                NidVerificationAttempt::create([
                    'nid_verification_id' => $verification->id,
                    'attempt_number' => $attemptNumber,
                    'provider_code' => $this->adapter->getProviderCode(),
                    'is_mock' => $this->adapter->isMockProvider(),
                    'status' => $attemptStatus,
                    'http_status' => $e->httpStatus,
                    'response_time_ms' => $elapsedMs,
                    'error_code' => $e->errorCode,
                    'error_message' => $e->getMessage(),
                    'attempted_by_user_id' => $actor?->id,
                    'attempted_at' => now(),
                ]);

                Log::error('NID verification provider error.', [
                    'verification_id' => $verification->id,
                    'reference' => $verification->verification_reference,
                    'nid_masked' => $verification->nid_masked,
                    'provider_code' => $this->adapter->getProviderCode(),
                    'is_mock' => $this->adapter->isMockProvider(),
                    'attempt_number' => $attemptNumber,
                    'error_code' => $e->errorCode,
                    'http_status' => $e->httpStatus,
                    'retryable' => $e->retryable,
                ]);

                if ($e->retryable && $i < $maxRetries) {
                    if (!app()->environment('testing') && $retryDelayMs > 0) {
                        usleep($retryDelayMs * 1000);
                    }
                    continue;
                }

                $finalResult = new NidVerificationResultDto(
                    status: NidVerification::STATUS_FAILED,
                    isValid: false,
                    providerCode: $this->adapter->getProviderCode(),
                    isMock: $this->adapter->isMockProvider(),
                    httpStatus: $e->httpStatus,
                    responseTimeMs: $elapsedMs,
                    errorCode: $e->errorCode,
                    errorMessage: $e->getMessage(),
                    matchedFields: ['provider_error' => true],
                );
                break;
            }
        }

        $totalAttempts = $verification->attempts()->count();

        return DB::transaction(function () use ($verification, $finalResult, $totalAttempts, $actor) {
            $oldStatus = $verification->verification_status;
            $newStatus = $finalResult?->status ?? NidVerification::STATUS_FAILED;

            $verification->update([
                'verification_status' => $newStatus,
                'verification_token' => $finalResult?->verificationToken,
                'provider_code' => $finalResult?->providerCode ?? $this->adapter->getProviderCode(),
                'is_mock' => $finalResult?->isMock ?? $this->adapter->isMockProvider(),
                'response_payload_hash' => $finalResult?->payloadHash,
                'matched_fields' => $finalResult?->matchedFields,
                'attempt_count' => $totalAttempts,
                'last_error_code' => $finalResult?->errorCode,
                'last_error_message' => $finalResult?->errorMessage,
                'verified_by_user_id' => $actor?->id ?? $verification->requested_by_user_id,
                'verified_at' => $newStatus === NidVerification::STATUS_VERIFIED ? now() : null,
                'nid_encrypted_payload' => null, // Always wipe temporary payload after processing
            ]);

            if ($verification->household_id && $newStatus === NidVerification::STATUS_VERIFIED) {
                Household::whereKey($verification->household_id)->update([
                    'nid_verified' => true,
                ]);
            }

            AuditLog::log(
                'NID_VERIFICATION_' . $newStatus,
                NidVerification::class,
                $verification->id,
                ['status' => $oldStatus],
                [
                    'verification_reference' => $verification->verification_reference,
                    'nid_masked' => $verification->nid_masked,
                    'status' => $newStatus,
                    'provider_code' => $verification->provider_code,
                    'is_mock' => $verification->is_mock,
                    'attempt_count' => $totalAttempts,
                    'error_code' => $verification->last_error_code,
                ],
                $actor,
                $verification->administrative_unit_id
            );

            return $verification->fresh(['attempts', 'administrativeUnit', 'household', 'requester', 'verifier', 'reviewer']);
        });
    }

    /**
     * Retry a failed or manual-review-pending verification.
     *
     * @throws ValidationException
     * @throws HttpResponseException
     */
    public function retryVerification(NidVerification $verification, string $rawNid, User $user): NidVerification
    {
        $cleanNid = trim($rawNid);

        if (!$this->isValidFormat($cleanNid)) {
            throw ValidationException::withMessages([
                'nid_number' => 'Invalid National ID format. Must be 10, 13, or 17 numeric digits.',
            ]);
        }

        if (!hash_equals($verification->nid_hash, Household::hashNid($cleanNid))) {
            throw ValidationException::withMessages([
                'nid_number' => 'Provided National ID does not match the original verification record.',
            ]);
        }

        if (in_array($verification->verification_status, [NidVerification::STATUS_VERIFIED, NidVerification::STATUS_PROCESSING], true)) {
            throw ValidationException::withMessages([
                'verification_status' => 'Cannot retry a verification that is already verified or currently processing.',
            ]);
        }

        $this->enforceRateLimit($user);

        return $this->executeVerification($verification, $cleanNid, $user);
    }

    /**
     * Perform an audited manual review override by a designated officer (4-eye SoD enforced).
     *
     * @throws ValidationException
     */
    public function manualReview(
        NidVerification $verification,
        string $decisionStatus,
        string $reason,
        User $reviewer
    ): NidVerification {
        if ($verification->requested_by_user_id && $verification->requested_by_user_id === $reviewer->id) {
            throw ValidationException::withMessages([
                'authorization' => 'Separation of Duties violation: the officer who initiated this verification cannot manually override it.',
            ]);
        }

        if (!in_array($decisionStatus, [NidVerification::STATUS_VERIFIED, NidVerification::STATUS_FAILED], true)) {
            throw ValidationException::withMessages([
                'decision_status' => 'Manual review decision must be VERIFIED or FAILED.',
            ]);
        }

        return DB::transaction(function () use ($verification, $decisionStatus, $reason, $reviewer) {
            $previousStatus = $verification->verification_status;

            $verification->update([
                'previous_status' => $previousStatus,
                'verification_status' => $decisionStatus,
                'manual_override' => true,
                'override_reason' => trim($reason),
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
                'verified_by_user_id' => $decisionStatus === NidVerification::STATUS_VERIFIED ? $reviewer->id : $verification->verified_by_user_id,
                'verified_at' => $decisionStatus === NidVerification::STATUS_VERIFIED ? now() : null,
                'nid_encrypted_payload' => null,
            ]);

            if ($verification->household_id) {
                $household = Household::find($verification->household_id);
                if ($household) {
                    $household->update([
                        'nid_verified' => $decisionStatus === NidVerification::STATUS_VERIFIED,
                        'updated_by' => $reviewer->id,
                    ]);

                    HouseholdVerification::create([
                        'household_id' => $household->id,
                        'stage' => 'NID_CHECK',
                        'status' => $decisionStatus === NidVerification::STATUS_VERIFIED ? 'VERIFIED' : 'REJECTED',
                        'remarks' => 'Manual NID review (' . $verification->verification_reference . '): ' . trim($reason),
                        'verified_by' => $reviewer->id,
                        'verified_at' => now(),
                    ]);
                }
            }

            AuditLog::log(
                'NID_MANUAL_REVIEW_OVERRIDE',
                NidVerification::class,
                $verification->id,
                ['verification_status' => $previousStatus, 'manual_override' => false],
                [
                    'verification_reference' => $verification->verification_reference,
                    'nid_masked' => $verification->nid_masked,
                    'verification_status' => $decisionStatus,
                    'manual_override' => true,
                    'override_reason' => trim($reason),
                ],
                $reviewer,
                $verification->administrative_unit_id
            );

            return $verification->fresh(['attempts', 'administrativeUnit', 'household', 'requester', 'verifier', 'reviewer']);
        });
    }

    /**
     * Aggregate dashboard metrics and provider availability/error telemetry within officer scope.
     */
    public function getDashboardStats(User $user): array
    {
        $baseQuery = NidVerification::inJurisdiction($user);

        $totalRequests = (clone $baseQuery)->count();
        $verifiedCount = (clone $baseQuery)->where('verification_status', NidVerification::STATUS_VERIFIED)->count();
        $failedCount = (clone $baseQuery)->where('verification_status', NidVerification::STATUS_FAILED)->count();
        $pendingCount = (clone $baseQuery)->whereIn('verification_status', [
            NidVerification::STATUS_PENDING,
            NidVerification::STATUS_PROCESSING,
        ])->count();
        $manualReviewCount = (clone $baseQuery)->where('verification_status', NidVerification::STATUS_MANUAL_REVIEW_REQUIRED)->count();
        $manualOverrideCount = (clone $baseQuery)->where('manual_override', true)->count();
        $mockCount = (clone $baseQuery)->where('is_mock', true)->count();
        $officialCount = (clone $baseQuery)->where('is_mock', false)->count();

        $verificationIdsQuery = (clone $baseQuery)->select('id');

        $attemptBase = NidVerificationAttempt::whereIn('nid_verification_id', $verificationIdsQuery);
        $totalAttempts = (clone $attemptBase)->count();
        $errorAttempts = (clone $attemptBase)->whereIn('status', ['FAILED', 'TIMEOUT', 'ERROR'])->count();
        $avgResponseMs = (int) round((float) ((clone $attemptBase)->avg('response_time_ms') ?? 0));

        $recentErrors = (clone $attemptBase)
            ->with(['verification:id,verification_reference,nid_masked,verification_status'])
            ->whereIn('status', ['FAILED', 'TIMEOUT', 'ERROR', 'MANUAL_REVIEW_REQUIRED'])
            ->orderByDesc('attempted_at')
            ->limit(10)
            ->get()
            ->map(function (NidVerificationAttempt $attempt) {
                return [
                    'id' => $attempt->id,
                    'nid_verification_id' => $attempt->nid_verification_id,
                    'verification_reference' => $attempt->verification?->verification_reference,
                    'nid_masked' => $attempt->verification?->nid_masked,
                    'attempt_number' => $attempt->attempt_number,
                    'provider_code' => $attempt->provider_code,
                    'is_mock' => $attempt->is_mock,
                    'status' => $attempt->status,
                    'http_status' => $attempt->http_status,
                    'response_time_ms' => $attempt->response_time_ms,
                    'error_code' => $attempt->error_code,
                    'error_message' => $attempt->error_message,
                    'attempted_at' => $attempt->attempted_at?->toIso8601String(),
                ];
            })
            ->values();

        return [
            'summary' => [
                'total_requests' => $totalRequests,
                'verified_count' => $verifiedCount,
                'failed_count' => $failedCount,
                'pending_count' => $pendingCount,
                'manual_review_count' => $manualReviewCount,
                'manual_override_count' => $manualOverrideCount,
                'mock_verifications_count' => $mockCount,
                'official_verifications_count' => $officialCount,
            ],
            'provider_health' => $this->getProviderHealth()->toArray(),
            'telemetry' => [
                'total_attempts' => $totalAttempts,
                'error_attempts' => $errorAttempts,
                'success_rate_percent' => $totalRequests > 0 ? round(($verifiedCount / $totalRequests) * 100, 1) : 0.0,
                'avg_response_time_ms' => $avgResponseMs,
                'recent_errors' => $recentErrors,
            ],
        ];
    }

    /**
     * Inspect current NID provider adapter availability.
     */
    public function getProviderHealth(): ProviderHealthDto
    {
        return $this->adapter->getHealthStatus();
    }

    /**
     * Enforce per-user rate limit on verification submissions.
     *
     * @throws HttpResponseException
     */
    protected function enforceRateLimit(?User $user): void
    {
        $maxPerMinute = max(1, (int) config('services.nid.rate_limit_per_minute', 30));
        $key = 'nid-verify:' . ($user?->id ?? request()->ip() ?? 'system');

        if (RateLimiter::tooManyAttempts($key, $maxPerMinute)) {
            $retryAfter = RateLimiter::availableIn($key);

            throw new HttpResponseException(response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NID_RATE_LIMIT_EXCEEDED',
                    'message' => "NID verification rate limit exceeded. Please retry after {$retryAfter} seconds.",
                    'retry_after_seconds' => $retryAfter,
                ],
                'timestamp' => now()->toIso8601String(),
            ], 429));
        }

        RateLimiter::hit($key, 60);
    }
}
