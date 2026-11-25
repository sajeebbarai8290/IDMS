<?php

namespace App\Modules\Disaster\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DisasterLocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'administrative_unit_id' => $this->administrative_unit_id,
            'administrative_unit' => $this->whenLoaded('administrativeUnit', function () {
                return $this->administrativeUnit ? [
                    'id' => $this->administrativeUnit->id,
                    'code' => $this->administrativeUnit->code,
                    'name_en' => $this->administrativeUnit->name_en,
                    'name_bn' => $this->administrativeUnit->name_bn,
                    'tier_level' => $this->administrativeUnit->tier_level,
                    'ancestry_path' => $this->administrativeUnit->ancestry_path,
                ] : null;
            }),
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'boundary_geojson' => $this->boundary_geojson,
            'severity_override' => $this->severity_override,
            'notes' => $this->notes,
        ];
    }
}
