<?php

namespace App\Modules\Workflow\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowNotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workflow_instance_id' => $this->workflow_instance_id,
            'instance_no' => $this->whenLoaded('instance', fn () => $this->instance?->instance_no),
            'notification_type' => $this->notification_type,
            'channel' => $this->channel,
            'recipient_user_id' => $this->recipient_user_id,
            'recipient_role_code' => $this->recipient_role_code,
            'title_en' => $this->title_en,
            'title_bn' => $this->title_bn,
            'message_en' => $this->message_en,
            'message_bn' => $this->message_bn,
            'delivery_status' => $this->delivery_status,
            'is_read' => $this->read_at !== null,
            'read_at' => $this->read_at?->toIso8601String(),
            'dispatched_at' => $this->dispatched_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
