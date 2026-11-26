<?php

namespace App\Modules\Project\Requests;

use App\Modules\Project\Models\ProjectRisk;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateProjectRiskRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ProjectRisk $risk */
        $risk = $this->route('risk');
        return Gate::allows('createMonitoring', $risk->project);
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:3000'],
            'severity' => ['sometimes', 'string', Rule::in(['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'])],
            'likelihood' => ['nullable', 'string', Rule::in(['LOW', 'MEDIUM', 'HIGH'])],
            'mitigation_plan' => ['nullable', 'string', 'max:3000'],
            'owner_name' => ['nullable', 'string', 'max:150'],
            'status' => ['sometimes', 'string', Rule::in([
                ProjectRisk::STATUS_OPEN,
                ProjectRisk::STATUS_MITIGATING,
                ProjectRisk::STATUS_RESOLVED,
                ProjectRisk::STATUS_CLOSED,
            ])],
            'resolved_date' => ['nullable', 'date'],
        ];
    }
}
