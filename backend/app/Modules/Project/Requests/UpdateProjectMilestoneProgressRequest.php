<?php

namespace App\Modules\Project\Requests;

use App\Modules\Project\Models\ProjectMilestone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateProjectMilestoneProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ProjectMilestone $milestone */
        $milestone = $this->route('milestone');
        return Gate::allows('updateMilestone', $milestone->project);
    }

    public function rules(): array
    {
        return [
            'completion_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'actual_start_date' => ['nullable', 'date'],
            'actual_end_date' => ['nullable', 'date'],
            'delay_reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
