<?php

namespace App\Modules\Response\Requests;

use App\Core\Models\AdministrativeUnit;
use App\Modules\Response\Models\ResponseOperation;
use App\Modules\Response\Models\ResponseSituationReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreSituationReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $operation = $this->route('operation');
        return $operation instanceof ResponseOperation && Gate::allows('createReport', $operation);
    }

    public function rules(): array
    {
        $adminTable = (new AdministrativeUnit())->getTable();

        return [
            'administrative_unit_id' => ['nullable', 'uuid', "exists:{$adminTable},id"],
            'report_type' => ['required', 'string', Rule::in(ResponseSituationReport::REPORT_TYPES)],
            'severity_level' => ['nullable', 'string', Rule::in(ResponseOperation::SEVERITIES)],
            'title_en' => ['required', 'string', 'max:180'],
            'title_bn' => ['nullable', 'string', 'max:220'],
            'summary' => ['required', 'string', 'max:4000'],
            'casualties_injured' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'casualties_missing' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'casualties_fatalities' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'rescued_count' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'evacuated_count' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'emergency_requirements' => ['nullable', 'string', 'max:2000'],
            'location_description' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'privacy_level' => ['nullable', 'string', Rule::in(ResponseSituationReport::PRIVACY_LEVELS)],
        ];
    }
}
