<?php

namespace App\Modules\Project\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name_en' => $this->name_en,
            'name_bn' => $this->name_bn,
            'description' => $this->description,
            'color_hex' => $this->color_hex,
            'is_system' => (bool) $this->is_system,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
