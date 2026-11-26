<?php

namespace App\Modules\Resource\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResourceAssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isExternal = $request->user()?->hasRole('ROLE_EXTERNAL_PARTNER') ?? false;

        return [
            'id' => $this->id,
            'asset_code' => $this->asset_code,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'code' => $this->category->code,
                'name_en' => $this->category->name_en,
                'name_bn' => $this->category->name_bn,
                'icon' => $this->category->icon,
                'color_hex' => $this->category->color_hex,
                'is_human_resource' => (bool) $this->category->is_human_resource,
            ] : null),
            'administrative_unit_id' => $this->administrative_unit_id,
            'administrative_unit' => $this->whenLoaded('administrativeUnit', fn () => $this->administrativeUnit ? [
                'id' => $this->administrativeUnit->id,
                'code' => $this->administrativeUnit->code,
                'name_en' => $this->administrativeUnit->name_en,
                'name_bn' => $this->administrativeUnit->name_bn,
                'tier_level' => $this->administrativeUnit->tier_level,
            ] : null),
            'asset_type' => $this->asset_type,
            'resource_type' => $this->resource_type ?: $this->asset_type,
            'name_en' => $this->name_en,
            'name_bn' => $this->name_bn,
            'description' => $this->description,
            'registration_no' => $isExternal ? null : $this->registration_no,
            'capacity_info' => $this->capacity_info,
            'specifications' => $this->specifications ?? [],
            'owning_organization' => $this->owning_organization,
            'current_location' => $this->current_location,
            'responsible_officer_name' => $isExternal ? null : $this->responsible_officer_name,
            'responsible_officer_contact' => $isExternal ? null : $this->responsible_officer_contact,
            'responsible_user_id' => $isExternal ? null : $this->responsible_user_id,
            'acquisition_date' => $this->acquisition_date?->toDateString(),
            'acquisition_value_bdt' => $this->acquisition_value_bdt !== null ? (float) $this->acquisition_value_bdt : null,
            'next_maintenance_due_at' => $this->next_maintenance_due_at?->toDateString(),
            'status' => $this->status,
            'latitude' => $isExternal ? ($this->latitude !== null ? round((float) $this->latitude, 2) : null) : $this->latitude,
            'longitude' => $isExternal ? ($this->longitude !== null ? round((float) $this->longitude, 2) : null) : $this->longitude,
            'is_active' => (bool) $this->is_active,
            'allocations_count' => $this->whenCounted('allocations'),
            'maintenances_count' => $this->whenCounted('maintenances'),
            'attachments_count' => $this->whenCounted('attachments'),
            'attachments' => $this->whenLoaded('attachments', fn () => $isExternal ? [] : $this->attachments->map(fn ($att) => [
                'id' => $att->id,
                'attachment_kind' => $att->attachment_kind,
                'title' => $att->title,
                'original_name' => $att->original_name,
                'mime_type' => $att->mime_type,
                'file_size_bytes' => $att->file_size_bytes,
                'sha256_hash' => $att->sha256_hash,
                'created_at' => $att->created_at?->toIso8601String(),
            ])),
            'allocations' => $this->whenLoaded('allocations', fn () => ResourceAllocationResource::collection($this->allocations)->resolve($request)),
            'maintenances' => $this->whenLoaded('maintenances', fn () => ResourceMaintenanceResource::collection($this->maintenances)->resolve($request)),
            'human_deployments' => $this->whenLoaded('humanDeployments', fn () => HumanResourceDeploymentResource::collection($this->humanDeployments)->resolve($request)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
