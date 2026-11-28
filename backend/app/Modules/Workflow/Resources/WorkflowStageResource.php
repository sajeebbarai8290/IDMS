<?php

namespace App\Modules\Workflow\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowStageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workflow_definition_id' => $this->workflow_definition_id,
            'stage_order' => (int) $this->stage_order,
            'stage_code' => $this->stage_code,
            'name_en' => $this->name_en,
            'name_bn' => $this->name_bn,
            'approval_level' => $this->approval_level,
            'execution_mode' => $this->execution_mode,
            'required_approvals' => (int) $this->required_approvals,
            'min_tier_level' => $this->min_tier_level,
            'sla_hours' => (int) $this->sla_hours,
            'escalation_role_code' => $this->escalation_role_code,
            'allow_delegation' => (bool) $this->allow_delegation,
            'allow_return_for_correction' => (bool) $this->allow_return_for_correction,
            'allow_info_request' => (bool) $this->allow_info_request,
            'allow_forward' => (bool) $this->allow_forward,
            'is_terminal' => (bool) $this->is_terminal,
            'mapped_entity_status' => $this->mapped_entity_status,
            'description' => $this->description,
            'assignments' => $this->whenLoaded('assignments', function () {
                return $this->assignments->map(fn ($a) => [
                    'id' => $a->id,
                    'assignee_type' => $a->assignee_type,
                    'user_id' => $a->user_id,
                    'user_name' => $a->relationLoaded('user') ? $a->user?->full_name_en : null,
                    'role_code' => $a->role_code,
                    'administrative_unit_id' => $a->administrative_unit_id,
                    'administrative_unit_name' => $a->relationLoaded('administrativeUnit') ? $a->administrativeUnit?->name_en : null,
                    'tier_level' => $a->tier_level,
                    'authority_type' => $a->authority_type,
                    'can_delegate' => (bool) $a->can_delegate,
                    'is_mandatory' => (bool) $a->is_mandatory,
                    'sort_order' => (int) $a->sort_order,
                ])->values()->all();
            }),
        ];
    }
}
