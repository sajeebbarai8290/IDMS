<?php

namespace App\Modules\Disaster\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DisasterTimelineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'disaster_event_id' => $this->disaster_event_id,
            'event_type' => $this->event_type,
            'from_phase' => $this->from_phase,
            'to_phase' => $this->to_phase,
            'title_en' => $this->title_en,
            'title_bn' => $this->title_bn,
            'notes' => $this->notes,
            'has_attachment' => (bool) $this->attachment_path,
            'attachment_original_name' => $this->attachment_original_name,
            'attachment_mime' => $this->attachment_mime,
            'attachment_size_bytes' => $this->attachment_size_bytes,
            'attachment_download_url' => $this->attachment_path
                ? "/api/v1/disaster/events/{$this->disaster_event_id}/timelines/{$this->id}/attachment"
                : null,
            'recorded_by' => $this->recorder?->full_name_en ?? 'System',
            'occurred_at' => $this->occurred_at?->toIso8601String(),
        ];
    }
}
