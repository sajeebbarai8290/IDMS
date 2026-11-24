<?php

namespace App\Modules\Beneficiary\Controllers\Api\V1;

use App\Modules\Beneficiary\Models\Household;
use App\Modules\Beneficiary\Requests\VerificationActionRequest;
use App\Modules\Beneficiary\Resources\HouseholdDetailResource;
use App\Modules\Beneficiary\Services\BeneficiaryVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class VerificationController extends Controller
{
    public function __construct(
        protected BeneficiaryVerificationService $verificationService
    ) {}

    /**
     * Submit an intermediate field verification or NID check step.
     */
    public function verifyStep(VerificationActionRequest $request, Household $household): JsonResponse
    {
        $user = $request->user();

        if (!$user->can('update', $household)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validated();

        $verification = $this->verificationService->recordStep(
            $household,
            $validated['stage'],
            $validated['status'],
            $validated['remarks'] ?? null,
            $validated['evidence_document_path'] ?? null,
            $user
        );

        return response()->json([
            'success' => true,
            'message' => 'Verification step recorded successfully.',
            'verification' => [
                'id' => $verification->id,
                'stage' => $verification->stage,
                'status' => $verification->status,
                'verified_at' => $verification->verified_at->toIso8601String(),
            ],
            'household' => new HouseholdDetailResource($household->fresh(['verifications'])),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Final committee vetting or Upazila PIO / DRRO approval/rejection.
     */
    public function finalApproval(Request $request, Household $household): JsonResponse
    {
        $request->validate([
            'action' => 'required|in:APPROVE,REJECT',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();
        $action = $request->input('action');
        $remarks = $request->input('remarks');

        $household = $this->verificationService->finalApproval($household, $action, $remarks, $user);

        return response()->json([
            'success' => true,
            'message' => $action === 'APPROVE' ? 'Beneficiary household approved for relief entitlement.' : 'Beneficiary household rejected.',
            'household' => new HouseholdDetailResource($household->fresh(['verifications'])),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
