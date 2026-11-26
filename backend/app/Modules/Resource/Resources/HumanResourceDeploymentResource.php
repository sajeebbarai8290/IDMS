<?php

namespace App\Modules\Resource\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HumanResourceDeploymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isExternal = $request->user()?->hasRole('ROLE_EXTERNAL_PARTNER') ?? false;

        return [
            'id' => $this->id,
            'deployment_no' => $this->deployment_no,
            'resource_asset_id' => $this->resource_asset_id,
            'asset' => $this->whenLoaded('asset', fn () => $this->asset ? [
                'id' => $this->asset->id,
                'asset_code' => $this->asset->asset_code,
                'name_en' => $this->asset->name_en,
                'name_bn' => $this->asset->name_bn,
                'status' => $this->asset->status,
            ] : null),
            'user_id' => $this->user_id,
            'response_team_id' => $this->response_team_id,
            'team' => $this->whenLoaded('team', fn () => $this->team ? [
                'id' => $this->team->id,
                'team_code' => $this->team->team_code,
                'team_name' => $this->team->team_name,
                'team_type' => $this->team->team_type,
                'member_count' => $this->team->member_count,
            ] : null),
            'response_operation_id' => $this->response_operation_id,
            'response_operation' => $this->whenLoaded('responseOperation', fn () => $this->responseOperation ? [
                'id' => $this->responseOperation->id,
                'operation_no' => $this->responseOperation->operation_no,
                'title_en' => $this->responseOperation->title_en,
                'title_bn' => $this->responseOperation->title_bn,
                'status' => $this->responseOperation->status,
            ] : null),
            'disaster_event_id' => $this->disaster_event_id,
            'disaster_event' => $this->whenLoaded('disasterEvent', fn () => $this->disasterEvent ? [
                'id' => $this->disasterEvent->id,
                'disaster_code' => $this->disasterEvent->disaster_code,
                'name_en' => $this->disasterEvent->name_en,
                'name_bn' => $this->disasterEvent->name_bn,
            ] : null),
            'administrative_unit_id' => $this->administrative_unit_id,
            'administrative_unit' => $this->whenLoaded('administrativeUnit', fn () => $this->administrativeUnit ? [
                'id' => $this->administrativeUnit->id,
                'name_en' => $this->administrativeUnit->name_en,
                'name_bn' => $this->administrativeUnit->name_bn,
                'tier_level' => $this->administrativeUnit->tier_level,
            ] : null),
            'personnel_name' => $isExternal ? 'Deployed Personnel' : $this->personnel_name,
            'personnel_designation' => $this->personnel_designation,
            'personnel_contact' => $isExternal ? null : $this->personnel_contact,
            'assigned_duty' => $this->assigned_duty,
            'deployment_location' => $this->deployment_location,
            'deployment_start_date' => $this->deployment_start_date?->toDateString(),
            'deployment_end_date' => $this->deployment_end_date?->toDateString(),
            'actual_return_date' => $this->actual_return_date?->toDateString(),
            'availability_status' => $this->availability_status,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
