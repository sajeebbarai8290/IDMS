<?php

namespace App\Modules\Workflow\Requests;

use App\Modules\Workflow\Models\WorkflowDelegation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreWorkflowDelegationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manageDelegation', WorkflowDelegation::class);
    }

    public function rules(): array
    {
        return [
            'workflow_definition_id' => ['nullable', 'uuid'],
            'delegator_user_id' => ['nullable', 'uuid'],
            'delegate_user_id' => ['required', 'uuid'],
            'administrative_unit_id' => ['nullable', 'uuid'],
            'delegated_role_code' => ['nullable', 'string', 'max:60'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after:valid_from'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
