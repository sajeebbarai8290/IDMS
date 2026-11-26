<?php

namespace App\Modules\Project\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectLocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'administrative_unit_id' => $this->administrative_unit_id,
            'administrative_unit' => $this->whenLoaded('administrativeUnit', fn () => [
                'id' => $this->administrativeUnit->id,
                'code' => $this->administrativeUnit->code,
                'name_en' => $this->administrativeUnit->name_en,
                'name_bn' => $this->administrativeUnit->name_bn,
                'tier_level' => $this->administrativeUnit->tier_level,
                'ancestry_path' => $this->administrativeUnit->ancestry_path,
            ]),
            'ward_no' => $this->ward_no,
            'site_name_en' => $this->site_name_en,
            'site_name_bn' => $this->site_name_bn,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'boundary_geojson' => $this->boundary_geojson,
            'notes' => $this->notes,
        ];
    }
}
