<?php

namespace App\Modules\Project\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectMonitoringReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isExternal = (bool) $request->user()?->hasRole('ROLE_EXTERNAL_PARTNER');

        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'administrative_unit_id' => $this->administrative_unit_id,
            'administrative_unit' => $this->whenLoaded('administrativeUnit', fn () => [
                'id' => $this->administrativeUnit->id,
                'name_en' => $this->administrativeUnit->name_en,
                'name_bn' => $this->administrativeUnit->name_bn,
                'tier_level' => $this->administrativeUnit->tier_level,
            ]),
            'report_no' => $this->report_no,
            'visit_date' => $this->visit_date?->toDateString(),
            'inspector_name' => $isExternal ? 'MoDMR Inspector' : $this->inspector_name,
            'inspector_designation' => $this->inspector_designation,
            'observed_physical_pct' => (float) $this->observed_physical_pct,
            'observed_financial_pct' => $this->observed_financial_pct !== null ? (float) $this->observed_financial_pct : null,
            'delay_observed' => (bool) $this->delay_observed,
            'delay_days' => (int) $this->delay_days,
            'delay_reason' => $this->delay_reason,
            'findings' => $this->findings,
            'recommendations' => $this->recommendations,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'has_attachment' => !$isExternal && !empty($this->attachment_path),
            'attachment_original_name' => $isExternal ? null : $this->attachment_original_name,
            'attachment_mime' => $isExternal ? null : $this->attachment_mime,
            'attachment_sha256' => $isExternal ? null : $this->attachment_sha256,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
