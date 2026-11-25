<?php

namespace App\Modules\Response\Requests;

use App\Modules\Response\Models\ResponseOperation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TransitionOperationLifecycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $operation = $this->route('operation');
        $target = strtoupper((string) $this->input('status', ''));

        if (!$operation instanceof ResponseOperation) {
            return false;
        }

        return Gate::allows('manageLifecycle', [$operation, $target]);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(ResponseOperation::STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
