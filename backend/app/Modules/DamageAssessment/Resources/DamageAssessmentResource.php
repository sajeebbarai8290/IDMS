<?php

namespace App\Modules\DamageAssessment\Resources;

use App\Modules\DamageAssessment\Policies\DamageAssessmentPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DamageAssessmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isExternal = $user ? app(DamageAssessmentPolicy::class)->isExternalPartnerOnly($user) : false;

        return [
            'id' => $this->id,
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
                'code' => $this->administrativeUnit->code,
                'name_en' => $this->administrativeUnit->name_en,
                'name_bn' => $this->administrativeUnit->name_bn,
                'tier_level' => $this->administrativeUnit->tier_level,
                'ancestry_path' => $this->administrativeUnit->ancestry_path,
            ]),
            'd_form_code' => $this->d_form_code,
            'reference_no' => $this->reference_no,
            'assessment_type' => $this->assessment_type,
            'assessment_date' => $this->assessment_date?->toDateString(),
            'assessed_at' => $this->assessed_at?->toIso8601String(),
            'assessing_organization' => $this->assessing_organization,
            'team_name' => $this->team_name,
            'team_lead_name' => $isExternal ? 'REDACTED' : $this->team_lead_name,
            'team_members' => $isExternal ? [] : ($this->team_members ?? []),
            'assessor_user_id' => $this->assessor_user_id,
            'assessor_name' => $isExternal ? 'REDACTED' : $this->assessor_name,
            'assessor_designation' => $this->assessor_designation,
            'assessor_contact' => $isExternal ? null : $this->assessor_contact,
            'ward_no' => $this->ward_no,
            'location_summary' => $this->location_summary,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'boundary_geojson' => $this->boundary_geojson,
            'severity_level' => $this->severity_level,
            'affected_households' => (int) $this->affected_households,
            'affected_population' => (int) $this->affected_population,
            'displaced_population' => (int) $this->displaced_population,
            'deaths_count' => (int) $this->deaths_count,
            'injured_count' => (int) $this->injured_count,
            'missing_count' => (int) $this->missing_count,
            'total_fully_damaged' => (int) $this->total_fully_damaged,
            'total_partially_damaged' => (int) $this->total_partially_damaged,
            'total_minor_damaged' => (int) $this->total_minor_damaged,
            'total_estimated_loss_bdt' => (float) $this->total_estimated_loss_bdt,
            'field_observations' => $this->field_observations,
            'verification_status' => $this->verification_status,
            'is_editable' => $this->isEditable(),
            'correction_notes' => $this->correction_notes,
            'rejection_reason' => $this->rejection_reason,
            'submitted_by' => $this->submitted_by,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'verified_by' => $this->verified_by,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'rejected_by' => $this->rejected_by,
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'created_by' => $this->created_by,
            'client_uuid' => $this->client_uuid,
            'idempotency_key' => $this->idempotency_key,
            'device_id' => $this->device_id,
            'offline_created_at' => $this->offline_created_at?->toIso8601String(),
            'synced_at' => $this->synced_at?->toIso8601String(),
            'sync_status' => $this->sync_status,
            'sync_version' => (int) $this->sync_version,
            'locations' => DamageLocationResource::collection($this->whenLoaded('locations')),
            'sector_items' => DamageSectorItemResource::collection($this->whenLoaded('sectorItems')),
            'attachments' => $isExternal ? [] : DamageAttachmentResource::collection($this->whenLoaded('attachments')),
            'verification_logs' => $isExternal ? [] : DamageVerificationLogResource::collection($this->whenLoaded('verificationLogs')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
