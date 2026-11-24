<?php

namespace App\Modules\Beneficiary\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DuplicateSuspectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'household' => [
                'id' => $this->household?->id,
                'code' => $this->household?->household_code,
                'name_en' => $this->household?->hoh_name_en,
                'name_bn' => $this->household?->hoh_name_bn,
                'nid_masked' => $this->household?->hoh_nid_masked,
                'mobile_masked' => $this->household?->hoh_mobile_masked,
                'jurisdiction' => $this->household?->administrativeUnit?->name_en,
                'village' => $this->household?->village_ward_name,
            ],
            'matched_household' => [
                'id' => $this->matchedHousehold?->id,
                'code' => $this->matchedHousehold?->household_code,
                'name_en' => $this->matchedHousehold?->hoh_name_en,
                'name_bn' => $this->matchedHousehold?->hoh_name_bn,
                'nid_masked' => $this->matchedHousehold?->hoh_nid_masked,
                'mobile_masked' => $this->matchedHousehold?->hoh_mobile_masked,
                'jurisdiction' => $this->matchedHousehold?->administrativeUnit?->name_en,
                'village' => $this->matchedHousehold?->village_ward_name,
            ],
            'match_type' => $this->match_type,
            'confidence_score' => (float) $this->confidence_score,
            'status' => $this->status,
            'reviewed_by' => $this->reviewer?->full_name_en,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'resolution_notes' => $this->resolution_notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
