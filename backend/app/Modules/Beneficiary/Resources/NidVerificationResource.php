<?php

namespace App\Modules\Beneficiary\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NidVerificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'household_id' => $this->household_id,
            'household_member_id' => $this->household_member_id,
            'nid_masked' => $this->nid_masked,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'verification_status' => $this->verification_status,
            'verification_token' => $this->verification_token,
            'provider_code' => $this->provider_code,
            'verified_by' => $this->verifier?->full_name_en ?? 'Officer',
            'verified_at' => $this->verified_at?->toIso8601String(),
        ];
    }
}
