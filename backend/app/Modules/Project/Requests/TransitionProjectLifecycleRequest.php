<?php

namespace App\Modules\Project\Requests;

use App\Modules\Project\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TransitionProjectLifecycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Project $project */
        $project = $this->route('project');
        $targetStatus = strtoupper((string) $this->input('status'));

        if ($targetStatus === Project::STATUS_UNDER_REVIEW) {
            return Gate::allows('submitForReview', $project);
        }

        if (in_array($targetStatus, [Project::STATUS_ONGOING, Project::STATUS_COMPLETED], true)) {
            return Gate::allows('executeLifecycle', $project);
        }

        return Gate::allows('approveProject', $project);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('status')) {
            $this->merge(['status' => strtoupper((string) $this->input('status'))]);
        }
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(Project::LIFECYCLE_STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'delay_reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
