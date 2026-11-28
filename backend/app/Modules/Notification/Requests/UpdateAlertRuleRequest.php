<?php

namespace App\Modules\Notification\Requests;

use App\Modules\Notification\Models\AlertRule;
use App\Modules\Notification\Models\Notification;
use App\Modules\Notification\Models\NotificationTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAlertRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name_bn' => ['sometimes', 'string', 'max:220'],
            'name_en' => ['sometimes', 'string', 'max:220'],
            'description_bn' => ['nullable', 'string', 'max:2000'],
            'description_en' => ['nullable', 'string', 'max:2000'],
            'category' => ['sometimes', 'string', Rule::in(NotificationTemplate::CATEGORIES)],
            'event_type' => ['sometimes', 'string', Rule::in(AlertRule::EVENT_TYPES)],
            'severity' => ['sometimes', 'string', Rule::in(Notification::SEVERITIES)],
            'condition_config' => ['nullable', 'array'],
            'channels' => ['sometimes', 'array', 'min:1'],
            'channels.*' => ['required', 'string', Rule::in(Notification::CHANNELS)],
            'target_roles' => ['nullable', 'array'],
            'target_roles.*' => ['string', 'max:60'],
            'escalation_delay_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'escalation_role_code' => ['nullable', 'string', 'max:60'],
            'cooldown_minutes' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
