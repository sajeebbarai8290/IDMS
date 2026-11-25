<?php

namespace App\Modules\NidVerification\Controllers\Api\V1;

use App\Modules\Beneficiary\Models\Household;
use App\Modules\Beneficiary\Models\HouseholdMember;
use App\Modules\NidVerification\Models\NidVerification;
use App\Modules\NidVerification\Requests\ManualReviewNidRequest;
use App\Modules\NidVerification\Requests\RetryNidVerificationRequest;
use App\Modules\NidVerification\Requests\StoreNidVerificationRequest;
use App\Modules\NidVerification\Resources\NidVerificationResource;
use App\Modules\NidVerification\Services\NidVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

/**
 * Class NidVerificationController
 *
 * Module 02 REST API controller (/api/v1/nid/*).
 */
class NidVerificationController extends Controller
{
    public function __construct(
        protected NidVerificationService $nidService
    ) {}

    /**
     * NID Verification Dashboard metrics and provider telemetry.
     */
    public function dashboard(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', NidVerification::class);

        $stats = $this->nidService->getDashboardStats($request->user());

        return response()->json([
            'success' => true,
            'data' => $stats,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Check live provider adapter availability and mode (mock vs official).
     */
    public function providerHealth(): JsonResponse
    {
        Gate::authorize('viewProviderHealth', NidVerification::class);

        return response()->json([
            'success' => true,
            'provider' => $this->nidService->getProviderHealth()->toArray(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Paginated, scoped, searchable, and filterable NID verification history.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', NidVerification::class);

        $user = $request->user();

        $query = NidVerification::with([
            'household:id,household_code,hoh_name_en,hoh_name_bn,administrative_unit_id',
            'administrativeUnit:id,code,name_en,name_bn,tier_level',
            'requester:id,full_name_en',
            'verifier:id,full_name_en',
            'reviewer:id,full_name_en',
        ])->inJurisdiction($user);

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('verification_reference', 'like', "%{$search}%")
                    ->orWhere('nid_masked', 'like', "%{$search}%")
                    ->orWhere('submitted_name_en', 'like', "%{$search}%")
                    ->orWhere('last_error_code', 'like', "%{$search}%");

                if (preg_match('/^\d{10,17}$/', $search)) {
                    $q->orWhere('nid_hash', Household::hashNid($search));
                }
            });
        }

        if ($status = $request->query('verification_status')) {
            $query->where('verification_status', strtoupper((string) $status));
        }

        if ($provider = $request->query('provider_code')) {
            $query->where('provider_code', strtoupper((string) $provider));
        }

        if ($request->has('is_mock') && $request->query('is_mock') !== '') {
            $query->where('is_mock', $request->boolean('is_mock'));
        }

        if ($request->has('manual_override') && $request->query('manual_override') !== '') {
            $query->where('manual_override', $request->boolean('manual_override'));
        }

        if ($adminUnitId = $request->query('administrative_unit_id')) {
            $query->where('administrative_unit_id', $adminUnitId);
        }

        if ($dateFrom = $request->query('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->query('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $sortBy = (string) $request->query('sort_by', 'created_at');
        $sortOrder = strtolower((string) $request->query('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['created_at', 'verified_at', 'verification_status', 'attempt_count'];

        $query->orderBy(
            in_array($sortBy, $allowedSorts, true) ? $sortBy : 'created_at',
            $sortOrder
        );

        $perPage = min(100, max(5, (int) $request->query('per_page', 15)));
        $verifications = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => NidVerificationResource::collection($verifications->items()),
            'pagination' => [
                'current_page' => $verifications->currentPage(),
                'per_page' => $verifications->perPage(),
                'total' => $verifications->total(),
                'last_page' => $verifications->lastPage(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Initiate an NID verification request (sync by default, or queued via Redis when async=true).
     */
    public function store(StoreNidVerificationRequest $request): JsonResponse
    {
        Gate::authorize('initiate', NidVerification::class);

        $user = $request->user();
        $validated = $request->validated();

        $household = !empty($validated['household_id'])
            ? Household::findOrFail($validated['household_id'])
            : null;

        if ($household) {
            Gate::authorize('view', $household);
        }

        $member = !empty($validated['household_member_id'])
            ? HouseholdMember::findOrFail($validated['household_member_id'])
            : null;

        if ($member && !$household && $member->household) {
            Gate::authorize('view', $member->household);
            $household = $member->household;
        }

        $async = array_key_exists('async', $validated)
            ? (bool) $validated['async']
            : (bool) config('services.nid.async_by_default', false);

        $verification = $this->nidService->initiateVerification(
            rawNid: $validated['nid_number'],
            dob: $validated['date_of_birth'] ?? null,
            nameEn: $validated['submitted_name_en'] ?? null,
            household: $household,
            member: $member,
            administrativeUnitId: $validated['administrative_unit_id'] ?? null,
            user: $user,
            async: $async,
        );

        $statusCode = $verification->verification_status === NidVerification::STATUS_PENDING ? 202 : 201;

        return response()->json([
            'success' => true,
            'message' => $verification->verification_status === NidVerification::STATUS_PENDING
                ? 'NID verification queued for background processing.'
                : 'NID verification request processed.',
            'verification' => new NidVerificationResource($verification),
            'timestamp' => now()->toIso8601String(),
        ], $statusCode);
    }

    /**
     * View full details and provider attempt history of a single verification record.
     */
    public function show(NidVerification $verification): JsonResponse
    {
        Gate::authorize('view', $verification);

        $verification->load([
            'attempts.attemptedBy:id,full_name_en',
            'administrativeUnit:id,code,name_en,name_bn,tier_level',
            'household:id,household_code,hoh_name_en,hoh_name_bn,administrative_unit_id',
            'requester:id,full_name_en',
            'verifier:id,full_name_en',
            'reviewer:id,full_name_en',
        ]);

        return response()->json([
            'success' => true,
            'verification' => new NidVerificationResource($verification),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Retry a failed or manual-review-pending verification request.
     */
    public function retry(RetryNidVerificationRequest $request, NidVerification $verification): JsonResponse
    {
        Gate::authorize('retry', $verification);

        $updated = $this->nidService->retryVerification(
            verification: $verification,
            rawNid: $request->validated('nid_number'),
            user: $request->user(),
        );

        return response()->json([
            'success' => true,
            'message' => 'NID verification retry processed.',
            'verification' => new NidVerificationResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Perform an audited manual review override (4-eye SoD enforced).
     */
    public function manualReview(ManualReviewNidRequest $request, NidVerification $verification): JsonResponse
    {
        Gate::authorize('manualReview', $verification);

        $validated = $request->validated();

        $updated = $this->nidService->manualReview(
            verification: $verification,
            decisionStatus: $validated['decision_status'],
            reason: $validated['override_reason'],
            reviewer: $request->user(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Manual review decision recorded successfully.',
            'verification' => new NidVerificationResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
