<?php

namespace App\Modules\Volunteer\Resources;

use App\Modules\Volunteer\Policies\VolunteerPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VolunteerDeploymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $policy = app(VolunteerPolicy::class);
        $isExternal = $user ? $policy->isExternalPartnerOnly($user) : false;
        $canExactLocation = $user ? $policy->canViewExactLocation($user) : false;

        $lat = $this->latitude !== null ? ($canExactLocation ? (float) $this->latitude : round((float) $this->latitude, 2)) : null;
        $lng = $this->longitude !== null ? ($canExactLocation ? (float) $this->longitude : round((float) $this->longitude, 2)) : null;

        return [
            'id' => $this->id,
            'deployment_no' => $this->deployment_no,
            'volunteer_id' => $this->volunteer_id,
            'disaster_event_id' => $this->disaster_event_id,
            'response_operation_id' => $this->response_operation_id,
            'response_team_id' => $this->response_team_id,
            'administrative_unit_id' => $this->administrative_unit_id,
            'deployment_location' => $isExternal ? $this->administrativeUnit?->name_en : $this->deployment_location,
            'latitude' => $lat,
            'longitude' => $lng,
            'location_masked' => !$canExactLocation,
            'assigned_role' => $this->assigned_role,
            'assigned_duties' => $this->assigned_duties,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'actual_end_date' => $this->actual_end_date?->toDateString(),
            'supervisor_user_id' => $this->supervisor_user_id,
            'supervisor_name' => $isExternal ? null : $this->supervisor_name,
            'supervisor_contact' => $isExternal ? null : $this->supervisor_contact,
            'deployment_status' => $this->deployment_status,
            'notes' => $isExternal ? null : $this->notes,
            'volunteer' => $this->whenLoaded('volunteer', fn () => [
                'id' => $this->volunteer->id,
                'volunteer_code' => $this->volunteer->volunteer_code,
                'full_name_en' => $isExternal ? 'Verified Volunteer' : $this->volunteer->full_name_en,
                'full_name_bn' => $isExternal ? 'যাচাইকৃত স্বেচ্ছাসেবক' : $this->volunteer->full_name_bn,
                'availability_status' => $this->volunteer->availability_status,
                'organization' => $this->volunteer->organization?->name_en,
            ]),
            'disaster_event' => $this->whenLoaded('disasterEvent', fn () => $this->disasterEvent ? [
                'id' => $this->disasterEvent->id,
                'disaster_code' => $this->disasterEvent->disaster_code,
                'name_en' => $this->disasterEvent->name_en,
                'name_bn' => $this->disasterEvent->name_bn,
            ] : null),
            'response_operation' => $this->whenLoaded('responseOperation', fn () => $this->responseOperation ? [
                'id' => $this->responseOperation->id,
                'operation_no' => $this->responseOperation->operation_no,
                'title_en' => $this->responseOperation->title_en,
                'title_bn' => $this->responseOperation->title_bn,
            ] : null),
            'administrative_unit' => $this->whenLoaded('administrativeUnit', fn () => [
                'id' => $this->administrativeUnit->id,
                'code' => $this->administrativeUnit->code,
                'name_en' => $this->administrativeUnit->name_en,
                'name_bn' => $this->administrativeUnit->name_bn,
            ]),
            'attendances' => $this->whenLoaded('attendances', fn () => VolunteerAttendanceResource::collection($this->attendances)),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
