<?php

namespace App\Modules\Warehouse\Resources;

use App\Core\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isExternalPartner = $user instanceof User && $user->hasRole('ROLE_EXTERNAL_PARTNER');

        return [
            'id' => $this->id,
            'warehouse_inventory_id' => $this->warehouse_inventory_id,
            'warehouse_id' => $this->warehouse_id ?? $this->warehouseInventory?->warehouse_id,
            'commodity_id' => $this->commodity_id ?? $this->warehouseInventory?->commodity_id,
            'stock_receipt_id' => $this->stock_receipt_id,
            'warehouse_transfer_id' => $this->warehouse_transfer_id,
            'transaction_type' => $this->transaction_type,
            'reference_type' => $this->reference_type,
            'reason_code' => $this->reason_code,
            'quantity' => (float) $this->quantity,
            'balance_after' => (float) $this->balance_after,
            'reference_voucher_no' => $this->reference_voucher_no,
            'idempotency_key' => $this->idempotency_key,
            'relief_allocation_item_id' => $this->relief_allocation_item_id,
            'distribution_voucher_id' => $this->distribution_voucher_id,
            'notes' => $this->notes,
            'batch_number' => $this->warehouseInventory?->batch_number,
            'warehouse' => $this->when($this->relationLoaded('warehouse') || $this->warehouseInventory?->relationLoaded('warehouse'), function () {
                $wh = $this->relationLoaded('warehouse') && $this->warehouse
                    ? $this->warehouse
                    : $this->warehouseInventory?->warehouse;

                return $wh ? [
                    'id' => $wh->id,
                    'warehouse_code' => $wh->warehouse_code,
                    'warehouse_name' => $wh->warehouse_name,
                    'warehouse_name_bn' => $wh->warehouse_name_bn,
                ] : null;
            }),
            'commodity' => $this->when($this->relationLoaded('commodity') || $this->warehouseInventory?->relationLoaded('commodity'), function () {
                $com = $this->relationLoaded('commodity') && $this->commodity
                    ? $this->commodity
                    : $this->warehouseInventory?->commodity;

                return $com ? [
                    'id' => $com->id,
                    'commodity_code' => $com->commodity_code,
                    'name_en' => $com->name_en,
                    'name_bn' => $com->name_bn,
                    'unit_of_measure' => $com->unit_of_measure,
                ] : null;
            }),
            'authorized_by' => $isExternalPartner ? null : $this->authorized_by,
            'authorizer_name' => $isExternalPartner ? null : $this->authorizer?->full_name_en,
            'approved_by' => $isExternalPartner ? null : $this->approved_by,
            'approver_name' => $isExternalPartner ? null : $this->approver?->full_name_en,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
