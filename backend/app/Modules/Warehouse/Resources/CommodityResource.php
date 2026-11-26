<?php

namespace App\Modules\Warehouse\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommodityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'relief_category_id' => $this->relief_category_id,
            'relief_category' => $this->whenLoaded('reliefCategory', function () {
                return [
                    'id' => $this->reliefCategory->id,
                    'code' => $this->reliefCategory->code,
                    'name_en' => $this->reliefCategory->name_en,
                    'name_bn' => $this->reliefCategory->name_bn,
                ];
            }),
            'commodity_code' => $this->commodity_code,
            'name_en' => $this->name_en,
            'name_bn' => $this->name_bn,
            'category' => $this->category,
            'unit_of_measure' => $this->unit_of_measure,
            'description' => $this->description,
            'minimum_stock_level' => (float) ($this->minimum_stock_level ?? 0),
            'is_perishable' => (bool) $this->is_perishable,
            'is_active' => (bool) $this->is_active,
            'total_current_quantity' => isset($this->total_current_quantity) ? (float) $this->total_current_quantity : null,
            'total_reserved_quantity' => isset($this->total_reserved_quantity) ? (float) $this->total_reserved_quantity : null,
            'total_available_quantity' => isset($this->total_available_quantity) ? (float) $this->total_available_quantity : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
