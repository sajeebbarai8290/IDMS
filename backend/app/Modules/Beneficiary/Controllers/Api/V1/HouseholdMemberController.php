<?php

namespace App\Modules\Beneficiary\Controllers\Api\V1;

use App\Core\Models\AuditLog;
use App\Modules\Beneficiary\Models\Household;
use App\Modules\Beneficiary\Models\HouseholdMember;
use App\Modules\Beneficiary\Requests\StoreMemberRequest;
use App\Modules\Beneficiary\Resources\HouseholdMemberResource;
use App\Modules\Beneficiary\Services\VulnerabilityCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Crypt;

class HouseholdMemberController extends Controller
{
    public function __construct(
        protected VulnerabilityCalculationService $vulnerabilityService
    ) {}

    /**
     * Add a dependent family member to an existing household.
     */
    public function store(StoreMemberRequest $request, Household $household): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $rawNid = !empty($validated['nid']) ? trim($validated['nid']) : null;

        $member = HouseholdMember::create([
            'household_id' => $household->id,
            'full_name_en' => $validated['full_name_en'],
            'full_name_bn' => $validated['full_name_bn'] ?? null,
            'relationship_to_hoh' => $validated['relationship_to_hoh'],
            'gender' => $validated['gender'],
            'age' => $validated['age'] ?? null,
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'nid_hash' => $rawNid ? Household::hashNid($rawNid) : null,
            'nid_encrypted' => $rawNid ? Crypt::encryptString($rawNid) : null,
            'nid_masked' => $rawNid ? Household::maskNid($rawNid) : null,
            'has_disability' => !empty($validated['has_disability']),
            'disability_type' => $validated['disability_type'] ?? null,
            'is_pregnant_lactating' => !empty($validated['is_pregnant_lactating']),
            'occupation' => $validated['occupation'] ?? null,
        ]);

        // Update member count
        $household->increment('family_member_count');

        // Recalculate vulnerability score
        $this->vulnerabilityService->recalculateForModel($household);

        AuditLog::log(
            'HOUSEHOLD_MEMBER_ADDED',
            HouseholdMember::class,
            $member->id,
            null,
            ['member_name' => $member->full_name_en, 'household_code' => $household->household_code],
            $user,
            $household->administrative_unit_id
        );

        return response()->json([
            'success' => true,
            'message' => 'Family member registered successfully.',
            'member' => new HouseholdMemberResource($member),
            'updated_vulnerability_score' => (float) $household->fresh()->vulnerability_score,
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    /**
     * Remove a family member from a household.
     */
    public function destroy(Request $request, Household $household, HouseholdMember $member): JsonResponse
    {
        $user = $request->user();

        if (!$user->can('update', $household)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        if ($member->household_id !== $household->id) {
            return response()->json(['success' => false, 'message' => 'Member does not belong to this household.'], 422);
        }

        $oldValues = $member->toArray();
        $member->delete();

        // Decrement member count
        if ($household->family_member_count > 1) {
            $household->decrement('family_member_count');
        }

        // Recalculate vulnerability score
        $this->vulnerabilityService->recalculateForModel($household);

        AuditLog::log(
            'HOUSEHOLD_MEMBER_REMOVED',
            HouseholdMember::class,
            $member->id,
            $oldValues,
            null,
            $user,
            $household->administrative_unit_id
        );

        return response()->json([
            'success' => true,
            'message' => 'Family member removed successfully.',
            'updated_vulnerability_score' => (float) $household->fresh()->vulnerability_score,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
