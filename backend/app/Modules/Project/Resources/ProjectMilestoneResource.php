<?php

namespace App\Modules\Project\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectMilestoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'parent_milestone_id' => $this->parent_milestone_id,
            'wbs_code' => $this->wbs_code,
            'milestone_title' => $this->milestone_title,
            'milestone_title_bn' => $this->milestone_title_bn,
            'item_type' => $this->item_type,
            'deliverables' => $this->deliverables,
            'responsible_organization' => $this->responsible_organization,
            'assigned_officer_name' => $this->assigned_officer_name,
            'assigned_officer_user_id' => $this->assigned_officer_user_id,
            'planned_start_date' => $this->planned_start_date?->toDateString(),
            'planned_end_date' => $this->planned_end_date?->toDateString(),
            'actual_start_date' => $this->actual_start_date?->toDateString(),
            'actual_end_date' => $this->actual_end_date?->toDateString(),
            'weight_percentage' => (float) $this->weight_percentage,
            'target_percentage' => (float) $this->target_percentage,
            'completion_percentage' => (float) $this->completion_percentage,
            'approval_status' => $this->approval_status,
            'delay_reason' => $this->delay_reason,
            'certification_notes' => $this->certification_notes,
            'updated_by' => $this->updated_by,
            'certified_by' => $this->certified_by,
            'certifier_name' => $this->whenLoaded('certifier', fn () => $this->certifier?->full_name_en),
            'certified_at' => $this->certified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
