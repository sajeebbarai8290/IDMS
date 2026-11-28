<?php

namespace App\Modules\Notification\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AlertRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rule_code' => $this->rule_code,
            'name_bn' => $this->name_bn,
            'name_en' => $this->name_en,
            'description_bn' => $this->description_bn,
            'description_en' => $this->description_en,
            'category' => $this->category,
            'event_type' => $this->event_type,
            'severity' => $this->severity,
            'condition_config' => $this->condition_config ?? [],
            'channels' => $this->channels ?? [],
            'target_roles' => $this->target_roles ?? [],
            'escalation_delay_minutes' => (int) $this->escalation_delay_minutes,
            'escalation_role_code' => $this->escalation_role_code,
            'cooldown_minutes' => (int) $this->cooldown_minutes,
            'administrative_unit_id' => $this->administrative_unit_id,
            'administrative_unit' => $this->whenLoaded('administrativeUnit', function () {
                return $this->administrativeUnit ? [
                    'id' => $this->administrativeUnit->id,
                    'name_bn' => $this->administrativeUnit->name_bn,
                    'name_en' => $this->administrativeUnit->name_en,
                    'tier_level' => $this->administrativeUnit->tier_level,
                ] : null;
            }),
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
