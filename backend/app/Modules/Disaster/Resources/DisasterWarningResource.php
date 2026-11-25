<?php

namespace App\Modules\Disaster\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DisasterWarningResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'disaster_event_id' => $this->disaster_event_id,
            'warning_code' => $this->warning_code,
            'warning_level' => $this->warning_level,
            'title_en' => $this->title_en,
            'title_bn' => $this->title_bn,
            'message_en' => $this->message_en,
            'message_bn' => $this->message_bn,
            'issuing_authority' => $this->issuing_authority,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'affected_unit_ids' => $this->affected_unit_ids ?? [],
            'status' => $this->status,
            'notification_dispatch_status' => $this->notification_dispatch_status,
            'notification_reference' => $this->notification_reference,
            'issued_by' => $this->issuer?->full_name_en,
        ];
    }
}
