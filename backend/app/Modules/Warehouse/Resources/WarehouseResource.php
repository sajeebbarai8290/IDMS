<?php

namespace App\Modules\Warehouse\Resources;

use App\Core\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isExternalPartner = $user instanceof User && $user->hasRole('ROLE_EXTERNAL_PARTNER');

        $inventories = $this->whenLoaded('inventories');
        $totalStockQty = null;
        $totalReservedQty = null;
        $totalAvailableQty = null;
        $totalValuationBdt = null;

        if ($this->relationLoaded('inventories')) {
            $totalStockQty = round((float) $this->inventories->sum('current_quantity'), 2);
            $totalReservedQty = round((float) $this->inventories->sum('reserved_quantity'), 2);
            $totalAvailableQty = round(max(0.0, $totalStockQty - $totalReservedQty), 2);
            $totalValuationBdt = round((float) $this->inventories->sum(function ($lot) {
                return (float) $lot->current_quantity * (float) ($lot->unit_cost_bdt ?? 0);
            }), 2);
        }

        return [
            'id' => $this->id,
            'warehouse_code' => $this->warehouse_code,
            'warehouse_name' => $this->warehouse_name,
            'warehouse_name_bn' => $this->warehouse_name_bn,
            'tier' => $this->tier,
            'warehouse_type' => $this->warehouse_type,
            'owning_organization' => $this->owning_organization,
            'responsible_officer_name' => $isExternalPartner ? null : $this->responsible_officer_name,
            'responsible_officer_contact' => $isExternalPartner ? null : $this->responsible_officer_contact,
            'responsible_user_id' => $isExternalPartner ? null : $this->responsible_user_id,
            'address' => $this->address,
            'contact_phone' => $isExternalPartner ? null : $this->contact_phone,
            'contact_email' => $isExternalPartner ? null : $this->contact_email,
            'capacity_mt' => (float) $this->capacity_mt,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'is_active' => (bool) $this->is_active,
            'administrative_unit_id' => $this->administrative_unit_id,
            'administrative_unit' => $this->whenLoaded('administrativeUnit', function () {
                return [
                    'id' => $this->administrativeUnit->id,
                    'code' => $this->administrativeUnit->code,
                    'name_en' => $this->administrativeUnit->name_en,
                    'name_bn' => $this->administrativeUnit->name_bn,
                    'tier_level' => $this->administrativeUnit->tier_level,
                    'ancestry_path' => $this->administrativeUnit->ancestry_path,
                ];
            }),
            'stock_summary' => $this->when($this->relationLoaded('inventories'), [
                'total_current_quantity' => $totalStockQty,
                'total_reserved_quantity' => $totalReservedQty,
                'total_available_quantity' => $totalAvailableQty,
                'total_valuation_bdt' => $totalValuationBdt,
                'active_lot_count' => $this->inventories->where('current_quantity', '>', 0)->count(),
            ]),
            'inventories' => WarehouseInventoryResource::collection($inventories),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
