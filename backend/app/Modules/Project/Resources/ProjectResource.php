<?php

namespace App\Modules\Project\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isExternal = (bool) $request->user()?->hasRole('ROLE_EXTERNAL_PARTNER');

        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'category' => new ProjectCategoryResource($this->whenLoaded('category')),
            'disaster_event_id' => $this->disaster_event_id,
            'disaster_event' => $this->whenLoaded('disasterEvent', fn () => $this->disasterEvent ? [
                'id' => $this->disasterEvent->id,
                'disaster_code' => $this->disasterEvent->disaster_code,
                'name_en' => $this->disasterEvent->name_en,
                'name_bn' => $this->disasterEvent->name_bn,
                'severity_level' => $this->disasterEvent->severity_level,
            ] : null),
            'administrative_unit_id' => $this->administrative_unit_id,
            'administrative_unit' => $this->whenLoaded('administrativeUnit', fn () => [
                'id' => $this->administrativeUnit->id,
                'code' => $this->administrativeUnit->code,
                'name_en' => $this->administrativeUnit->name_en,
                'name_bn' => $this->administrativeUnit->name_bn,
                'tier_level' => $this->administrativeUnit->tier_level,
                'ancestry_path' => $this->administrativeUnit->ancestry_path,
            ]),
            'project_code' => $this->project_code,
            'reference_no' => $this->reference_no,
            'title_en' => $this->title_en,
            'title_bn' => $this->title_bn,
            'description' => $this->description,
            'objectives' => $this->objectives,
            'implementing_organization' => $this->implementing_organization,
            'project_manager_name' => $this->project_manager_name,
            'project_manager_contact' => $isExternal ? null : $this->project_manager_contact,
            'project_manager_user_id' => $isExternal ? null : $this->project_manager_user_id,
            'funding_source' => $this->funding_source,
            'approved_budget_bdt' => (float) $this->approved_budget_bdt,
            'revised_budget_bdt' => $this->revised_budget_bdt !== null ? (float) $this->revised_budget_bdt : null,
            'effective_budget_bdt' => $this->effective_budget_bdt,
            'released_funds_bdt' => (float) $this->released_funds_bdt,
            'expenditure_bdt' => (float) $this->expenditure_bdt,
            'remaining_balance_bdt' => $this->remaining_balance_bdt,
            'unreleased_balance_bdt' => $this->unreleased_balance_bdt,
            'physical_progress_pct' => (float) $this->physical_progress_pct,
            'financial_progress_pct' => (float) $this->financial_progress_pct,
            'start_date' => $this->start_date?->toDateString(),
            'expected_end_date' => $this->expected_end_date?->toDateString(),
            'actual_end_date' => $this->actual_end_date?->toDateString(),
            'ward_no' => $this->ward_no,
            'location_summary' => $this->location_summary,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'boundary_geojson' => $this->boundary_geojson,
            'status' => $this->status,
            'status_notes' => $this->status_notes,
            'is_delayed' => (bool) $this->is_delayed,
            'delay_days' => (int) $this->delay_days,
            'delay_reason' => $this->delay_reason,
            'created_by' => $this->created_by,
            'approved_by' => $this->approved_by,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'milestones_count' => $this->whenCounted('milestones'),
            'disbursements_count' => $this->whenCounted('disbursements'),
            'monitoring_reports_count' => $this->whenCounted('monitoringReports'),
            'risks_count' => $this->whenCounted('risks'),
            'locations' => ProjectLocationResource::collection($this->whenLoaded('locations')),
            'milestones' => ProjectMilestoneResource::collection($this->whenLoaded('milestones')),
            'disbursements' => ProjectDisbursementResource::collection($this->whenLoaded('disbursements')),
            'monitoring_reports' => ProjectMonitoringReportResource::collection($this->whenLoaded('monitoringReports')),
            'risks' => ProjectRiskResource::collection($this->whenLoaded('risks')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
