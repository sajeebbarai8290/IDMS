<?php

namespace App\Modules\Notification\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationDeviceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'device_uuid' => $this->device_uuid,
            'platform' => $this->platform,
            'push_provider' => $this->push_provider,
            'device_name' => $this->device_name,
            'app_version' => $this->app_version,
            'locale' => $this->locale,
            'is_active' => (bool) $this->is_active,
            'token_fingerprint' => $this->push_token_hash ? substr($this->push_token_hash, 0, 12) : null,
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
