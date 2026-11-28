<?php

namespace App\Modules\Workflow\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowTransitionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workflow_instance_id' => $this->workflow_instance_id,
            'workflow_stage_task_id' => $this->workflow_stage_task_id,
            'action' => $this->action,
            'from_state' => $this->from_state,
            'to_state' => $this->to_state,
            'from_stage' => $this->whenLoaded('fromStage', fn () => [
                'id' => $this->fromStage->id,
                'stage_code' => $this->fromStage->stage_code,
                'name_en' => $this->fromStage->name_en,
                'name_bn' => $this->fromStage->name_bn,
                'approval_level' => $this->fromStage->approval_level,
            ]),
            'to_stage' => $this->whenLoaded('toStage', fn () => [
                'id' => $this->toStage->id,
                'stage_code' => $this->toStage->stage_code,
                'name_en' => $this->toStage->name_en,
                'name_bn' => $this->toStage->name_bn,
                'approval_level' => $this->toStage->approval_level,
            ]),
            'action_by_user_id' => $this->action_by_user_id,
            'actor_name' => $this->actor_name,
            'actor_role' => $this->actor_role,
            'acted_on_behalf_of_user_id' => $this->acted_on_behalf_of_user_id,
            'acted_on_behalf_of_name' => $this->whenLoaded('actedOnBehalfOf', fn () => $this->actedOnBehalfOf?->full_name_en),
            'remarks' => $this->remarks,
            'metadata' => $this->metadata,
            'transitioned_at' => $this->transitioned_at?->toIso8601String(),
        ];
    }
}
