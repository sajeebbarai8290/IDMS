<?php

namespace App\Modules\Notification\Requests;

use App\Modules\Notification\Models\NotificationTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['sometimes', 'string', Rule::in(NotificationTemplate::CATEGORIES)],
            'channel' => ['sometimes', 'string', Rule::in(NotificationTemplate::CHANNELS)],
            'default_severity' => ['sometimes', 'string', Rule::in(NotificationTemplate::SEVERITIES)],
            'title_bn' => ['sometimes', 'string', 'max:250'],
            'title_en' => ['sometimes', 'string', 'max:250'],
            'subject_bn' => ['nullable', 'string', 'max:250'],
            'subject_en' => ['nullable', 'string', 'max:250'],
            'body_bn' => ['sometimes', 'string', 'max:5000'],
            'body_en' => ['sometimes', 'string', 'max:5000'],
            'variables' => ['nullable', 'array'],
            'variables.*' => ['string', 'max:60'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
