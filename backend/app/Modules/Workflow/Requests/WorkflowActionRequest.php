<?php

namespace App\Modules\Workflow\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WorkflowActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'comments' => ['nullable', 'string', 'max:3000'],
            'remarks' => ['nullable', 'string', 'max:3000'],
            'reason' => ['nullable', 'string', 'max:3000'],
            'target_stage_id' => ['nullable', 'uuid'],
            'forward_to_user_id' => ['nullable', 'uuid'],
            'forward_to_role_code' => ['nullable', 'string', 'max:60'],
            'forward_to_unit_id' => ['nullable', 'uuid'],
            'resume_current_stage' => ['sometimes', 'boolean'],
        ];
    }
}
