<?php

namespace App\Modules\DamageAssessment\Requests;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Modules\DamageAssessment\Models\DamageAssessment;
use App\Modules\DamageAssessment\Models\DamageCategory;
use App\Modules\Disaster\Models\DisasterEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreDamageAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $existing = $this->route('assessment');
        if ($existing instanceof DamageAssessment) {
            return Gate::allows('updateAssessment', $existing);
        }

        return Gate::allows('createAssessment', DamageAssessment::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->boundary_geojson)) {
            $decoded = json_decode($this->boundary_geojson, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['boundary_geojson' => $decoded]);
            }
        }
        if (is_string($this->team_members)) {
            $decoded = json_decode($this->team_members, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['team_members' => $decoded]);
            }
        }
    }

    public function rules(): array
    {
        $existing = $this->route('assessment');
        $ignoreId = $existing instanceof DamageAssessment ? $existing->id : null;

        $assessmentsTable = (new DamageAssessment())->getTable();
        $categoriesTable = (new DamageCategory())->getTable();
        $adminTable = (new AdministrativeUnit())->getTable();
        $disasterTable = (new DisasterEvent())->getTable();
        $usersTable = (new User())->getTable();

        return [
            'disaster_event_id' => ['required', 'uuid', "exists:{$disasterTable},id"],
            'administrative_unit_id' => ['required', 'uuid', "exists:{$adminTable},id"],
            'd_form_code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique($assessmentsTable, 'd_form_code')->ignore($ignoreId),
            ],
            'reference_no' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique($assessmentsTable, 'reference_no')->ignore($ignoreId),
            ],
            'assessment_type' => ['required', 'string', Rule::in(DamageAssessment::ASSESSMENT_TYPES)],
            'assessment_date' => ['required', 'date'],
            'assessed_at' => ['nullable', 'date'],
            'assessing_organization' => ['required', 'string', 'max:180'],
            'team_name' => ['nullable', 'string', 'max:150'],
            'team_lead_name' => ['nullable', 'string', 'max:150'],
            'team_members' => ['nullable', 'array'],
            'team_members.*.name' => ['required_with:team_members', 'string', 'max:120'],
            'team_members.*.designation' => ['nullable', 'string', 'max:100'],
            'team_members.*.role' => ['nullable', 'string', 'max:80'],
            'team_members.*.contact' => ['nullable', 'string', 'max:40'],
            'assessor_user_id' => ['nullable', 'uuid', "exists:{$usersTable},id"],
            'assessor_name' => ['nullable', 'string', 'max:150'],
            'assessor_designation' => ['nullable', 'string', 'max:120'],
            'assessor_contact' => ['nullable', 'string', 'max:60'],
            'ward_no' => ['nullable', 'string', 'max:40'],
            'location_summary' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'boundary_geojson' => ['nullable', 'array'],
            'severity_level' => ['nullable', 'string', Rule::in(DamageAssessment::SEVERITY_LEVELS)],
            'affected_households' => ['required', 'integer', 'min:0'],
            'affected_population' => ['required', 'integer', 'min:0'],
            'displaced_population' => ['nullable', 'integer', 'min:0'],
            'deaths_count' => ['nullable', 'integer', 'min:0'],
            'injured_count' => ['nullable', 'integer', 'min:0'],
            'missing_count' => ['nullable', 'integer', 'min:0'],
            'total_estimated_loss_bdt' => ['nullable', 'numeric', 'min:0'],
            'field_observations' => ['nullable', 'string', 'max:5000'],
            'submit_immediately' => ['nullable', 'boolean'],
            'sync_version' => ['nullable', 'integer', 'min:1'],
            'update_notes' => ['nullable', 'string', 'max:500'],

            // Offline sync compatibility
            'client_uuid' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique($assessmentsTable, 'client_uuid')->ignore($ignoreId),
            ],
            'idempotency_key' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique($assessmentsTable, 'idempotency_key')->ignore($ignoreId),
            ],
            'device_id' => ['nullable', 'string', 'max:100'],
            'offline_created_at' => ['nullable', 'date'],

            // Coverage locations
            'locations' => ['nullable', 'array'],
            'locations.*.administrative_unit_id' => ['required', 'uuid', "exists:{$adminTable},id"],
            'locations.*.ward_no' => ['nullable', 'string', 'max:40'],
            'locations.*.locality_name_en' => ['nullable', 'string', 'max:180'],
            'locations.*.locality_name_bn' => ['nullable', 'string', 'max:200'],
            'locations.*.affected_households' => ['nullable', 'integer', 'min:0'],
            'locations.*.affected_population' => ['nullable', 'integer', 'min:0'],
            'locations.*.severity_level' => ['nullable', 'string', Rule::in(DamageAssessment::SEVERITY_LEVELS)],
            'locations.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'locations.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'locations.*.notes' => ['nullable', 'string', 'max:1000'],

            // Sectoral damage items
            'sector_items' => ['required', 'array', 'min:1'],
            'sector_items.*.category_id' => ['required_without:sector_items.*.sector_code', 'uuid', "exists:{$categoriesTable},id"],
            'sector_items.*.sector_code' => ['required_without:sector_items.*.category_id', 'string', 'max:50'],
            'sector_items.*.item_title' => ['nullable', 'string', 'max:180'],
            'sector_items.*.unit_of_measure' => ['nullable', 'string', 'max:40'],
            'sector_items.*.fully_damaged_count' => ['required', 'integer', 'min:0'],
            'sector_items.*.partially_damaged_count' => ['required', 'integer', 'min:0'],
            'sector_items.*.minor_damaged_count' => ['nullable', 'integer', 'min:0'],
            'sector_items.*.damaged_quantity' => ['nullable', 'numeric', 'min:0'],
            'sector_items.*.affected_households' => ['nullable', 'integer', 'min:0'],
            'sector_items.*.affected_population' => ['nullable', 'integer', 'min:0'],
            'sector_items.*.estimated_loss_bdt' => ['required', 'numeric', 'min:0'],
            'sector_items.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'sector_items.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'sector_items.*.remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
