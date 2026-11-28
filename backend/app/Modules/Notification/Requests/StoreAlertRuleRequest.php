<?php

namespace App\Modules\Notification\Requests;

use App\Modules\Notification\Models\AlertRule;
use App\Modules\Notification\Models\Notification;
use App\Modules\Notification\Models\NotificationTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAlertRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rule_code' => ['required', 'string', 'max:80', 'regex:/^[A-Z0-9_-]+$/'],
            'name_bn' => ['required', 'string', 'max:220'],
            'name_en' => ['required', 'string', 'max:220'],
            'description_bn' => ['nullable', 'string', 'max:2000'],
            'description_en' => ['nullable', 'string', 'max:2000'],
            'category' => ['required', 'string', Rule::in(NotificationTemplate::CATEGORIES)],
            'event_type' => ['required', 'string', Rule::in(AlertRule::EVENT_TYPES)],
            'severity' => ['required', 'string', Rule::in(Notification::SEVERITIES)],
            'condition_config' => ['nullable', 'array'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['required', 'string', Rule::in(Notification::CHANNELS)],
            'target_roles' => ['nullable', 'array'],
            'target_roles.*' => ['string', 'max:60'],
            'escalation_delay_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'escalation_role_code' => ['nullable', 'string', 'max:60'],
            'cooldown_minutes' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'administrative_unit_id' => ['nullable', 'uuid'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
