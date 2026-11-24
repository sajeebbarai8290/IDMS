<?php

namespace App\Modules\Beneficiary\Resources;

use App\Core\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HouseholdDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $decryptPiiRequested = $request->boolean('decrypt_pii');
        $canDecrypt = $user && $user->can('decryptPii', $this->resource);

        $showCleartext = $decryptPiiRequested && $canDecrypt;

        if ($showCleartext) {
            AuditLog::log(
                'PII_DECRYPTION_ACCESSED',
                get_class($this->resource),
                $this->id,
                null,
                ['action' => 'UNMASKED_PII_VIEWED', 'household_code' => $this->household_code],
                $user,
                $this->administrative_unit_id
            );
        }

        return [
            'id' => $this->id,
            'household_code' => $this->household_code,
            'administrative_unit' => [
                'id' => $this->administrativeUnit?->id,
                'name_en' => $this->administrativeUnit?->name_en,
                'name_bn' => $this->administrativeUnit?->name_bn,
                'code' => $this->administrativeUnit?->code,
                'tier_level' => $this->administrativeUnit?->tier_level,
                'ancestry_path' => $this->administrativeUnit?->ancestry_path,
            ],
            'village_ward_name' => $this->village_ward_name,
            'present_address' => $this->present_address,
            'permanent_address' => $this->permanent_address,
            'hoh_name_en' => $this->hoh_name_en,
            'hoh_name_bn' => $this->hoh_name_bn,
            'hoh_nid' => $showCleartext ? $this->getDecryptedNid() : $this->hoh_nid_masked,
            'hoh_nid_masked' => $this->hoh_nid_masked,
            'is_nid_unmasked' => $showCleartext,
            'hoh_dob' => $this->hoh_dob?->format('Y-m-d'),
            'hoh_gender' => $this->hoh_gender,
            'hoh_mobile' => $showCleartext ? $this->getDecryptedMobile() : $this->hoh_mobile_masked,
            'hoh_mobile_masked' => $this->hoh_mobile_masked,
            'hoh_occupation' => $this->hoh_occupation,
            'monthly_income_bdt' => (float) $this->monthly_income_bdt,
            'income_category' => $this->income_category,
            'family_member_count' => (int) $this->family_member_count,
            'vulnerability' => [
                'score' => (float) $this->vulnerability_score,
                'is_female_headed' => (bool) $this->is_female_headed,
                'has_elderly' => (bool) $this->has_elderly,
                'has_disabled' => (bool) $this->has_disabled,
                'has_children_infants' => (bool) $this->has_children_infants,
                'has_pregnant_lactating' => (bool) $this->has_pregnant_lactating,
                'is_displaced' => (bool) $this->is_displaced,
                'other_vulnerabilities' => $this->other_vulnerabilities ?? [],
            ],
            'coordinates' => [
                'latitude' => $this->latitude ? (float) $this->latitude : null,
                'longitude' => $this->longitude ? (float) $this->longitude : null,
            ],
            'verification' => [
                'status' => $this->verification_status,
                'nid_verified' => (bool) $this->nid_verified,
                'address_verified' => (bool) $this->address_verified,
                'field_verified' => (bool) $this->field_verified,
            ],
            'photo_url' => $this->photo_url,
            'nid_document_url' => $this->nid_document_url,
            'members' => HouseholdMemberResource::collection($this->whenLoaded('members')),
            'verification_history' => $this->verifications->map(function ($v) {
                return [
                    'id' => $v->id,
                    'stage' => $v->stage,
                    'status' => $v->status,
                    'remarks' => $v->remarks,
                    'evidence_document_path' => $v->evidence_document_path,
                    'verified_by' => $v->verifier?->full_name_en ?? 'Officer',
                    'verified_at' => $v->verified_at?->toIso8601String(),
                ];
            }),
            'disaster_eligibilities' => $this->disasterEligibilities->map(function ($e) {
                return [
                    'id' => $e->id,
                    'disaster' => [
                        'id' => $e->disasterEvent?->id,
                        'code' => $e->disasterEvent?->disaster_code,
                        'name_en' => $e->disasterEvent?->name_en,
                        'name_bn' => $e->disasterEvent?->name_bn,
                        'type' => $e->disasterEvent?->disaster_type,
                    ],
                    'eligibility_status' => $e->eligibility_status,
                    'assistance_count' => $e->assistance_count,
                    'last_assistance_at' => $e->last_assistance_at?->toIso8601String(),
                    'is_blacklisted' => (bool) $e->is_blacklisted,
                ];
            }),
            'duplicate_suspects' => $this->duplicateSuspects->map(function ($d) {
                return [
                    'id' => $d->id,
                    'matched_household_id' => $d->matched_household_id,
                    'matched_household_code' => $d->matchedHousehold?->household_code,
                    'matched_hoh_name' => $d->matchedHousehold?->hoh_name_en,
                    'match_type' => $d->match_type,
                    'confidence_score' => (float) $d->confidence_score,
                    'status' => $d->status,
                    'reviewed_at' => $d->reviewed_at?->toIso8601String(),
                ];
            }),
            'creator' => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->full_name_en,
                'role' => $this->creator->role_code,
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
