<?php

namespace App\Modules\Project\Requests;

use App\Core\Models\AdministrativeUnit;
use App\Modules\Project\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreProjectMonitoringReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Project $project */
        $project = $this->route('project');
        return Gate::allows('createMonitoring', $project);
    }

    public function rules(): array
    {
        $adminTable = (new AdministrativeUnit())->getTable();

        return [
            'administrative_unit_id' => ['nullable', 'uuid', "exists:{$adminTable},id"],
            'visit_date' => ['required', 'date'],
            'inspector_name' => ['nullable', 'string', 'max:150'],
            'inspector_designation' => ['nullable', 'string', 'max:120'],
            'observed_physical_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'observed_financial_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'delay_observed' => ['nullable', 'boolean'],
            'delay_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'delay_reason' => ['nullable', 'string', 'max:2000'],
            'findings' => ['required', 'string', 'max:4000'],
            'recommendations' => ['nullable', 'string', 'max:3000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
        ];
    }
}
