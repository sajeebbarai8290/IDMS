<?php

namespace App\Modules\Project\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectDisbursementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isExternal = (bool) $request->user()?->hasRole('ROLE_EXTERNAL_PARTNER');

        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'project_milestone_id' => $this->project_milestone_id,
            'milestone_wbs' => $this->whenLoaded('milestone', fn () => $this->milestone?->wbs_code),
            'milestone_title' => $this->whenLoaded('milestone', fn () => $this->milestone?->milestone_title),
            'record_type' => $this->record_type,
            'voucher_no' => $this->voucher_no,
            'fiscal_year' => $this->fiscal_year,
            'amount_bdt' => (float) $this->amount_bdt,
            'payment_status' => $this->payment_status,
            'description' => $this->description,
            'has_supporting_doc' => !$isExternal && !empty($this->supporting_doc_path),
            'supporting_doc_name' => $isExternal ? null : $this->supporting_doc_name,
            'supporting_doc_mime' => $isExternal ? null : $this->supporting_doc_mime,
            'supporting_doc_size' => $isExternal ? null : $this->supporting_doc_size,
            'supporting_doc_sha256' => $isExternal ? null : $this->supporting_doc_sha256,
            'disbursed_at' => $this->disbursed_at?->toIso8601String(),
            'created_by' => $this->created_by,
            'creator_name' => $this->whenLoaded('creator', fn () => $isExternal ? null : $this->creator?->full_name_en),
            'approved_by' => $this->approved_by,
            'approver_name' => $this->whenLoaded('approver', fn () => $isExternal ? null : $this->approver?->full_name_en),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'approval_notes' => $this->approval_notes,
        ];
    }
}
