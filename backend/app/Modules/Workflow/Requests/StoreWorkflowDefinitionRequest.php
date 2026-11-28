<?php

namespace App\Modules\Workflow\Requests;

use App\Modules\Workflow\Models\WorkflowDefinition;
use Illuminate\Validation\Rule;

class StoreWorkflowDefinitionRequest extends AbstractWorkflowDefinitionRequest
{
    public function rules(): array
    {
        return array_merge([
            'code' => [
                'required',
                'string',
                'max:60',
                'alpha_dash',
                Rule::unique((new WorkflowDefinition())->getTable(), 'code'),
            ],
            'name_en' => ['required', 'string', 'max:150'],
            'name_bn' => ['required', 'string', 'max:200'],
            'module_code' => ['required', 'string', 'max:40', Rule::in(WorkflowDefinition::MODULES)],
            'entity_type' => ['required', 'string', 'max:100', Rule::in(WorkflowDefinition::ENTITY_TYPES)],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'allow_self_approval' => ['sometimes', 'boolean'],
            'allow_delegation' => ['sometimes', 'boolean'],
            'sync_entity_status' => ['sometimes', 'boolean'],
            'sla_hours' => ['sometimes', 'integer', 'min:1', 'max:2160'],
            'escalation_enabled' => ['sometimes', 'boolean'],
            'config_metadata' => ['nullable', 'array'],
        ], $this->sharedStageRules(true));
    }
}
