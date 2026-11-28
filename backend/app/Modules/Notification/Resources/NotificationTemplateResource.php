<?php

namespace App\Modules\Notification\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'template_code' => $this->template_code,
            'category' => $this->category,
            'channel' => $this->channel,
            'default_severity' => $this->default_severity,
            'title_bn' => $this->title_bn,
            'title_en' => $this->title_en,
            'subject_bn' => $this->subject_bn,
            'subject_en' => $this->subject_en,
            'body_bn' => $this->body_bn,
            'body_en' => $this->body_en,
            'variables' => $this->variables ?? [],
            'is_system' => (bool) $this->is_system,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
