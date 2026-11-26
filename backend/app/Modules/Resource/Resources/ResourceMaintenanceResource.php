<?php

namespace App\Modules\Resource\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResourceMaintenanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isExternal = $request->user()?->hasRole('ROLE_EXTERNAL_PARTNER') ?? false;

        return [
            'id' => $this->id,
            'maintenance_no' => $this->maintenance_no,
            'resource_asset_id' => $this->resource_asset_id,
            'asset' => $this->whenLoaded('asset', fn () => $this->asset ? [
                'id' => $this->asset->id,
                'asset_code' => $this->asset->asset_code,
                'name_en' => $this->asset->name_en,
                'name_bn' => $this->asset->name_bn,
                'status' => $this->asset->status,
            ] : null),
            'administrative_unit_id' => $this->administrative_unit_id,
            'record_type' => $this->record_type,
            'title' => $this->title,
            'description' => $this->description,
            'scheduled_date' => $this->scheduled_date?->toDateString(),
            'performed_date' => $this->performed_date?->toDateString(),
            'next_due_date' => $this->next_due_date?->toDateString(),
            'reminder_days_before' => (int) ($this->reminder_days_before ?? 7),
            'is_overdue' => $this->status !== 'COMPLETED'
                && $this->status !== 'CANCELLED'
                && ($this->scheduled_date?->isPast() || $this->next_due_date?->isPast()),
            'inspection_findings' => $this->inspection_findings,
            'inspection_result' => $this->inspection_result,
            'repair_cost_bdt' => (float) $this->repair_cost_bdt,
            'vendor_or_workshop' => $isExternal ? null : $this->vendor_or_workshop,
            'status' => $this->status,
            'performed_by_name' => $isExternal ? null : $this->performed_by_name,
            'has_attachment' => !$isExternal && !empty($this->attachment_path),
            'attachment_original_name' => $isExternal ? null : $this->attachment_original_name,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
