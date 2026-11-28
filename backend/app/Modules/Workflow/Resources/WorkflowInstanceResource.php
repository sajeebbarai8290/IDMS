<?php

namespace App\Modules\Workflow\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowInstanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'instance_no' => $this->instance_no,
            'workflow_definition_id' => $this->workflow_definition_id,
            'definition' => $this->whenLoaded('definition', fn () => [
                'id' => $this->definition->id,
                'code' => $this->definition->code,
                'name_en' => $this->definition->name_en,
                'name_bn' => $this->definition->name_bn,
                'allow_self_approval' => (bool) $this->definition->allow_self_approval,
                'allow_delegation' => (bool) $this->definition->allow_delegation,
                'stages' => $this->definition->relationLoaded('stages')
                    ? WorkflowStageResource::collection($this->definition->stages)
                    : null,
            ]),
            'module_code' => $this->module_code,
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'entity_reference' => $this->entity_reference,
            'title_en' => $this->title_en,
            'title_bn' => $this->title_bn,
            'administrative_unit_id' => $this->administrative_unit_id,
            'administrative_unit' => $this->whenLoaded('administrativeUnit', fn () => [
                'id' => $this->administrativeUnit->id,
                'code' => $this->administrativeUnit->code,
                'name_en' => $this->administrativeUnit->name_en,
                'name_bn' => $this->administrativeUnit->name_bn,
                'tier_level' => $this->administrativeUnit->tier_level,
                'ancestry_path' => $this->administrativeUnit->ancestry_path,
            ]),
            'current_stage_id' => $this->current_stage_id,
            'current_stage' => $this->whenLoaded('currentStage', fn () => new WorkflowStageResource($this->currentStage)),
            'current_state' => $this->current_state,
            'priority' => $this->priority,
            'is_terminal' => $this->isTerminal(),
            'is_overdue' => $this->isOverdue(),
            'submitted_by' => $this->submitted_by,
            'submitter' => $this->whenLoaded('submitter', fn () => [
                'id' => $this->submitter->id,
                'name_en' => $this->submitter->full_name_en,
                'name_bn' => $this->submitter->full_name_bn,
                'role_code' => $this->submitter->role_code,
            ]),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'assigned_to_user_id' => $this->assigned_to_user_id,
            'assigned_user' => $this->whenLoaded('assignedUser', fn () => [
                'id' => $this->assignedUser->id,
                'name_en' => $this->assignedUser->full_name_en,
                'role_code' => $this->assignedUser->role_code,
            ]),
            'assigned_role_code' => $this->assigned_role_code,
            'due_at' => $this->due_at?->toIso8601String(),
            'reminder_sent_at' => $this->reminder_sent_at?->toIso8601String(),
            'escalated_at' => $this->escalated_at?->toIso8601String(),
            'escalation_count' => (int) $this->escalation_count,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'last_action_by' => $this->last_action_by,
            'last_actor' => $this->whenLoaded('lastActor', fn () => [
                'id' => $this->lastActor->id,
                'name_en' => $this->lastActor->full_name_en,
                'role_code' => $this->lastActor->role_code,
            ]),
            'last_action_at' => $this->last_action_at?->toIso8601String(),
            'submission_notes' => $this->submission_notes,
            'final_remarks' => $this->final_remarks,
            'metadata' => $this->metadata,
            'tasks' => $this->whenLoaded('tasks', function () {
                return $this->tasks->map(fn ($t) => [
                    'id' => $t->id,
                    'workflow_stage_id' => $t->workflow_stage_id,
                    'stage_code' => $t->relationLoaded('stage') ? $t->stage?->stage_code : null,
                    'stage_name_en' => $t->relationLoaded('stage') ? $t->stage?->name_en : null,
                    'stage_name_bn' => $t->relationLoaded('stage') ? $t->stage?->name_bn : null,
                    'step_sequence' => (int) $t->step_sequence,
                    'assignee_type' => $t->assignee_type,
                    'assignee_user_id' => $t->assignee_user_id,
                    'assignee_user_name' => $t->relationLoaded('assigneeUser') ? $t->assigneeUser?->full_name_en : null,
                    'assignee_role_code' => $t->assignee_role_code,
                    'delegated_from_user_id' => $t->delegated_from_user_id,
                    'delegated_from_name' => $t->relationLoaded('delegatedFromUser') ? $t->delegatedFromUser?->full_name_en : null,
                    'authority_type' => $t->authority_type,
                    'status' => $t->status,
                    'due_at' => $t->due_at?->toIso8601String(),
                    'acted_by_user_id' => $t->acted_by_user_id,
                    'acted_by_name' => $t->relationLoaded('actor') ? $t->actor?->full_name_en : null,
                    'acted_at' => $t->acted_at?->toIso8601String(),
                    'comments' => $t->comments,
                ])->values()->all();
            }),
            'transitions' => WorkflowTransitionResource::collection($this->whenLoaded('transitions')),
            'attachments' => $this->whenLoaded('attachments', function () {
                return $this->attachments->map(fn ($att) => [
                    'id' => $att->id,
                    'workflow_transition_id' => $att->workflow_transition_id,
                    'title' => $att->title,
                    'original_name' => $att->original_name,
                    'mime_type' => $att->mime_type,
                    'file_size_bytes' => (int) $att->file_size_bytes,
                    'sha256_hash' => $att->sha256_hash,
                    'uploaded_by_name' => $att->relationLoaded('uploader') ? $att->uploader?->full_name_en : null,
                    'created_at' => $att->created_at?->toIso8601String(),
                ])->values()->all();
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
