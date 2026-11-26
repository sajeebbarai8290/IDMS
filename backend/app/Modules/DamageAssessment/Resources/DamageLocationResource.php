<?php

namespace App\Modules\DamageAssessment\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DamageLocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'damage_assessment_id' => $this->damage_assessment_id,
            'administrative_unit_id' => $this->administrative_unit_id,
            'administrative_unit' => $this->whenLoaded('administrativeUnit', fn () => [
                'id' => $this->administrativeUnit->id,
                'code' => $this->administrativeUnit->code,
                'name_en' => $this->administrativeUnit->name_en,
                'name_bn' => $this->administrativeUnit->name_bn,
                'tier_level' => $this->administrativeUnit->tier_level,
            ]),
            'ward_no' => $this->ward_no,
            'locality_name_en' => $this->locality_name_en,
            'locality_name_bn' => $this->locality_name_bn,
            'affected_households' => (int) $this->affected_households,
            'affected_population' => (int) $this->affected_population,
            'severity_level' => $this->severity_level,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'notes' => $this->notes,
        ];
    }
}
