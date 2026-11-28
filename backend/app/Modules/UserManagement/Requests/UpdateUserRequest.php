<?php

namespace App\Modules\UserManagement\Requests;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Modules\UserManagement\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');
        return $target instanceof User && Gate::allows('updateUser', $target);
    }

    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');

        return [
            'full_name_en' => ['sometimes', 'required', 'string', 'max:150'],
            'full_name_bn' => ['nullable', 'string', 'max:200'],
            'email' => ['sometimes', 'required', 'email', 'max:150', Rule::unique(User::class, 'email')->ignore($target->id)],
            'employee_id' => ['nullable', 'string', 'max:50', Rule::unique(User::class, 'employee_id')->ignore($target->id)],
            'mobile_number' => ['nullable', 'string', 'max:20'],
            'designation' => ['nullable', 'string', 'max:100'],
            'designation_bn' => ['nullable', 'string', 'max:150'],
            'organization_id' => ['nullable', 'uuid', Rule::exists((new Organization())->getTable(), 'id')],
            'department_en' => ['nullable', 'string', 'max:120'],
            'department_bn' => ['nullable', 'string', 'max:150'],
            'administrative_unit_id' => ['sometimes', 'required', 'uuid', Rule::exists((new AdministrativeUnit())->getTable(), 'id')],
            'role_code' => ['nullable', 'string', 'max:60'],
            'role_codes' => ['nullable', 'array', 'min:1'],
            'role_codes.*' => ['string', 'max:60'],
            'keycloak_sub' => ['nullable', 'string', 'max:255', Rule::unique(User::class, 'keycloak_sub')->ignore($target->id)],
            'keycloak_username' => ['nullable', 'string', 'max:150'],
            'mfa_enforced' => ['nullable', 'boolean'],
            'official_order_ref' => ['nullable', 'string', 'max:100'],
            'sync_with_keycloak' => ['nullable', 'boolean'],
        ];
    }
}
