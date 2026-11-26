<?php

namespace App\Modules\DamageAssessment\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DamageCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name_en' => $this->name_en,
            'name_bn' => $this->name_bn,
            'description' => $this->description,
            'default_unit' => $this->default_unit,
            'color_hex' => $this->color_hex,
            'sort_order' => (int) $this->sort_order,
            'is_system' => (bool) $this->is_system,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
