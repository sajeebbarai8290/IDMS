<?php

namespace App\Modules\Workflow\Requests;

use App\Modules\Workflow\Models\WorkflowDefinition;
use App\Modules\Workflow\Models\WorkflowInstance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreWorkflowInstanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('submitInstance', WorkflowInstance::class);
    }

    public function rules(): array
    {
        return [
            'workflow_definition_id' => ['nullable', 'uuid'],
            'module_code' => ['nullable', 'string', 'max:40', Rule::in(WorkflowDefinition::MODULES)],
            'entity_type' => ['required', 'string', 'max:100', Rule::in(WorkflowDefinition::ENTITY_TYPES)],
            'entity_id' => ['nullable', 'uuid'],
            'entity_reference' => ['nullable', 'string', 'max:120'],
            'administrative_unit_id' => ['nullable', 'uuid'],
            'title_en' => ['nullable', 'string', 'max:255'],
            'title_bn' => ['nullable', 'string', 'max:255'],
            'priority' => ['sometimes', 'string', Rule::in(WorkflowInstance::PRIORITIES)],
            'comments' => ['nullable', 'string', 'max:3000'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
