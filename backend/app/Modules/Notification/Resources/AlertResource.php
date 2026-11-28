<?php

namespace App\Modules\Notification\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AlertResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'alert_no' => $this->alert_no,
            'alert_rule_id' => $this->alert_rule_id,
            'rule' => $this->whenLoaded('rule', function () {
                return $this->rule ? [
                    'id' => $this->rule->id,
                    'rule_code' => $this->rule->rule_code,
                    'name_bn' => $this->rule->name_bn,
                    'name_en' => $this->rule->name_en,
                    'escalation_delay_minutes' => (int) $this->rule->escalation_delay_minutes,
                    'escalation_role_code' => $this->rule->escalation_role_code,
                ] : null;
            }),
            'category' => $this->category,
            'severity' => $this->severity,
            'status' => $this->status,
            'title_bn' => $this->title_bn,
            'title_en' => $this->title_en,
            'message_bn' => $this->message_bn,
            'message_en' => $this->message_en,
            'source_module' => $this->source_module,
            'source_entity_type' => $this->source_entity_type,
            'source_entity_id' => $this->source_entity_id,
            'metric_payload' => $this->metric_payload,
            'administrative_unit_id' => $this->administrative_unit_id,
            'administrative_unit' => $this->whenLoaded('administrativeUnit', function () {
                return $this->administrativeUnit ? [
                    'id' => $this->administrativeUnit->id,
                    'name_bn' => $this->administrativeUnit->name_bn,
                    'name_en' => $this->administrativeUnit->name_en,
                    'tier_level' => $this->administrativeUnit->tier_level,
                ] : null;
            }),
            'triggered_at' => $this->triggered_at?->toIso8601String(),
            'is_acknowledged' => $this->acknowledged_at !== null,
            'acknowledged_at' => $this->acknowledged_at?->toIso8601String(),
            'acknowledgement_notes' => $this->acknowledgement_notes,
            'acknowledged_by_user' => $this->whenLoaded('acknowledgedByUser', function () {
                return $this->acknowledgedByUser ? [
                    'id' => $this->acknowledgedByUser->id,
                    'full_name_bn' => $this->acknowledgedByUser->full_name_bn,
                    'full_name_en' => $this->acknowledgedByUser->full_name_en,
                ] : null;
            }),
            'escalated_at' => $this->escalated_at?->toIso8601String(),
            'escalated_to_role' => $this->escalated_to_role,
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'resolution_notes' => $this->resolution_notes,
            'resolved_by_user' => $this->whenLoaded('resolvedByUser', function () {
                return $this->resolvedByUser ? [
                    'id' => $this->resolvedByUser->id,
                    'full_name_bn' => $this->resolvedByUser->full_name_bn,
                    'full_name_en' => $this->resolvedByUser->full_name_en,
                ] : null;
            }),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
