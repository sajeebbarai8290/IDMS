<?php

namespace App\Modules\Notification\Resources;

use App\Modules\Notification\Models\NotificationDispatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationDispatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'notification_id' => $this->notification_id,
            'notification_no' => $this->whenLoaded('notification', fn () => $this->notification?->notification_no),
            'category' => $this->whenLoaded('notification', fn () => $this->notification?->category),
            'severity' => $this->whenLoaded('notification', fn () => $this->notification?->severity),
            'priority' => $this->whenLoaded('notification', fn () => $this->notification?->priority),
            'recipient_user_id' => $this->recipient_user_id,
            'recipient_summary' => $this->whenLoaded('recipient', function () {
                return $this->recipient ? [
                    'id' => $this->recipient->id,
                    'full_name_bn' => $this->recipient->full_name_bn,
                    'full_name_en' => $this->recipient->full_name_en,
                    'role_code' => $this->recipient->role_code,
                ] : null;
            }),
            'administrative_unit' => $this->whenLoaded('administrativeUnit', function () {
                return $this->administrativeUnit ? [
                    'id' => $this->administrativeUnit->id,
                    'name_bn' => $this->administrativeUnit->name_bn,
                    'name_en' => $this->administrativeUnit->name_en,
                ] : null;
            }),
            'channel' => $this->channel,
            'masked_destination' => NotificationDispatch::maskDestination($this->destination_address, $this->channel),
            'title_bn' => $this->title_bn,
            'title_en' => $this->title_en,
            'message_body' => $this->message_body,
            'message_body_bn' => $this->message_body_bn,
            'message_body_en' => $this->message_body_en,
            'delivery_status' => $this->delivery_status,
            'provider_name' => $this->provider_name,
            'provider_reference' => $this->provider_reference,
            'attempt_count' => (int) $this->attempt_count,
            'last_error' => $this->last_error,
            'is_read' => $this->read_at !== null,
            'read_at' => $this->read_at?->toIso8601String(),
            'dispatched_at' => $this->dispatched_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
