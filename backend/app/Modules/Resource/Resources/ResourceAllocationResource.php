<?php

namespace App\Modules\Resource\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResourceAllocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isExternal = $request->user()?->hasRole('ROLE_EXTERNAL_PARTNER') ?? false;

        return [
            'id' => $this->id,
            'allocation_no' => $this->allocation_no,
            'resource_asset_id' => $this->resource_asset_id,
            'asset' => $this->whenLoaded('asset', fn () => $this->asset ? [
                'id' => $this->asset->id,
                'asset_code' => $this->asset->asset_code,
                'name_en' => $this->asset->name_en,
                'name_bn' => $this->asset->name_bn,
                'status' => $this->asset->status,
                'category' => $this->asset->relationLoaded('category') && $this->asset->category ? [
                    'id' => $this->asset->category->id,
                    'code' => $this->asset->category->code,
                    'name_en' => $this->asset->category->name_en,
                    'name_bn' => $this->asset->category->name_bn,
                ] : null,
            ] : null),
            'disaster_event_id' => $this->disaster_event_id,
            'disaster_event' => $this->whenLoaded('disasterEvent', fn () => $this->disasterEvent ? [
                'id' => $this->disasterEvent->id,
                'disaster_code' => $this->disasterEvent->disaster_code,
                'name_en' => $this->disasterEvent->name_en,
                'name_bn' => $this->disasterEvent->name_bn,
            ] : null),
            'response_operation_id' => $this->response_operation_id,
            'response_operation' => $this->whenLoaded('responseOperation', fn () => $this->responseOperation ? [
                'id' => $this->responseOperation->id,
                'operation_no' => $this->responseOperation->operation_no,
                'title_en' => $this->responseOperation->title_en,
                'title_bn' => $this->responseOperation->title_bn,
                'status' => $this->responseOperation->status,
            ] : null),
            'administrative_unit_id' => $this->administrative_unit_id,
            'administrative_unit' => $this->whenLoaded('administrativeUnit', fn () => $this->administrativeUnit ? [
                'id' => $this->administrativeUnit->id,
                'name_en' => $this->administrativeUnit->name_en,
                'name_bn' => $this->administrativeUnit->name_bn,
                'tier_level' => $this->administrativeUnit->tier_level,
            ] : null),
            'allocation_location' => $this->allocation_location,
            'latitude' => $isExternal ? ($this->latitude !== null ? round((float) $this->latitude, 2) : null) : $this->latitude,
            'longitude' => $isExternal ? ($this->longitude !== null ? round((float) $this->longitude, 2) : null) : $this->longitude,
            'assignment_date' => $this->assignment_date?->toDateString(),
            'expected_return_date' => $this->expected_return_date?->toDateString(),
            'actual_return_date' => $this->actual_return_date?->toDateString(),
            'responsible_officer_name' => $isExternal ? null : $this->responsible_officer_name,
            'responsible_officer_contact' => $isExternal ? null : $this->responsible_officer_contact,
            'status' => $this->status,
            'purpose' => $this->purpose,
            'utilization_notes' => $this->utilization_notes,
            'return_condition_notes' => $this->return_condition_notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
