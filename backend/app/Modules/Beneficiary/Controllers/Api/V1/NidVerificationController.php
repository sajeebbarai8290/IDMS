<?php

namespace App\Modules\Beneficiary\Controllers\Api\V1;

use App\Modules\Beneficiary\Models\Household;
use App\Modules\Beneficiary\Models\HouseholdMember;
use App\Modules\Beneficiary\Requests\VerifyNidRequest;
use App\Modules\Beneficiary\Resources\NidVerificationResource;
use App\Modules\Beneficiary\Services\NidVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class NidVerificationController extends Controller
{
    public function __construct(
        protected NidVerificationService $nidService
    ) {}

    /**
     * Submit an NID number for identity verification against Porichoy or Mock Adapter.
     */
    public function verify(VerifyNidRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $household = !empty($validated['household_id'])
            ? Household::find($validated['household_id'])
            : null;

        $member = !empty($validated['household_member_id'])
            ? HouseholdMember::find($validated['household_member_id'])
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
            'message' => 'National ID verified successfully.',
            'verification' => new NidVerificationResource($verification),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
