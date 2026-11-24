<?php

namespace App\Modules\Beneficiary\Services;

use App\Core\Models\AuditLog;
use App\Core\Models\User;
use App\Modules\Beneficiary\Models\Household;
use App\Modules\Beneficiary\Models\HouseholdVerification;
use Illuminate\Validation\ValidationException;

/**
 * Class BeneficiaryVerificationService
 *
 * Implements the 4-Stage Verification and Approval State Machine.
 * Enforces separation of duties: Enumerator surveys; Upazila PIO / DRRO approves.
 *
 * @package App\Modules\Beneficiary\Services
 */
class BeneficiaryVerificationService
{
    /**
     * Record a verification step for a household.
     *
     * @param Household $household
     * @param string $stage 'NID_CHECK' | 'ADDRESS_CHECK' | 'FIELD_SURVEY' | 'COMMITTEE_APPROVAL'
     * @param string $status 'VERIFIED' | 'REJECTED'
     * @param string|null $remarks
     * @param string|null $evidenceDoc
     * @param User $user
     * @return HouseholdVerification
     */
    public function recordStep(
        Household $household,
        string $stage,
        string $status,
        ?string $remarks,
        ?string $evidenceDoc,
        User $user
    ): HouseholdVerification {
        $oldState = $household->toArray();

        // Create verification history step
        $verification = HouseholdVerification::create([
            'household_id' => $household->id,
            'stage' => $stage,
            'status' => $status,
            'remarks' => $remarks,
            'evidence_document_path' => $evidenceDoc,
            'verified_by' => $user->id,
            'verified_at' => now(),
        ]);

        // Update specific boolean indicators on Household
        if ($status === 'VERIFIED') {
            switch ($stage) {
                case 'NID_CHECK':
                    $household->nid_verified = true;
                    break;
                case 'ADDRESS_CHECK':
                    $household->address_verified = true;
                    break;
                case 'FIELD_SURVEY':
                    $household->field_verified = true;
                    $household->verification_status = 'FIELD_VERIFIED';
                    break;
            }
        } elseif ($status === 'REJECTED') {
            $household->verification_status = 'REJECTED';
        }

        $household->save();

        AuditLog::log(
            'BENEFICIARY_VERIFICATION_STEP',
            Household::class,
            $household->id,
            $oldState,
            $household->toArray(),
            $user,
            $household->administrative_unit_id
        );

        return $verification;
    }

    /**
     * Final statutory approval by Upazila PIO or District DRRO.
     *
     * @param Household $household
     * @param string $action 'APPROVE' | 'REJECT'
     * @param string|null $remarks
     * @param User $user
     * @return Household
     * @throws ValidationException
     */
    public function finalApproval(Household $household, string $action, ?string $remarks, User $user): Household
    {
        // Invariant: Field Enumerator cannot give final approval
        if ($user->hasRole('ROLE_FIELD_ENUMERATOR') && !$user->isNationalScope()) {
            throw ValidationException::withMessages([
                'authorization' => 'Field Enumerators are prohibited from granting final relief entitlement approvals.',
            ]);
        }

        $oldState = $household->toArray();

        if ($action === 'APPROVE') {
            $household->verification_status = 'APPROVED';
        } else {
            $household->verification_status = 'REJECTED';
        }

        $household->updated_by = $user->id;
        $household->save();

        // Record verification history
        HouseholdVerification::create([
            'household_id' => $household->id,
            'stage' => 'COMMITTEE_APPROVAL',
            'status' => $action === 'APPROVE' ? 'VERIFIED' : 'REJECTED',
            'remarks' => $remarks,
            'verified_by' => $user->id,
            'verified_at' => now(),
        ]);

        AuditLog::log(
            $action === 'APPROVE' ? 'BENEFICIARY_FINAL_APPROVED' : 'BENEFICIARY_FINAL_REJECTED',
            Household::class,
            $household->id,
            $oldState,
            $household->toArray(),
            $user,
            $household->administrative_unit_id
        );

        return $household;
    }
}
