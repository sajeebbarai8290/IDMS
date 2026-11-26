<?php

namespace App\Modules\Warehouse\Resources;

use App\Core\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isExternalPartner = $user instanceof User && $user->hasRole('ROLE_EXTERNAL_PARTNER');

        return [
            'id' => $this->id,
            'receipt_no' => $this->receipt_no,
            'warehouse_id' => $this->warehouse_id,
            'warehouse' => $this->whenLoaded('warehouse', function () {
                return [
                    'id' => $this->warehouse->id,
                    'warehouse_code' => $this->warehouse->warehouse_code,
                    'warehouse_name' => $this->warehouse->warehouse_name,
                    'warehouse_name_bn' => $this->warehouse->warehouse_name_bn,
                    'tier' => $this->warehouse->tier,
                ];
            }),
            'supplier_name' => $this->supplier_name,
            'supplier_contact' => $isExternalPartner ? null : $this->supplier_contact,
            'purchase_order_no' => $this->purchase_order_no,
            'received_date' => $this->received_date?->toDateString(),
            'status' => $this->status,
            'verification_notes' => $this->verification_notes,
            'has_attachment' => ! empty($this->attachment_path),
            'attachment_original_name' => $this->attachment_original_name,
            'attachment_mime' => $this->attachment_mime,
            'attachment_size_bytes' => $this->attachment_size_bytes,
            'attachment_sha256' => $this->attachment_sha256,
            'attachment_download_url' => $this->attachment_path
                ? url("/api/v1/warehouse/receipts/{$this->id}/attachment")
                : null,
            'received_by' => $isExternalPartner ? null : $this->received_by,
            'receiver_name' => $isExternalPartner ? null : $this->receiver?->full_name_en,
            'verified_by' => $isExternalPartner ? null : $this->verified_by,
            'verifier_name' => $isExternalPartner ? null : $this->verifier?->full_name_en,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'notes' => $this->notes,
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'commodity_id' => $item->commodity_id,
                        'commodity' => $item->commodity ? [
                            'id' => $item->commodity->id,
                            'commodity_code' => $item->commodity->commodity_code,
                            'name_en' => $item->commodity->name_en,
                            'name_bn' => $item->commodity->name_bn,
                            'unit_of_measure' => $item->commodity->unit_of_measure,
                        ] : null,
                        'batch_number' => $item->batch_number,
                        'quantity_received' => (float) $item->quantity_received,
                        'unit_cost_bdt' => $item->unit_cost_bdt !== null ? (float) $item->unit_cost_bdt : null,
                        'total_value_bdt' => $item->unit_cost_bdt !== null
                            ? round((float) $item->quantity_received * (float) $item->unit_cost_bdt, 2)
                            : null,
                        'expiry_date' => $item->expiry_date?->toDateString(),
                        'warehouse_inventory_id' => $item->warehouse_inventory_id,
                        'notes' => $item->notes,
                    ];
                })->values();
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
