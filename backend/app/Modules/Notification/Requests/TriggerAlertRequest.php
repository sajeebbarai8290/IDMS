<?php

namespace App\Modules\Notification\Requests;

use App\Modules\Notification\Models\Notification;
use App\Modules\Notification\Models\NotificationTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TriggerAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'alert_rule_id' => ['nullable', 'uuid'],
            'rule_code' => ['nullable', 'string', 'max:80'],
            'category' => ['nullable', 'string', Rule::in(NotificationTemplate::CATEGORIES)],
            'severity' => ['nullable', 'string', Rule::in(Notification::SEVERITIES)],
            'title_bn' => ['nullable', 'string', 'max:250'],
            'title_en' => ['nullable', 'string', 'max:250'],
            'message_bn' => ['required', 'string', 'max:5000'],
            'message_en' => ['required', 'string', 'max:5000'],
            'source_module' => ['nullable', 'string', 'max:60'],
            'source_entity_type' => ['nullable', 'string', 'max:80'],
            'source_entity_id' => ['nullable', 'uuid'],
            'metric_value' => ['nullable', 'numeric'],
            'metric_payload' => ['nullable', 'array'],
            'channels' => ['nullable', 'array'],
            'channels.*' => ['string', Rule::in(Notification::CHANNELS)],
            'target_roles' => ['nullable', 'array'],
            'target_roles.*' => ['string', 'max:60'],
            'administrative_unit_id' => ['nullable', 'uuid'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
        ];
    }
}
