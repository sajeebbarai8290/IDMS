<?php

namespace App\Modules\Warehouse\Resources;

use App\Core\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseTransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isExternalPartner = $user instanceof User && $user->hasRole('ROLE_EXTERNAL_PARTNER');

        return [
            'id' => $this->id,
            'transfer_no' => $this->transfer_no,
            'source_warehouse_id' => $this->source_warehouse_id,
            'destination_warehouse_id' => $this->destination_warehouse_id,
            'source_warehouse' => $this->whenLoaded('sourceWarehouse', function () {
                return [
                    'id' => $this->sourceWarehouse->id,
                    'warehouse_code' => $this->sourceWarehouse->warehouse_code,
                    'warehouse_name' => $this->sourceWarehouse->warehouse_name,
                    'warehouse_name_bn' => $this->sourceWarehouse->warehouse_name_bn,
                    'tier' => $this->sourceWarehouse->tier,
                ];
            }),
            'destination_warehouse' => $this->whenLoaded('destinationWarehouse', function () {
                return [
                    'id' => $this->destinationWarehouse->id,
                    'warehouse_code' => $this->destinationWarehouse->warehouse_code,
                    'warehouse_name' => $this->destinationWarehouse->warehouse_name,
                    'warehouse_name_bn' => $this->destinationWarehouse->warehouse_name_bn,
                    'tier' => $this->destinationWarehouse->tier,
                ];
            }),
            'status' => $this->status,
            'requested_date' => $this->requested_date?->toDateString(),
            'expected_delivery_date' => $this->expected_delivery_date?->toDateString(),
            'vehicle_info' => $this->vehicle_info,
            'dispatch_reference' => $this->dispatch_reference,
            'requested_by' => $isExternalPartner ? null : $this->requested_by,
            'requester_name' => $isExternalPartner ? null : $this->requester?->full_name_en,
            'approved_by' => $isExternalPartner ? null : $this->approved_by,
            'approver_name' => $isExternalPartner ? null : $this->approver?->full_name_en,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'dispatched_by' => $isExternalPartner ? null : $this->dispatched_by,
            'dispatcher_name' => $isExternalPartner ? null : $this->dispatcher?->full_name_en,
            'dispatched_at' => $this->dispatched_at?->toIso8601String(),
            'received_by' => $isExternalPartner ? null : $this->received_by,
            'receiver_name' => $isExternalPartner ? null : $this->receiver?->full_name_en,
            'received_at' => $this->received_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,
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
                        'quantity_requested' => (float) $item->quantity_requested,
                        'quantity_dispatched' => (float) $item->quantity_dispatched,
                        'quantity_received' => (float) $item->quantity_received,
                        'unit_cost_bdt' => $item->unit_cost_bdt !== null ? (float) $item->unit_cost_bdt : null,
                        'expiry_date' => $item->expiry_date?->toDateString(),
                        'notes' => $item->notes,
                    ];
                })->values();
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
