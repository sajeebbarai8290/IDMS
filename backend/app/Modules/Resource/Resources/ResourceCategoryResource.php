<?php

namespace App\Modules\Resource\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResourceCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name_en' => $this->name_en,
            'name_bn' => $this->name_bn,
            'description' => $this->description,
            'icon' => $this->icon,
            'color_hex' => $this->color_hex,
            'is_human_resource' => (bool) $this->is_human_resource,
            'is_system' => (bool) $this->is_system,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
