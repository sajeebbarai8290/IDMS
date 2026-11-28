<?php

namespace App\Modules\Document\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_id' => $this->document_id,
            'version_number' => $this->version_number,
            'version_label' => $this->version_label,
            'change_notes' => $this->change_notes,
            'original_filename' => $this->original_filename,
            'mime_type' => $this->mime_type,
            'file_extension' => $this->file_extension,
            'file_size_bytes' => $this->file_size_bytes,
            'file_hash_sha256' => $this->file_hash_sha256,
            'scan_status' => $this->scan_status,
            'scanned_at' => $this->scanned_at?->toIso8601String(),
            'scan_engine' => $this->scan_engine,
            'is_current' => (bool) $this->is_current,
            'uploaded_by' => $this->uploaded_by,
            'uploader' => $this->whenLoaded('uploader', fn () => $this->uploader ? [
                'id' => $this->uploader->id,
                'full_name_en' => $this->uploader->full_name_en,
                'full_name_bn' => $this->uploader->full_name_bn,
                'role_code' => $this->uploader->role_code,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
