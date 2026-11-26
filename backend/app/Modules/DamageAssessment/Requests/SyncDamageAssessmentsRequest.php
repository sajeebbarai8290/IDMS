<?php

namespace App\Modules\DamageAssessment\Requests;

use App\Core\Models\AdministrativeUnit;
use App\Modules\DamageAssessment\Models\DamageAssessment;
use App\Modules\DamageAssessment\Models\DamageCategory;
use App\Modules\Disaster\Models\DisasterEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SyncDamageAssessmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('createAssessment', DamageAssessment::class);
    }

    public function rules(): array
    {
        $adminTable = (new AdministrativeUnit())->getTable();
        $disasterTable = (new DisasterEvent())->getTable();
        $categoriesTable = (new DamageCategory())->getTable();

        return [
            'records' => ['required', 'array', 'min:1', 'max:50'],
            'records.*.client_uuid' => ['required', 'string', 'max:64'],
            'records.*.idempotency_key' => ['nullable', 'string', 'max:80'],
            'records.*.device_id' => ['nullable', 'string', 'max:100'],
            'records.*.offline_created_at' => ['nullable', 'date'],
            'records.*.sync_version' => ['nullable', 'integer', 'min:1'],
            'records.*.disaster_event_id' => ['required', 'uuid', "exists:{$disasterTable},id"],
            'records.*.administrative_unit_id' => ['required', 'uuid', "exists:{$adminTable},id"],
            'records.*.assessment_type' => ['required', 'string', Rule::in(DamageAssessment::ASSESSMENT_TYPES)],
            'records.*.assessment_date' => ['required', 'date'],
            'records.*.assessed_at' => ['nullable', 'date'],
            'records.*.assessing_organization' => ['required', 'string', 'max:180'],
            'records.*.team_name' => ['nullable', 'string', 'max:150'],
            'records.*.assessor_name' => ['nullable', 'string', 'max:150'],
            'records.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'records.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'records.*.severity_level' => ['nullable', 'string', Rule::in(DamageAssessment::SEVERITY_LEVELS)],
            'records.*.affected_households' => ['required', 'integer', 'min:0'],
            'records.*.affected_population' => ['required', 'integer', 'min:0'],
            'records.*.field_observations' => ['nullable', 'string', 'max:5000'],
            'records.*.submit_immediately' => ['nullable', 'boolean'],
            'records.*.sector_items' => ['required', 'array', 'min:1'],
            'records.*.sector_items.*.category_id' => ['required_without:records.*.sector_items.*.sector_code', 'uuid', "exists:{$categoriesTable},id"],
            'records.*.sector_items.*.sector_code' => ['required_without:records.*.sector_items.*.category_id', 'string', 'max:50'],
            'records.*.sector_items.*.fully_damaged_count' => ['required', 'integer', 'min:0'],
            'records.*.sector_items.*.partially_damaged_count' => ['required', 'integer', 'min:0'],
            'records.*.sector_items.*.minor_damaged_count' => ['nullable', 'integer', 'min:0'],
            'records.*.sector_items.*.estimated_loss_bdt' => ['required', 'numeric', 'min:0'],
        ];
    }
}
