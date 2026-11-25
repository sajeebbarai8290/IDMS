<?php

namespace App\Modules\Beneficiary\Services;

use App\Core\Models\User;
use App\Modules\Beneficiary\Models\Household;
use App\Modules\Beneficiary\Models\HouseholdMember;
use App\Modules\NidVerification\Models\NidVerification;
use App\Modules\NidVerification\Services\NidVerificationService as CoreNidVerificationService;

/**
 * Class NidVerificationService
 *
 * Backward-compatible Beneficiary facade delegating to App\Modules\NidVerification\Services\NidVerificationService.
 *
 * @package App\Modules\Beneficiary\Services
 */
class NidVerificationService
{
    public function __construct(
        protected CoreNidVerificationService $coreNidService
    ) {}

    public function isValidFormat(string $nid): bool
    {
        return $this->coreNidService->isValidFormat($nid);
    }

    public function verify(
        string $rawNid,
        ?string $dob = null,
        ?Household $household = null,
        ?HouseholdMember $member = null,
        ?User $user = null
    ): NidVerification {
        return $this->coreNidService->initiateVerification(
            rawNid: $rawNid,
            dob: $dob,
            nameEn: $household?->hoh_name_en ?? $member?->full_name_en,
            household: $household,
            member: $member,
            administrativeUnitId: $household?->administrative_unit_id ?? $user?->administrative_unit_id,
            user: $user,
            async: false,
        );
    }
}
