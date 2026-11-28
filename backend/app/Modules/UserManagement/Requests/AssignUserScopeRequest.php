<?php

namespace App\Modules\UserManagement\Requests;

use App\Core\Models\User;
use App\Modules\UserManagement\Models\UserAdministrativeScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AssignUserScopeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');
        return $target instanceof User && Gate::allows('assignScope', $target);
    }

    public function rules(): array
    {
        return [
            'administrative_unit_id' => ['required', 'uuid'],
            'assignment_type' => ['required', 'string', Rule::in(UserAdministrativeScope::ASSIGNMENT_TYPES)],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after:valid_from'],
            'official_order_ref' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
