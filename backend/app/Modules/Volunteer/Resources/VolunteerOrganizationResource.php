<?php

namespace App\Modules\Volunteer\Resources;

use App\Modules\Volunteer\Policies\VolunteerPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VolunteerOrganizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isExternal = $user ? app(VolunteerPolicy::class)->isExternalPartnerOnly($user) : false;

        return [
            'id' => $this->id,
            'org_code' => $this->org_code,
            'name_en' => $this->name_en,
            'name_bn' => $this->name_bn,
            'org_type' => $this->org_type,
            'registration_no' => $this->registration_no,
            'administrative_unit_id' => $this->administrative_unit_id,
            'administrative_unit' => $this->whenLoaded('administrativeUnit', fn () => [
                'id' => $this->administrativeUnit->id,
                'code' => $this->administrativeUnit->code,
                'name_en' => $this->administrativeUnit->name_en,
                'name_bn' => $this->administrativeUnit->name_bn,
                'tier_level' => $this->administrativeUnit->tier_level,
            ]),
            'address' => $isExternal ? null : $this->address,
            'contact_person_name' => $isExternal ? null : $this->contact_person_name,
            'contact_person_designation' => $isExternal ? null : $this->contact_person_designation,
            'contact_person_phone' => $isExternal ? null : $this->contact_person_phone,
            'contact_person_email' => $isExternal ? null : $this->contact_person_email,
            'description' => $this->description,
            'verification_status' => $this->verification_status,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'verification_remarks' => $this->verification_remarks,
            'is_active' => $this->is_active,
            'volunteers_count' => $this->whenCounted('volunteers'),
            'coverages' => $this->whenLoaded('coverages', fn () => $this->coverages->map(fn ($cov) => [
                'id' => $cov->id,
                'administrative_unit_id' => $cov->administrative_unit_id,
                'name_en' => $cov->administrativeUnit?->name_en,
                'name_bn' => $cov->administrativeUnit?->name_bn,
                'tier_level' => $cov->administrativeUnit?->tier_level,
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
