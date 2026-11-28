<?php

namespace App\Modules\Workflow\Requests;

use App\Modules\Workflow\Models\WorkflowDefinition;
use Illuminate\Validation\Rule;

class UpdateWorkflowDefinitionRequest extends AbstractWorkflowDefinitionRequest
{
    public function rules(): array
    {
        return array_merge([
            'name_en' => ['sometimes', 'string', 'max:150'],
            'name_bn' => ['sometimes', 'string', 'max:200'],
            'module_code' => ['sometimes', 'string', 'max:40', Rule::in(WorkflowDefinition::MODULES)],
            'entity_type' => ['sometimes', 'string', 'max:100', Rule::in(WorkflowDefinition::ENTITY_TYPES)],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'allow_self_approval' => ['sometimes', 'boolean'],
            'allow_delegation' => ['sometimes', 'boolean'],
            'sync_entity_status' => ['sometimes', 'boolean'],
            'sla_hours' => ['sometimes', 'integer', 'min:1', 'max:2160'],
            'escalation_enabled' => ['sometimes', 'boolean'],
            'config_metadata' => ['nullable', 'array'],
        ], $this->sharedStageRules(false));
    }
}
