<?php

namespace App\Modules\Notification\Requests;

use App\Modules\Notification\Models\NotificationTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'uuid'],
            'preferences' => ['required', 'array', 'min:1'],
            'preferences.*.category' => ['required', 'string', Rule::in(NotificationTemplate::CATEGORIES)],
            'preferences.*.in_app_enabled' => ['nullable', 'boolean'],
            'preferences.*.email_enabled' => ['nullable', 'boolean'],
            'preferences.*.sms_enabled' => ['nullable', 'boolean'],
            'preferences.*.push_enabled' => ['nullable', 'boolean'],
            'preferences.*.quiet_hours_start' => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
            'preferences.*.quiet_hours_end' => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
            'preferences.*.policy_locked' => ['nullable', 'boolean'],
        ];
    }
}
