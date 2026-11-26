<?php

namespace App\Modules\Warehouse\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseInventoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currentQty = (float) $this->current_quantity;
        $reservedQty = (float) $this->reserved_quantity;
        $unitCost = $this->unit_cost_bdt !== null ? (float) $this->unit_cost_bdt : null;
        $daysToExpiry = $this->expiry_date ? now()->startOfDay()->diffInDays($this->expiry_date->startOfDay(), false) : null;

        return [
            'id' => $this->id,
            'warehouse_id' => $this->warehouse_id,
            'commodity_id' => $this->commodity_id,
            'batch_number' => $this->batch_number,
            'opening_quantity' => (float) ($this->opening_quantity ?? 0),
            'current_quantity' => $currentQty,
            'reserved_quantity' => $reservedQty,
            'available_quantity' => max(0.0, round($currentQty - $reservedQty, 2)),
            'damaged_quantity' => (float) ($this->damaged_quantity ?? 0),
            'expired_quantity' => (float) ($this->expired_quantity ?? 0),
            'unit_cost_bdt' => $unitCost,
            'valuation_bdt' => $unitCost !== null ? round($currentQty * $unitCost, 2) : null,
            'received_date' => $this->received_date?->toDateString(),
            'expiry_date' => $this->expiry_date?->toDateString(),
            'days_to_expiry' => $daysToExpiry,
            'status' => $this->status ?? 'ACTIVE',
            'notes' => $this->notes,
            'warehouse' => $this->whenLoaded('warehouse', function () {
                return [
                    'id' => $this->warehouse->id,
                    'warehouse_code' => $this->warehouse->warehouse_code,
                    'warehouse_name' => $this->warehouse->warehouse_name,
                    'warehouse_name_bn' => $this->warehouse->warehouse_name_bn,
                    'tier' => $this->warehouse->tier,
                ];
            }),
            'commodity' => $this->whenLoaded('commodity', function () {
                return [
                    'id' => $this->commodity->id,
                    'commodity_code' => $this->commodity->commodity_code,
                    'name_en' => $this->commodity->name_en,
                    'name_bn' => $this->commodity->name_bn,
                    'category' => $this->commodity->category,
                    'unit_of_measure' => $this->commodity->unit_of_measure,
                    'minimum_stock_level' => (float) ($this->commodity->minimum_stock_level ?? 0),
                    'is_perishable' => (bool) $this->commodity->is_perishable,
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
