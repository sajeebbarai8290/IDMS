<?php

namespace App\Modules\Notification\Requests;

use App\Modules\Notification\Models\NotificationTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNotificationTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'template_code' => ['required', 'string', 'max:80', 'regex:/^[A-Z0-9_]+$/'],
            'category' => ['required', 'string', Rule::in(NotificationTemplate::CATEGORIES)],
            'channel' => ['required', 'string', Rule::in(NotificationTemplate::CHANNELS)],
            'default_severity' => ['nullable', 'string', Rule::in(NotificationTemplate::SEVERITIES)],
            'title_bn' => ['required', 'string', 'max:250'],
            'title_en' => ['required', 'string', 'max:250'],
            'subject_bn' => ['nullable', 'string', 'max:250'],
            'subject_en' => ['nullable', 'string', 'max:250'],
            'body_bn' => ['required', 'string', 'max:5000'],
            'body_en' => ['required', 'string', 'max:5000'],
            'variables' => ['nullable', 'array'],
            'variables.*' => ['string', 'max:60'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
