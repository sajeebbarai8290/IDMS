<?php

namespace App\Modules\Project\Requests;

use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectRisk;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreProjectRiskRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Project $project */
        $project = $this->route('project');
        return Gate::allows('createMonitoring', $project);
    }

    public function rules(): array
    {
        return [
            'risk_code' => ['nullable', 'string', 'max:40'],
            'record_kind' => ['nullable', 'string', Rule::in([ProjectRisk::KIND_RISK, ProjectRisk::KIND_ISSUE])],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:3000'],
            'severity' => ['required', 'string', Rule::in(['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'])],
            'likelihood' => ['nullable', 'string', Rule::in(['LOW', 'MEDIUM', 'HIGH'])],
            'mitigation_plan' => ['nullable', 'string', 'max:3000'],
            'owner_name' => ['nullable', 'string', 'max:150'],
            'identified_date' => ['nullable', 'date'],
        ];
    }
}
