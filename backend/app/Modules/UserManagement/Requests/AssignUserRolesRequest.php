<?php

namespace App\Modules\UserManagement\Requests;

use App\Core\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class AssignUserRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');
        return $target instanceof User && Gate::allows('assignRoles', $target);
    }

    public function rules(): array
    {
        return [
            'role_code' => ['nullable', 'string', 'max:60'],
            'role_codes' => ['required_without:role_code', 'array', 'min:1'],
            'role_codes.*' => ['string', 'max:60'],
            'sync_with_keycloak' => ['nullable', 'boolean'],
        ];
    }
}
