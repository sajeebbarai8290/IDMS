<?php

namespace App\Modules\Volunteer\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SkillCatalogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'skill_code' => $this->skill_code,
            'name_en' => $this->name_en,
            'name_bn' => $this->name_bn,
            'description' => $this->description,
            'requires_certification' => $this->requires_certification,
            'is_system' => $this->is_system,
            'is_active' => $this->is_active,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'code' => $this->category->code,
                'name_en' => $this->category->name_en,
                'name_bn' => $this->category->name_bn,
            ]),
        ];
    }
}
