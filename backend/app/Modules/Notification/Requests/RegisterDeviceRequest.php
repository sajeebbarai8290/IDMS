<?php

namespace App\Modules\Notification\Requests;

use App\Modules\Notification\Models\NotificationDevice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_uuid' => ['required', 'string', 'max:120'],
            'platform' => ['required', 'string', Rule::in(NotificationDevice::PLATFORMS)],
            'push_provider' => ['nullable', 'string', Rule::in(NotificationDevice::PUSH_PROVIDERS)],
            'push_token' => ['required', 'string', 'min:12', 'max:2048'],
            'device_name' => ['nullable', 'string', 'max:150'],
            'app_version' => ['nullable', 'string', 'max:40'],
            'locale' => ['nullable', 'string', Rule::in(['bn', 'en'])],
        ];
    }
}
