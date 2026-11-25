<?php

namespace App\Modules\Response\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResponseTeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isExternal = $user?->hasRole('ROLE_EXTERNAL_PARTNER') ?? false;
        $spatial = app(\App\Modules\Response\Services\ResponseSpatialService::class);
        $coords = $user
            ? $spatial->maskCoordinatesIfNeeded($this->latitude, $this->longitude, $user)
            : ['latitude' => $this->latitude, 'longitude' => $this->longitude, 'location_masked' => false];

        return [
            'id' => $this->id,
            'response_operation_id' => $this->response_operation_id,
            'administrative_unit_id' => $this->administrative_unit_id,
            'administrative_unit' => $this->whenLoaded('administrativeUnit', fn () => [
                'id' => $this->administrativeUnit->id,
                'name_en' => $this->administrativeUnit->name_en,
                'name_bn' => $this->administrativeUnit->name_bn,
                'tier_level' => $this->administrativeUnit->tier_level,
            ]),
            'team_code' => $this->team_code,
            'team_name' => $this->team_name,
            'agency_name' => $this->agency_name,
            'team_type' => $this->team_type,
            'leader_name' => $isExternal ? null : $this->leader_name,
            'leader_contact' => $isExternal ? null : $this->leader_contact,
            'member_count' => (int) $this->member_count,
            'status' => $this->status,
            'latitude' => $coords['latitude'],
            'longitude' => $coords['longitude'],
            'location_masked' => $coords['location_masked'],
            'last_checkin_at' => $this->last_checkin_at?->toIso8601String(),
        ];
    }
}
