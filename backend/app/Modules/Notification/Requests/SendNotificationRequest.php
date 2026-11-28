<?php

namespace App\Modules\Notification\Requests;

use App\Modules\Notification\Models\Notification;
use App\Modules\Notification\Models\NotificationTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'template_id' => ['nullable', 'uuid'],
            'template_variables' => ['nullable', 'array'],
            'category' => ['required', 'string', Rule::in(NotificationTemplate::CATEGORIES)],
            'severity' => ['required', 'string', Rule::in(Notification::SEVERITIES)],
            'priority' => ['nullable', 'string', Rule::in(Notification::PRIORITIES)],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['required', 'string', Rule::in(Notification::CHANNELS)],
            'title_bn' => ['required_without:template_id', 'nullable', 'string', 'max:250'],
            'title_en' => ['required_without:template_id', 'nullable', 'string', 'max:250'],
            'message_bn' => ['required_without:template_id', 'nullable', 'string', 'max:5000'],
            'message_en' => ['required_without:template_id', 'nullable', 'string', 'max:5000'],
            'target_type' => ['required', 'string', Rule::in(Notification::TARGET_TYPES)],
            'target_criteria' => ['nullable', 'array'],
            'target_criteria.user_id' => ['nullable', 'uuid'],
            'target_criteria.user_ids' => ['nullable', 'array'],
            'target_criteria.user_ids.*' => ['uuid'],
            'target_criteria.role_code' => ['nullable', 'string', 'max:60'],
            'target_criteria.roles' => ['nullable', 'array'],
            'target_criteria.roles.*' => ['string', 'max:60'],
            'target_criteria.organization_id' => ['nullable', 'uuid'],
            'target_criteria.administrative_unit_id' => ['nullable', 'uuid'],
            'administrative_unit_id' => ['nullable', 'uuid'],
            'related_entity_type' => ['nullable', 'string', 'max:80'],
            'related_entity_id' => ['nullable', 'uuid'],
            'scheduled_at' => ['nullable', 'date'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
