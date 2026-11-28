<?php

namespace App\Modules\Notification\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'notification_no' => $this->notification_no,
            'template_id' => $this->template_id,
            'template_code' => $this->whenLoaded('template', fn () => $this->template?->template_code),
            'category' => $this->category,
            'severity' => $this->severity,
            'priority' => $this->priority,
            'channels' => $this->channels ?? [],
            'title_bn' => $this->title_bn,
            'title_en' => $this->title_en,
            'message_bn' => $this->message_bn,
            'message_en' => $this->message_en,
            'target_type' => $this->target_type,
            'target_criteria' => $this->target_criteria ?? [],
            'administrative_unit_id' => $this->administrative_unit_id,
            'administrative_unit' => $this->whenLoaded('administrativeUnit', function () {
                return $this->administrativeUnit ? [
                    'id' => $this->administrativeUnit->id,
                    'name_bn' => $this->administrativeUnit->name_bn,
                    'name_en' => $this->administrativeUnit->name_en,
                    'tier_level' => $this->administrativeUnit->tier_level,
                ] : null;
            }),
            'related_entity_type' => $this->related_entity_type,
            'related_entity_id' => $this->related_entity_id,
            'status' => $this->status,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'dispatched_at' => $this->dispatched_at?->toIso8601String(),
            'total_recipients' => (int) $this->total_recipients,
            'delivered_count' => (int) $this->delivered_count,
            'failed_count' => (int) $this->failed_count,
            'creator' => $this->whenLoaded('creator', function () {
                return $this->creator ? [
                    'id' => $this->creator->id,
                    'full_name_bn' => $this->creator->full_name_bn,
                    'full_name_en' => $this->creator->full_name_en,
                    'role_code' => $this->creator->role_code,
                ] : null;
            }),
            'dispatches' => NotificationDispatchResource::collection($this->whenLoaded('dispatches')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
