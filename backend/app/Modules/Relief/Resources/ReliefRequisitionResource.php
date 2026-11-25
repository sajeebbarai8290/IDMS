<?php

namespace App\Modules\Relief\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReliefRequisitionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'requisition_no' => $this->requisition_no,
            'disaster_event_id' => $this->disaster_event_id,
            'disaster_event' => $this->whenLoaded('disasterEvent', fn () => [
                'id' => $this->disasterEvent->id,
                'disaster_code' => $this->disasterEvent->disaster_code,
                'name_en' => $this->disasterEvent->name_en,
                'name_bn' => $this->disasterEvent->name_bn,
                'severity_level' => $this->disasterEvent->severity_level,
            ]),
            'administrative_unit_id' => $this->administrative_unit_id,
            'administrative_unit' => $this->whenLoaded('administrativeUnit', fn () => [
                'id' => $this->administrativeUnit->id,
                'name_en' => $this->administrativeUnit->name_en,
                'name_bn' => $this->administrativeUnit->name_bn,
                'tier_level' => $this->administrativeUnit->tier_level,
                'ancestry_path' => $this->administrativeUnit->ancestry_path,
            ]),
            'requesting_organization' => $this->requesting_organization,
            'estimated_beneficiaries' => (int) $this->estimated_beneficiaries,
            'priority' => $this->priority,
            'request_date' => $this->request_date?->toDateString(),
            'needed_by_date' => $this->needed_by_date?->toDateString(),
            'status' => $this->status,
            'justification' => $this->justification,
            'review_notes' => $this->review_notes,
            'has_supporting_doc' => (bool) $this->supporting_doc_path,
            'supporting_doc_name' => $this->supporting_doc_name,
            'supporting_doc_mime' => $this->supporting_doc_mime,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'commodity_id' => $item->commodity_id,
                'quantity_requested' => (float) $item->quantity_requested,
                'quantity_approved' => (float) $item->quantity_approved,
                'notes' => $item->notes,
                'commodity' => $item->relationLoaded('commodity') && $item->commodity ? [
                    'id' => $item->commodity->id,
                    'commodity_code' => $item->commodity->commodity_code,
                    'name_en' => $item->commodity->name_en,
                    'name_bn' => $item->commodity->name_bn,
                    'category' => $item->commodity->category,
                    'unit_of_measure' => $item->commodity->unit_of_measure,
                ] : null,
            ])),
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->full_name_en,
                'full_name_en' => $this->creator->full_name_en,
                'full_name_bn' => $this->creator->full_name_bn,
                'role_code' => $this->creator->role_code,
            ] : null),
            'submitted_by' => $this->submitted_by,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'reviewed_by' => $this->reviewed_by,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'approved_by' => $this->approved_by,
            'approver' => $this->whenLoaded('approver', fn () => $this->approver ? [
                'id' => $this->approver->id,
                'name' => $this->approver->full_name_en,
                'full_name_en' => $this->approver->full_name_en,
                'full_name_bn' => $this->approver->full_name_bn,
                'role_code' => $this->approver->role_code,
            ] : null),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'allocations_count' => $this->whenCounted('allocations'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
