<?php

namespace App\Modules\Response\Requests;

use App\Modules\Response\Models\ResponseTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateResponseTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('task');
        if (!$task instanceof ResponseTask) {
            return false;
        }
        $task->loadMissing('operation');

        return $task->operation && Gate::allows('manageTasks', $task->operation);
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', Rule::in(ResponseTask::STATUSES)],
            'progress_percentage' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'completion_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
