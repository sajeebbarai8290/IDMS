<?php

namespace App\Modules\Relief\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DistributionVoucherResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isExternal = $request->user()?->hasRole('ROLE_EXTERNAL_PARTNER') ?? false;

        return [
            'id' => $this->id,
            'voucher_no' => $this->voucher_no,
            'idempotency_key' => $this->idempotency_key,
            'relief_allocation_item_id' => $this->relief_allocation_item_id,
            'allocation_item' => $this->whenLoaded('allocationItem', fn () => [
                'id' => $this->allocationItem->id,
                'commodity' => $this->allocationItem->relationLoaded('commodity') && $this->allocationItem->commodity ? [
                    'id' => $this->allocationItem->commodity->id,
                    'commodity_code' => $this->allocationItem->commodity->commodity_code,
                    'name_en' => $this->allocationItem->commodity->name_en,
                    'name_bn' => $this->allocationItem->commodity->name_bn,
                    'unit_of_measure' => $this->allocationItem->commodity->unit_of_measure,
                ] : null,
                'allocation' => $this->allocationItem->relationLoaded('allocation') && $this->allocationItem->allocation ? [
                    'id' => $this->allocationItem->allocation->id,
                    'allocation_order_no' => $this->allocationItem->allocation->allocation_order_no,
                    'disaster_event' => $this->allocationItem->allocation->relationLoaded('disasterEvent') && $this->allocationItem->allocation->disasterEvent ? [
                        'id' => $this->allocationItem->allocation->disasterEvent->id,
                        'name_en' => $this->allocationItem->allocation->disasterEvent->name_en,
                        'name_bn' => $this->allocationItem->allocation->disasterEvent->name_bn,
                    ] : null,
                ] : null,
            ]),
            'household_id' => $isExternal ? null : $this->household_id,
            'household' => $this->whenLoaded('household', fn () => $isExternal ? null : [
                'id' => $this->household->id,
                'household_code' => $this->household->household_code,
                'hoh_name_en' => $this->household->hoh_name_en,
                'hoh_name_bn' => $this->household->hoh_name_bn,
                'hoh_nid_masked' => $this->household->hoh_nid_masked,
            ]),
            'distribution_point_id' => $this->distribution_point_id,
            'distribution_point' => $this->whenLoaded('distributionPoint', fn () => [
                'id' => $this->distributionPoint->id,
                'point_code' => $this->distributionPoint->point_code,
                'point_name' => $this->distributionPoint->point_name,
                'point_name_bn' => $this->distributionPoint->point_name_bn,
            ]),
            'quantity_disbursed' => (float) $this->quantity_disbursed,
            'verification_method' => $this->verification_method,
            'verification_proof' => $this->verification_proof,
            'acknowledgment_signature' => $this->acknowledgment_signature,
            'has_photo_evidence' => (bool) $this->photo_evidence_path,
            'distribution_lat' => $this->distribution_lat,
            'distribution_lng' => $this->distribution_lng,
            'duplicate_override' => (bool) $this->duplicate_override,
            'duplicate_override_reason' => $this->duplicate_override_reason,
            'distributed_by' => $this->distributed_by,
            'distributor' => $this->whenLoaded('distributor', fn () => $this->distributor ? [
                'id' => $this->distributor->id,
                'name' => $this->distributor->full_name_en,
                'full_name_en' => $this->distributor->full_name_en,
                'full_name_bn' => $this->distributor->full_name_bn,
                'role_code' => $this->distributor->role_code,
            ] : null),
            'distributed_at' => $this->distributed_at?->toIso8601String(),
            'verified_by_tag_officer' => $this->verified_by_tag_officer,
            'verifying_tag_officer' => $this->whenLoaded('verifyingTagOfficer', fn () => $this->verifyingTagOfficer ? [
                'id' => $this->verifyingTagOfficer->id,
                'name' => $this->verifyingTagOfficer->full_name_en,
                'full_name_en' => $this->verifyingTagOfficer->full_name_en,
                'full_name_bn' => $this->verifyingTagOfficer->full_name_bn,
                'role_code' => $this->verifyingTagOfficer->role_code,
            ] : null),
            'verified_at' => $this->verified_at?->toIso8601String(),
            'remarks' => $this->remarks,
            'is_voided' => (bool) $this->is_voided,
            'void_reason' => $this->void_reason,
            'voided_at' => $this->voided_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
