<?php

namespace App\Modules\Workflow\Requests;

use App\Modules\Workflow\Models\WorkflowDefinition;
use App\Modules\Workflow\Models\WorkflowStage;
use App\Modules\Workflow\Models\WorkflowStageAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

abstract class AbstractWorkflowDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manageConfiguration', WorkflowDefinition::class);
    }

    protected function sharedStageRules(bool $stagesRequired): array
    {
        $stageReq = $stagesRequired ? 'required' : 'required_with:stages';

        return [
            'stages' => [$stagesRequired ? 'required' : 'sometimes', 'array', 'min:1'],
            'stages.*.stage_order' => [$stageReq, 'integer', 'min:1'],
            'stages.*.stage_code' => [$stageReq, 'string', 'max:60', 'alpha_dash'],
            'stages.*.name_en' => [$stageReq, 'string', 'max:150'],
            'stages.*.name_bn' => ['nullable', 'string', 'max:200'],
            'stages.*.approval_level' => [$stageReq, 'string', Rule::in(WorkflowStage::APPROVAL_LEVELS)],
            'stages.*.execution_mode' => ['sometimes', 'string', Rule::in(WorkflowStage::EXECUTION_MODES)],
            'stages.*.required_approvals' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'stages.*.min_tier_level' => ['nullable', 'string', 'max:30'],
            'stages.*.sla_hours' => ['sometimes', 'integer', 'min:1', 'max:720'],
            'stages.*.escalation_role_code' => ['nullable', 'string', 'max:60'],
            'stages.*.allow_delegation' => ['sometimes', 'boolean'],
            'stages.*.allow_return_for_correction' => ['sometimes', 'boolean'],
            'stages.*.allow_info_request' => ['sometimes', 'boolean'],
            'stages.*.allow_forward' => ['sometimes', 'boolean'],
            'stages.*.is_terminal' => ['sometimes', 'boolean'],
            'stages.*.mapped_entity_status' => ['nullable', 'string', 'max:50'],
            'stages.*.description' => ['nullable', 'string', 'max:1000'],
            'stages.*.assignments' => [$stageReq, 'array', 'min:1'],
            'stages.*.assignments.*.assignee_type' => ['required', 'string', Rule::in(WorkflowStageAssignment::ASSIGNEE_TYPES)],
            'stages.*.assignments.*.user_id' => ['nullable', 'required_if:stages.*.assignments.*.assignee_type,USER', 'uuid'],
            'stages.*.assignments.*.role_code' => ['nullable', 'required_if:stages.*.assignments.*.assignee_type,ROLE', 'string', 'max:60'],
            'stages.*.assignments.*.administrative_unit_id' => ['nullable', 'required_if:stages.*.assignments.*.assignee_type,ADMINISTRATIVE_UNIT', 'uuid'],
            'stages.*.assignments.*.tier_level' => ['nullable', 'string', 'max:30'],
            'stages.*.assignments.*.authority_type' => ['sometimes', 'string', Rule::in(WorkflowStageAssignment::AUTHORITY_TYPES)],
            'stages.*.assignments.*.can_delegate' => ['sometimes', 'boolean'],
            'stages.*.assignments.*.is_mandatory' => ['sometimes', 'boolean'],
        ];
    }
}
