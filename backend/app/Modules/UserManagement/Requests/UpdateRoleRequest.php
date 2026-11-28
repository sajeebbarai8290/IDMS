<?php

namespace App\Modules\UserManagement\Requests;

use App\Modules\UserManagement\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');
        return $role instanceof Role && Gate::allows('manageRoleConfiguration', $role);
    }

    public function rules(): array
    {
        return [
            'name_en' => ['sometimes', 'required', 'string', 'max:120'],
            'name_bn' => ['sometimes', 'required', 'string', 'max:160'],
            'tier_level' => ['sometimes', 'required', 'string', Rule::in(Role::TIER_LEVELS)],
            'hierarchy_rank' => ['nullable', 'integer', 'min:1', 'max:7'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'mfa_required' => ['nullable', 'boolean'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['string'],
        ];
    }
}
