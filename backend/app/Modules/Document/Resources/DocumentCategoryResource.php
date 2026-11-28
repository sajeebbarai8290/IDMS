<?php

namespace App\Modules\Document\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name_en' => $this->name_en,
            'name_bn' => $this->name_bn,
            'description_en' => $this->description_en,
            'description_bn' => $this->description_bn,
            'default_classification' => $this->default_classification,
            'allowed_formats' => $this->allowed_formats ?? [],
            'retention_days' => $this->retention_days,
            'requires_approval' => (bool) $this->requires_approval,
            'is_system' => (bool) $this->is_system,
            'is_active' => (bool) $this->is_active,
            'sort_order' => $this->sort_order,
            'documents_count' => $this->whenCounted('documents'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
