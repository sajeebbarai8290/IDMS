<?php

namespace App\Modules\UserManagement\Requests;

use App\Core\Models\AdministrativeUnit;
use App\Modules\UserManagement\Models\Organization;
use App\Modules\UserManagement\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manageOrganizations', Organization::class);
    }

    public function rules(): array
    {
        return [
            'org_code' => ['required', 'string', 'max:40', Rule::unique(Organization::class, 'org_code')],
            'name_en' => ['required', 'string', 'max:180'],
            'name_bn' => ['nullable', 'string', 'max:220'],
            'org_type' => ['required', 'string', Rule::in(Organization::ORG_TYPES)],
            'tier_level' => ['required', 'string', Rule::in(Role::TIER_LEVELS)],
            'administrative_unit_id' => ['nullable', 'uuid', Rule::exists((new AdministrativeUnit())->getTable(), 'id')],
            'parent_organization_id' => ['nullable', 'uuid', Rule::exists((new Organization())->getTable(), 'id')],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
