<?php

namespace App\Modules\Volunteer\Resources;

use App\Modules\Volunteer\Policies\VolunteerPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VolunteerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $policy = app(VolunteerPolicy::class);
        $isExternal = $user ? $policy->isExternalPartnerOnly($user) : false;
        $canExactLocation = $user ? $policy->canViewExactLocation($user) : false;

        $lat = $this->latitude !== null ? ($canExactLocation ? (float) $this->latitude : round((float) $this->latitude, 2)) : null;
        $lng = $this->longitude !== null ? ($canExactLocation ? (float) $this->longitude : round((float) $this->longitude, 2)) : null;

        return [
            'id' => $this->id,
            'volunteer_code' => $this->volunteer_code,
            'administrative_unit_id' => $this->administrative_unit_id,
            'organization_id' => $this->organization_id,
            'primary_category_id' => $this->primary_category_id,
            'user_id' => $this->user_id,

            'full_name_en' => $isExternal ? 'Verified Volunteer' : $this->full_name_en,
            'full_name_bn' => $isExternal ? 'যাচাইকৃত স্বেচ্ছাসেবক' : $this->full_name_bn,
            'gender' => $this->gender,
            'date_of_birth' => $isExternal ? null : $this->date_of_birth?->toDateString(),
            'blood_group' => $this->blood_group,

            // Protected & Masked PII (never exposes raw encrypted values)
            'nid_masked' => $isExternal ? null : $this->nid_masked,
            'mobile_masked' => $isExternal ? null : $this->mobile_masked,
            'email_masked' => $isExternal ? null : $this->email_masked,
            'present_address' => $isExternal ? null : $this->present_address,
            'permanent_address' => $isExternal ? null : $this->permanent_address,
            'ward_village' => $isExternal ? null : $this->ward_village,
            'emergency_contact_name' => $isExternal ? null : $this->emergency_contact_name,
            'emergency_contact_relation' => $isExternal ? null : $this->emergency_contact_relation,
            'emergency_contact_masked' => $isExternal ? null : $this->emergency_contact_masked,
            'has_photo' => !$isExternal && !empty($this->photo_path),
            'photo_mime' => $isExternal ? null : $this->photo_mime,

            'identity_verification_status' => $this->identity_verification_status,
            'nid_verification_id' => $isExternal ? null : $this->nid_verification_id,
            'organization_verification_status' => $this->organization_verification_status,
            'training_verification_status' => $this->training_verification_status,
            'overall_verification_status' => $this->overall_verification_status,
            'submitted_by' => $this->submitted_by,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'verified_by' => $this->verified_by,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'rejected_by' => $this->rejected_by,
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,

            'availability_status' => $this->availability_status,
            'location_consent_given' => $this->location_consent_given,
            'latitude' => $this->location_consent_given ? $lat : null,
            'longitude' => $this->location_consent_given ? $lng : null,
            'location_masked' => !$canExactLocation,
            'pii_redacted' => $isExternal,
            'notes' => $isExternal ? null : $this->notes,

            'administrative_unit' => $this->whenLoaded('administrativeUnit', fn () => [
                'id' => $this->administrativeUnit->id,
                'code' => $this->administrativeUnit->code,
                'name_en' => $this->administrativeUnit->name_en,
                'name_bn' => $this->administrativeUnit->name_bn,
                'tier_level' => $this->administrativeUnit->tier_level,
            ]),
            'organization' => $this->whenLoaded('organization', fn () => $this->organization ? [
                'id' => $this->organization->id,
                'org_code' => $this->organization->org_code,
                'name_en' => $this->organization->name_en,
                'name_bn' => $this->organization->name_bn,
                'org_type' => $this->organization->org_type,
                'verification_status' => $this->organization->verification_status,
            ] : null),
            'primary_category' => $this->whenLoaded('primaryCategory', fn () => $this->primaryCategory ? [
                'id' => $this->primaryCategory->id,
                'code' => $this->primaryCategory->code,
                'name_en' => $this->primaryCategory->name_en,
                'name_bn' => $this->primaryCategory->name_bn,
                'color_hex' => $this->primaryCategory->color_hex,
            ] : null),
            'skills' => $this->whenLoaded('skillAssignments', fn () => $this->skillAssignments->map(fn ($sa) => [
                'id' => $sa->id,
                'skill_id' => $sa->skill_id,
                'skill_code' => $sa->skill?->skill_code,
                'name_en' => $sa->skill?->name_en,
                'name_bn' => $sa->skill?->name_bn,
                'proficiency_level' => $sa->proficiency_level,
                'certification_level' => $sa->certification_level,
                'certified_by' => $sa->certified_by,
                'issued_at' => $sa->issued_at?->toDateString(),
                'expires_at' => $sa->expires_at?->toDateString(),
                'verification_status' => $sa->verification_status,
            ])),
            'verification_logs' => $this->whenLoaded('verificationLogs', fn () => $isExternal ? [] : $this->verificationLogs->map(fn ($log) => [
                'id' => $log->id,
                'pillar' => $log->pillar,
                'action' => $log->action,
                'from_status' => $log->from_status,
                'to_status' => $log->to_status,
                'remarks' => $log->remarks,
                'actor_name' => $log->actor?->full_name_en,
                'actor_role' => $log->actor?->role_code,
                'created_at' => $log->created_at?->toIso8601String(),
            ])),
            'deployments' => $this->whenLoaded('deployments', fn () => $this->deployments->map(fn ($d) => [
                'id' => $d->id,
                'deployment_no' => $d->deployment_no,
                'assigned_role' => $d->assigned_role,
                'deployment_location' => $isExternal ? $d->administrativeUnit?->name_en : $d->deployment_location,
                'deployment_status' => $d->deployment_status,
                'start_date' => $d->start_date?->toDateString(),
                'end_date' => $d->end_date?->toDateString(),
                'disaster_code' => $d->disasterEvent?->disaster_code,
                'operation_no' => $d->responseOperation?->operation_no,
            ])),
            'training_enrollments' => $this->whenLoaded('trainingEnrollments', fn () => $this->trainingEnrollments->map(fn ($e) => [
                'id' => $e->id,
                'session_code' => $e->session?->session_code,
                'program_title_en' => $e->session?->program?->title_en,
                'program_title_bn' => $e->session?->program?->title_bn,
                'enrollment_status' => $e->enrollment_status,
                'attendance_hours' => $e->attendance_hours,
                'passed' => $e->passed,
                'certificate_no' => $e->certificate_no,
                'issued_at' => $e->issued_at?->toDateString(),
                'expires_at' => $e->expires_at?->toDateString(),
                'has_certificate_file' => !empty($e->certificate_path),
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
