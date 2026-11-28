<?php

namespace App\Modules\Document\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentAccessLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_id' => $this->document_id,
            'document_version_id' => $this->document_version_id,
            'action' => $this->action,
            'ip_address' => $this->ip_address,
            'details' => $this->details,
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'full_name_en' => $this->user->full_name_en,
                'full_name_bn' => $this->user->full_name_bn,
                'role_code' => $this->user->role_code,
            ] : null),
            'version' => $this->whenLoaded('version', fn () => $this->version ? [
                'id' => $this->version->id,
                'version_number' => $this->version->version_number,
                'version_label' => $this->version->version_label,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
