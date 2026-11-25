<?php

namespace App\Modules\NidVerification\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NidVerificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'verification_reference' => $this->verification_reference,
            'household_id' => $this->household_id,
            'household' => $this->whenLoaded('household', function () {
                return $this->household ? [
                    'id' => $this->household->id,
                    'household_code' => $this->household->household_code,
                    'hoh_name_en' => $this->household->hoh_name_en,
                    'hoh_name_bn' => $this->household->hoh_name_bn,
                ] : null;
            }),
            'household_member_id' => $this->household_member_id,
            'administrative_unit' => $this->whenLoaded('administrativeUnit', function () {
                return $this->administrativeUnit ? [
                    'id' => $this->administrativeUnit->id,
                    'code' => $this->administrativeUnit->code,
                    'name_en' => $this->administrativeUnit->name_en,
                    'name_bn' => $this->administrativeUnit->name_bn,
                    'tier_level' => $this->administrativeUnit->tier_level,
                ] : null;
            }),
            'nid_masked' => $this->nid_masked,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'submitted_name_en' => $this->submitted_name_en,
            'verification_status' => $this->verification_status,
            'verification_token' => $this->verification_token,
            'provider_code' => $this->provider_code,
            'is_mock' => (bool) $this->is_mock,
            'matched_fields' => $this->matched_fields ?? [],
            'attempt_count' => (int) $this->attempt_count,
            'last_error_code' => $this->last_error_code,
            'last_error_message' => $this->last_error_message,
            'manual_override' => (bool) $this->manual_override,
            'previous_status' => $this->previous_status,
            'override_reason' => $this->override_reason,
            'requested_by' => $this->requester?->full_name_en,
            'requested_by_user_id' => $this->requested_by_user_id,
            'requested_by_keycloak_sub' => $this->requester?->keycloak_sub,
            'verified_by' => $this->verifier?->full_name_en,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'reviewed_by' => $this->reviewer?->full_name_en,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'attempts' => NidVerificationAttemptResource::collection($this->whenLoaded('attempts')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
