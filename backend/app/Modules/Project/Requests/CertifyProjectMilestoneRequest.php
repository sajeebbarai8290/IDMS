<?php

namespace App\Modules\Project\Requests;

use App\Modules\Project\Models\ProjectMilestone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CertifyProjectMilestoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ProjectMilestone $milestone */
        $milestone = $this->route('milestone');
        return Gate::allows('certifyMilestone', $milestone);
    }

    public function rules(): array
    {
        return [
            'approval_status' => [
                'required',
                'string',
                Rule::in([
                    ProjectMilestone::APPROVAL_VERIFIED_ENGINEER,
                    ProjectMilestone::APPROVAL_APPROVED_PIO,
                    ProjectMilestone::APPROVAL_REJECTED,
                ]),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
