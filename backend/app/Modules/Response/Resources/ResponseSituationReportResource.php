<?php

namespace App\Modules\Response\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResponseSituationReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $spatial = app(\App\Modules\Response\Services\ResponseSpatialService::class);
        $privacy = $this->privacy_level ?? 'PUBLIC_AREA';
        $coords = $user
            ? $spatial->maskCoordinatesIfNeeded($this->latitude, $this->longitude, $user, $privacy)
            : ['latitude' => $this->latitude, 'longitude' => $this->longitude, 'location_masked' => false];

        return [
            'id' => $this->id,
            'response_operation_id' => $this->response_operation_id,
            'disaster_event_id' => $this->disaster_event_id,
            'administrative_unit_id' => $this->administrative_unit_id,
            'administrative_unit' => $this->whenLoaded('administrativeUnit', fn () => [
                'id' => $this->administrativeUnit->id,
                'name_en' => $this->administrativeUnit->name_en,
                'name_bn' => $this->administrativeUnit->name_bn,
                'tier_level' => $this->administrativeUnit->tier_level,
            ]),
            'report_no' => $this->report_no,
            'report_type' => $this->report_type,
            'severity_level' => $this->severity_level,
            'title_en' => $this->title_en,
            'title_bn' => $this->title_bn,
            'summary' => $this->summary,
            'casualties_injured' => (int) $this->casualties_injured,
            'casualties_missing' => (int) $this->casualties_missing,
            'casualties_fatalities' => (int) $this->casualties_fatalities,
            'rescued_count' => (int) $this->rescued_count,
            'evacuated_count' => (int) $this->evacuated_count,
            'emergency_requirements' => $this->emergency_requirements,
            'location_description' => $coords['location_masked']
                ? 'Generalized area (restricted exact coordinates)'
                : $this->location_description,
            'latitude' => $coords['latitude'],
            'longitude' => $coords['longitude'],
            'location_masked' => $coords['location_masked'],
            'privacy_level' => $privacy,
            'reporter' => $this->whenLoaded('reporter', fn () => $this->reporter && !$user?->hasRole('ROLE_EXTERNAL_PARTNER') ? [
                'id' => $this->reporter->id,
                'full_name_en' => $this->reporter->full_name_en,
                'role_code' => $this->reporter->role_code,
            ] : null),
            'reported_at' => $this->reported_at?->toIso8601String(),
        ];
    }
}
