<?php

namespace App\Modules\Disaster\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DisasterDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'disaster_code' => $this->disaster_code,
            'category_id' => $this->category_id,
            'category' => new DisasterCategoryResource($this->whenLoaded('category')),
            'disaster_type' => $this->disaster_type,
            'subtype' => $this->subtype,
            'name_en' => $this->name_en,
            'name_bn' => $this->name_bn,
            'description_en' => $this->description_en,
            'description_bn' => $this->description_bn,
            'severity_level' => $this->severity_level,
            'lifecycle_phase' => $this->lifecycle_phase,
            'declaration_date' => $this->declaration_date?->toIso8601String(),
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'epicenter_lat' => $this->epicenter_lat !== null ? (float) $this->epicenter_lat : null,
            'epicenter_lng' => $this->epicenter_lng !== null ? (float) $this->epicenter_lng : null,
            'affected_areas_summary' => $this->affected_areas_summary,
            'affected_zone_geojson' => $this->affected_zone_geojson,
            'is_national' => (bool) $this->is_national,
            'is_emergency_declared' => (bool) $this->is_emergency_declared,
            'locations' => DisasterLocationResource::collection($this->whenLoaded('locations')),
            'impact' => new DisasterImpactResource($this->whenLoaded('impact')),
            'warnings' => DisasterWarningResource::collection($this->whenLoaded('warnings')),
            'timelines' => DisasterTimelineResource::collection($this->whenLoaded('timelines')),
            'created_by' => $this->creator?->full_name_en,
            'updated_by' => $this->updater?->full_name_en,
            'verified_by' => $this->verifier?->full_name_en,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
