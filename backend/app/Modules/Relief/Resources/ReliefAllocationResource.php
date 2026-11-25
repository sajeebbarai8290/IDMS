<?php

namespace App\Modules\Relief\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReliefAllocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isExternal = $request->user()?->hasRole('ROLE_EXTERNAL_PARTNER') ?? false;

        return [
            'id' => $this->id,
            'allocation_order_no' => $this->allocation_order_no,
            'relief_requisition_id' => $this->relief_requisition_id,
            'requisition' => $this->whenLoaded('requisition', fn () => $this->requisition ? [
                'id' => $this->requisition->id,
                'requisition_no' => $this->requisition->requisition_no,
                'status' => $this->requisition->status,
            ] : null),
            'disaster_event_id' => $this->disaster_event_id,
            'disaster_event' => $this->whenLoaded('disasterEvent', fn () => [
                'id' => $this->disasterEvent->id,
                'disaster_code' => $this->disasterEvent->disaster_code,
                'name_en' => $this->disasterEvent->name_en,
                'name_bn' => $this->disasterEvent->name_bn,
            ]),
            'administrative_unit_id' => $this->administrative_unit_id,
            'administrative_unit' => $this->whenLoaded('administrativeUnit', fn () => [
                'id' => $this->administrativeUnit->id,
                'name_en' => $this->administrativeUnit->name_en,
                'name_bn' => $this->administrativeUnit->name_bn,
                'tier_level' => $this->administrativeUnit->tier_level,
            ]),
            'distribution_point_id' => $this->distribution_point_id,
            'distribution_point' => $this->whenLoaded('distributionPoint', fn () => $this->distributionPoint ? [
                'id' => $this->distributionPoint->id,
                'point_code' => $this->distributionPoint->point_code,
                'point_name' => $this->distributionPoint->point_name,
                'point_name_bn' => $this->distributionPoint->point_name_bn,
            ] : null),
            'warehouse_id' => $this->warehouse_id,
            'warehouse' => $this->whenLoaded('warehouse', fn () => [
                'id' => $this->warehouse->id,
                'warehouse_code' => $this->warehouse->warehouse_code,
                'warehouse_name' => $this->warehouse->warehouse_name,
                'warehouse_name_bn' => $this->warehouse->warehouse_name_bn,
                'tier' => $this->warehouse->tier,
            ]),
            'status' => $this->status,
            'scheduled_start_at' => $this->scheduled_start_at?->toIso8601String(),
            'scheduled_end_at' => $this->scheduled_end_at?->toIso8601String(),
            'duplicate_window_days' => (int) $this->duplicate_window_days,
            'remarks' => $this->remarks,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'commodity_id' => $item->commodity_id,
                'quantity_allocated' => (float) $item->quantity_allocated,
                'quantity_distributed' => (float) $item->quantity_distributed,
                'quantity_cancelled' => (float) $item->quantity_cancelled,
                'remaining_quantity' => $item->remaining_quantity,
                'commodity' => $item->relationLoaded('commodity') && $item->commodity ? [
                    'id' => $item->commodity->id,
                    'commodity_code' => $item->commodity->commodity_code,
                    'name_en' => $item->commodity->name_en,
                    'name_bn' => $item->commodity->name_bn,
                    'category' => $item->commodity->category,
                    'unit_of_measure' => $item->commodity->unit_of_measure,
                ] : null,
            ])),
            'beneficiaries' => $this->whenLoaded('beneficiaries', fn () => $isExternal ? [] : $this->beneficiaries->map(fn ($b) => [
                'id' => $b->id,
                'household_id' => $b->household_id,
                'selection_status' => $b->selection_status,
                'household' => $b->relationLoaded('household') && $b->household ? [
                    'id' => $b->household->id,
                    'household_code' => $b->household->household_code,
                    'hoh_name_en' => $b->household->hoh_name_en,
                    'hoh_name_bn' => $b->household->hoh_name_bn,
                    'hoh_nid_masked' => $b->household->hoh_nid_masked,
                    'vulnerability_score' => (float) $b->household->vulnerability_score,
                ] : null,
            ])),
            'created_by' => $this->created_by,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
