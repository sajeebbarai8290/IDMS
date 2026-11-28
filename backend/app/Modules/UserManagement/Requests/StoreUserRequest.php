<?php

namespace App\Modules\UserManagement\Requests;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Modules\UserManagement\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('createUser', User::class);
    }

    public function rules(): array
    {
        return [
            'full_name_en' => ['required', 'string', 'max:150'],
            'full_name_bn' => ['nullable', 'string', 'max:200'],
            'email' => ['required', 'email', 'max:150', Rule::unique(User::class, 'email')],
            'employee_id' => ['nullable', 'string', 'max:50', Rule::unique(User::class, 'employee_id')],
            'mobile_number' => ['nullable', 'string', 'max:20'],
            'designation' => ['nullable', 'string', 'max:100'],
            'designation_bn' => ['nullable', 'string', 'max:150'],
            'organization_id' => ['nullable', 'uuid', Rule::exists((new Organization())->getTable(), 'id')],
            'department_en' => ['nullable', 'string', 'max:120'],
            'department_bn' => ['nullable', 'string', 'max:150'],
            'administrative_unit_id' => ['required', 'uuid', Rule::exists((new AdministrativeUnit())->getTable(), 'id')],
            'role_code' => ['nullable', 'string', 'max:60'],
            'role_codes' => ['required_without:role_code', 'array', 'min:1'],
            'role_codes.*' => ['string', 'max:60'],
            'account_status' => ['nullable', 'string', Rule::in(User::ACCOUNT_STATUSES)],
            'status_reason' => ['nullable', 'string', 'max:500'],
            'keycloak_sub' => ['nullable', 'string', 'max:255', Rule::unique(User::class, 'keycloak_sub')],
            'keycloak_username' => ['nullable', 'string', 'max:150'],
            'mfa_enforced' => ['nullable', 'boolean'],
            'official_order_ref' => ['nullable', 'string', 'max:100'],
            'sync_with_keycloak' => ['nullable', 'boolean'],
        ];
    }
}
