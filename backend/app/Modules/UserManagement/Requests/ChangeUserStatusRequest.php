<?php

namespace App\Modules\UserManagement\Requests;

use App\Core\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ChangeUserStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');
        return $target instanceof User && Gate::allows('changeStatus', $target);
    }

    public function rules(): array
    {
        return [
            'account_status' => ['required', 'string', Rule::in(User::ACCOUNT_STATUSES)],
            'reason' => ['nullable', 'string', 'max:500'],
            'sync_with_keycloak' => ['nullable', 'boolean'],
        ];
    }
}
