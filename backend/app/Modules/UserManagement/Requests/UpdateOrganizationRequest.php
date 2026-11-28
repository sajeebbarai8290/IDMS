<?php

namespace App\Modules\UserManagement\Requests;

use App\Core\Models\AdministrativeUnit;
use App\Modules\UserManagement\Models\Organization;
use App\Modules\UserManagement\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $org = $this->route('organization');
        return $org instanceof Organization && Gate::allows('manageOrganizations', $org);
    }

    public function rules(): array
    {
        /** @var Organization $org */
        $org = $this->route('organization');

        return [
            'org_code' => ['sometimes', 'required', 'string', 'max:40', Rule::unique(Organization::class, 'org_code')->ignore($org->id)],
            'name_en' => ['sometimes', 'required', 'string', 'max:180'],
            'name_bn' => ['nullable', 'string', 'max:220'],
            'org_type' => ['sometimes', 'required', 'string', Rule::in(Organization::ORG_TYPES)],
            'tier_level' => ['sometimes', 'required', 'string', Rule::in(Role::TIER_LEVELS)],
            'administrative_unit_id' => ['nullable', 'uuid', Rule::exists((new AdministrativeUnit())->getTable(), 'id')],
            'parent_organization_id' => [
                'nullable',
                'uuid',
                Rule::exists((new Organization())->getTable(), 'id'),
                Rule::notIn([$org->id]),
            ],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
