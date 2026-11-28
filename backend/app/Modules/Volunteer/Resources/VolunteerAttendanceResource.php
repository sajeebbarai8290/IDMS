<?php

namespace App\Modules\Volunteer\Resources;

use App\Modules\Volunteer\Policies\VolunteerPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VolunteerAttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $policy = app(VolunteerPolicy::class);
        $isExternal = $user ? $policy->isExternalPartnerOnly($user) : false;
        $canExactLocation = $user ? $policy->canViewExactLocation($user) : false;

        $inLat = $this->check_in_lat !== null ? ($canExactLocation ? (float) $this->check_in_lat : null) : null;
        $inLng = $this->check_in_lng !== null ? ($canExactLocation ? (float) $this->check_in_lng : null) : null;
        $outLat = $this->check_out_lat !== null ? ($canExactLocation ? (float) $this->check_out_lat : null) : null;
        $outLng = $this->check_out_lng !== null ? ($canExactLocation ? (float) $this->check_out_lng : null) : null;

        return [
            'id' => $this->id,
            'deployment_id' => $this->deployment_id,
            'volunteer_id' => $this->volunteer_id,
            'administrative_unit_id' => $this->administrative_unit_id,
            'attendance_date' => $this->attendance_date?->toDateString(),
            'check_in_at' => $this->check_in_at?->toIso8601String(),
            'check_out_at' => $this->check_out_at?->toIso8601String(),
            'duration_minutes' => $this->duration_minutes,
            'gps_consent_given' => $this->gps_consent_given,
            'check_in_lat' => $this->gps_consent_given ? $inLat : null,
            'check_in_lng' => $this->gps_consent_given ? $inLng : null,
            'check_out_lat' => $this->gps_consent_given ? $outLat : null,
            'check_out_lng' => $this->gps_consent_given ? $outLng : null,
            'location_redacted' => !$canExactLocation,
            'activity_summary' => $this->activity_summary,
            'tasks_completed' => $this->tasks_completed,
            'beneficiaries_assisted' => $this->beneficiaries_assisted,
            'has_evidence' => !empty($this->evidence_path),
            'evidence_original_name' => $isExternal ? null : $this->evidence_original_name,
            'confirmation_status' => $this->confirmation_status,
            'supervisor_confirmed_by' => $this->supervisor_confirmed_by,
            'supervisor_confirmed_at' => $this->supervisor_confirmed_at?->toIso8601String(),
            'supervisor_remarks' => $this->supervisor_remarks,
            'volunteer' => $this->whenLoaded('volunteer', fn () => [
                'id' => $this->volunteer->id,
                'volunteer_code' => $this->volunteer->volunteer_code,
                'full_name_en' => $isExternal ? 'Verified Volunteer' : $this->volunteer->full_name_en,
                'full_name_bn' => $isExternal ? 'যাচাইকৃত স্বেচ্ছাসেবক' : $this->volunteer->full_name_bn,
            ]),
            'deployment' => $this->whenLoaded('deployment', fn () => [
                'id' => $this->deployment->id,
                'deployment_no' => $this->deployment->deployment_no,
                'assigned_role' => $this->deployment->assigned_role,
                'deployment_location' => $isExternal ? $this->administrativeUnit?->name_en : $this->deployment->deployment_location,
            ]),
            'administrative_unit' => $this->whenLoaded('administrativeUnit', fn () => [
                'id' => $this->administrativeUnit->id,
                'name_en' => $this->administrativeUnit->name_en,
                'name_bn' => $this->administrativeUnit->name_bn,
            ]),
            'confirmed_by' => $this->whenLoaded('confirmedBy', fn () => $this->confirmedBy ? [
                'id' => $this->confirmedBy->id,
                'full_name_en' => $this->confirmedBy->full_name_en,
                'role_code' => $this->confirmedBy->role_code,
            ] : null),
        ];
    }
}
