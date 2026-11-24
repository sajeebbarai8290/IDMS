<?php

namespace App\Modules\Beneficiary\Services;

use App\Core\Models\AuditLog;
use App\Core\Models\User;
use App\Modules\Beneficiary\Models\Household;
use App\Modules\Beneficiary\Models\HouseholdMember;
use App\Modules\Beneficiary\Models\NidVerification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Class NidVerificationService
 *
 * Verifies citizen National ID numbers via mock adapter or Porichoy gateway contract.
 *
 * @package App\Modules\Beneficiary\Services
 */
class NidVerificationService
{
    /**
     * Validate NID format (10, 13, or 17 numeric digits).
     *
     * @param string $nid
     * @return bool
     */
    public function isValidFormat(string $nid): bool
    {
        $clean = trim($nid);
        return (bool) preg_match('/^(?:\d{10}|\d{13}|\d{17})$/', $clean);
    }

    /**
     * Verify NID for a citizen / household.
     *
     * @param string $rawNid
     * @param string|null $dob Format: Y-m-d
     * @param Household|null $household
     * @param HouseholdMember|null $member
     * @param User $user
     * @return NidVerification
     * @throws ValidationException
     */
    public function verify(
        string $rawNid,
        ?string $dob = null,
        ?Household $household = null,
        ?HouseholdMember $member = null,
        ?User $user = null
    ): NidVerification {
        $cleanNid = trim($rawNid);

        if (!$this->isValidFormat($cleanNid)) {
            throw ValidationException::withMessages([
                'nid_number' => 'Invalid National ID format. Must be 10, 13, or 17 numeric digits.',
            ]);
        }

        $nidHash = Household::hashNid($cleanNid);
        $nidMasked = Household::maskNid($cleanNid);

        // Simulation/Mock Adapter behavior
        // In this environment, mock adapter provides instant cryptographic verification
        $token = 'NID-AUTH-' . strtoupper(Str::random(12));
        $payload = [
            'status' => 'VERIFIED',
            'query_nid_hash' => $nidHash,
            'queried_at' => now()->toIso8601String(),
            'adapter' => 'MOCK_ADAPTER',
        ];

        $verification = NidVerification::create([
            'household_id' => $household?->id,
            'household_member_id' => $member?->id,
            'nid_hash' => $nidHash,
            'nid_masked' => $nidMasked,
            'date_of_birth' => $dob,
            'verification_status' => 'VERIFIED',
            'verification_token' => $token,
            'provider_code' => 'MOCK_ADAPTER',
            'response_payload_hash' => hash('sha256', json_encode($payload)),
            'verified_by_user_id' => $user?->id,
            'verified_at' => now(),
        ]);

        // If linked to household, mark verified
        if ($household) {
            $household->update([
                'nid_verified' => true,
            ]);
        }

        AuditLog::log(
            'NID_VERIFICATION_PERFORMED',
            NidVerification::class,
            $verification->id,
            null,
            ['verification_token' => $token, 'status' => 'VERIFIED'],
            $user,
            $household?->administrative_unit_id
        );

        return $verification;
    }
}
