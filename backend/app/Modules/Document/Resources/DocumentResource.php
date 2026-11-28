<?php

namespace App\Modules\Document\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_no' => $this->document_no,
            'reference_no' => $this->reference_no,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'code' => $this->category->code,
                'name_en' => $this->category->name_en,
                'name_bn' => $this->category->name_bn,
                'requires_approval' => (bool) $this->category->requires_approval,
            ] : null),
            'document_type' => $this->document_type,
            'title_bn' => $this->title_bn,
            'title_en' => $this->title_en,
            'description_bn' => $this->description_bn,
            'description_en' => $this->description_en,
            'issuing_organization' => $this->issuing_organization,
            'organization_id' => $this->organization_id,
            'organization' => $this->whenLoaded('organization', fn () => $this->organization ? [
                'id' => $this->organization->id,
                'org_code' => $this->organization->org_code,
                'name_en' => $this->organization->name_en,
                'name_bn' => $this->organization->name_bn,
            ] : null),
            'administrative_unit_id' => $this->administrative_unit_id,
            'administrative_unit' => $this->whenLoaded('administrativeUnit', fn () => $this->administrativeUnit ? [
                'id' => $this->administrativeUnit->id,
                'code' => $this->administrativeUnit->code,
                'name_en' => $this->administrativeUnit->name_en,
                'name_bn' => $this->administrativeUnit->name_bn,
                'tier_level' => $this->administrativeUnit->tier_level,
            ] : null),
            'owner_user_id' => $this->owner_user_id,
            'owner' => $this->whenLoaded('owner', fn () => $this->owner ? [
                'id' => $this->owner->id,
                'full_name_en' => $this->owner->full_name_en,
                'full_name_bn' => $this->owner->full_name_bn,
                'role_code' => $this->owner->role_code,
            ] : null),
            'uploaded_by' => $this->uploaded_by,
            'uploader' => $this->whenLoaded('uploader', fn () => $this->uploader ? [
                'id' => $this->uploader->id,
                'full_name_en' => $this->uploader->full_name_en,
                'full_name_bn' => $this->uploader->full_name_bn,
                'role_code' => $this->uploader->role_code,
            ] : null),
            'uploaded_at' => $this->uploaded_at?->toIso8601String(),
            'effective_date' => $this->effective_date?->toDateString(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'is_expired' => $this->isExpired(),
            'module_code' => $this->module_code,
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'entity_reference' => $this->entity_reference,
            'classification' => $this->classification,
            'is_confidential' => $this->isConfidentialFile(),
            'tags' => $this->tags ?? [],
            'approval_status' => $this->approval_status,
            'submitted_by' => $this->submitted_by,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'submitter' => $this->whenLoaded('submitter', fn () => $this->submitter ? [
                'id' => $this->submitter->id,
                'full_name_en' => $this->submitter->full_name_en,
                'full_name_bn' => $this->submitter->full_name_bn,
                'role_code' => $this->submitter->role_code,
            ] : null),
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'approval_notes' => $this->approval_notes,
            'approver' => $this->whenLoaded('approver', fn () => $this->approver ? [
                'id' => $this->approver->id,
                'full_name_en' => $this->approver->full_name_en,
                'full_name_bn' => $this->approver->full_name_bn,
                'role_code' => $this->approver->role_code,
            ] : null),
            'rejected_by' => $this->rejected_by,
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'rejecter' => $this->whenLoaded('rejecter', fn () => $this->rejecter ? [
                'id' => $this->rejecter->id,
                'full_name_en' => $this->rejecter->full_name_en,
                'full_name_bn' => $this->rejecter->full_name_bn,
                'role_code' => $this->rejecter->role_code,
            ] : null),
            'current_version_number' => $this->current_version_number,
            'current_version_id' => $this->current_version_id,
            'original_filename' => $this->original_filename,
            'mime_type' => $this->mime_type,
            'file_extension' => $this->file_extension,
            'file_size_bytes' => $this->file_size_bytes,
            'file_hash_sha256' => $this->file_hash_sha256,
            'scan_status' => $this->scan_status,
            'scanned_at' => $this->scanned_at?->toIso8601String(),
            'scan_engine' => $this->scan_engine,
            'is_previewable' => $this->isPreviewable(),
            'versions' => DocumentVersionResource::collection($this->whenLoaded('versions')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
