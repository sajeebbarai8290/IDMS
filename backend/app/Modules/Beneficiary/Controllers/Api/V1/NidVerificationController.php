<?php

namespace App\Modules\Beneficiary\Controllers\Api\V1;

use App\Modules\Beneficiary\Models\Household;
use App\Modules\Beneficiary\Models\HouseholdMember;
use App\Modules\Beneficiary\Requests\VerifyNidRequest;
use App\Modules\Beneficiary\Services\NidVerificationService;
use App\Modules\NidVerification\Models\NidVerification;
use App\Modules\NidVerification\Resources\NidVerificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class NidVerificationController extends Controller
{
    public function __construct(
        protected NidVerificationService $nidService
    ) {}

    /**
     * Backward-compatible alias for /api/v1/beneficiary/verify-nid.
     * Delegates to App\Modules\NidVerification.
     */
    public function verify(VerifyNidRequest $request): JsonResponse
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

        $verification = $this->nidService->verify(
            $validated['nid_number'],
            $validated['date_of_birth'] ?? null,
            $household,
            $member,
            $user
        );

        return response()->json([
            'success' => true,
            'message' => $verification->verification_status === NidVerification::STATUS_VERIFIED
                ? 'National ID verified successfully.'
                : 'National ID verification processed with status: ' . $verification->verification_status,
            'verification' => new NidVerificationResource($verification),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
