<?php

namespace App\Modules\UserManagement\Requests;

use App\Modules\UserManagement\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manageRoleConfiguration', Role::class);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9_]+$/'],
            'name_en' => ['required', 'string', 'max:120'],
            'name_bn' => ['required', 'string', 'max:160'],
            'tier_level' => ['required', 'string', Rule::in(Role::TIER_LEVELS)],
            'hierarchy_rank' => ['nullable', 'integer', 'min:1', 'max:7'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'mfa_required' => ['nullable', 'boolean'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['string'],
        ];
    }
}
