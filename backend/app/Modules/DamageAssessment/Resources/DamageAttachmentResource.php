<?php

namespace App\Modules\DamageAssessment\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DamageAttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'damage_assessment_id' => $this->damage_assessment_id,
            'damage_sector_item_id' => $this->damage_sector_item_id,
            'attachment_kind' => $this->attachment_kind,
            'title' => $this->title,
            'caption' => $this->caption,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'file_size_bytes' => (int) $this->file_size_bytes,
            'sha256_hash' => $this->sha256_hash,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'captured_at' => $this->captured_at?->toIso8601String(),
            'uploaded_by' => $this->uploaded_by,
            'uploader_name' => $this->whenLoaded('uploader', fn () => $this->uploader?->full_name_en),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
