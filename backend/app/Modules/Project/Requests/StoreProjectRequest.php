<?php

namespace App\Modules\Project\Requests;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Modules\Disaster\Models\DisasterEvent;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $existing = $this->route('project');
        if ($existing instanceof Project) {
            return Gate::allows('updateProject', $existing);
        }

        return Gate::allows('createProject', Project::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->boundary_geojson)) {
            $decoded = json_decode($this->boundary_geojson, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['boundary_geojson' => $decoded]);
            }
        }
    }

    public function rules(): array
    {
        $existing = $this->route('project');
        $ignoreId = $existing instanceof Project ? $existing->id : null;

        $projectsTable = (new Project())->getTable();
        $categoriesTable = (new ProjectCategory())->getTable();
        $adminTable = (new AdministrativeUnit())->getTable();
        $disasterTable = (new DisasterEvent())->getTable();
        $usersTable = (new User())->getTable();

        return [
            'category_id' => ['required', 'uuid', "exists:{$categoriesTable},id"],
            'disaster_event_id' => ['nullable', 'uuid', "exists:{$disasterTable},id"],
            'administrative_unit_id' => ['required', 'uuid', "exists:{$adminTable},id"],
            'project_code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique($projectsTable, 'project_code')->ignore($ignoreId),
            ],
            'reference_no' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique($projectsTable, 'reference_no')->ignore($ignoreId),
            ],
            'title_en' => ['required', 'string', 'max:220'],
            'title_bn' => ['required', 'string', 'max:260'],
            'description' => ['nullable', 'string', 'max:5000'],
            'objectives' => ['nullable', 'string', 'max:5000'],
            'implementing_organization' => ['required', 'string', 'max:180'],
            'project_manager_name' => ['required', 'string', 'max:150'],
            'project_manager_contact' => ['nullable', 'string', 'max:60'],
            'project_manager_user_id' => ['nullable', 'uuid', "exists:{$usersTable},id"],
            'funding_source' => ['required', 'string', 'max:150'],
            'approved_budget_bdt' => ['required', 'numeric', 'min:1'],
            'revised_budget_bdt' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['required', 'date'],
            'expected_end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'ward_no' => ['nullable', 'string', 'max:40'],
            'location_summary' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'boundary_geojson' => ['nullable', 'array'],
            'status_notes' => ['nullable', 'string', 'max:2000'],
            'is_delayed' => ['nullable', 'boolean'],
            'delay_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'delay_reason' => ['nullable', 'string', 'max:2000'],
            'locations' => ['nullable', 'array'],
            'locations.*.administrative_unit_id' => ['required_with:locations', 'uuid', "exists:{$adminTable},id"],
            'locations.*.ward_no' => ['nullable', 'string', 'max:40'],
            'locations.*.site_name_en' => ['nullable', 'string', 'max:180'],
            'locations.*.site_name_bn' => ['nullable', 'string', 'max:200'],
            'locations.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'locations.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'locations.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
