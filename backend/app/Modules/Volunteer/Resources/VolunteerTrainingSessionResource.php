<?php

namespace App\Modules\Volunteer\Resources;

use App\Modules\Volunteer\Policies\VolunteerPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VolunteerTrainingSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isExternal = $user ? app(VolunteerPolicy::class)->isExternalPartnerOnly($user) : false;

        return [
            'id' => $this->id,
            'session_code' => $this->session_code,
            'program_id' => $this->program_id,
            'administrative_unit_id' => $this->administrative_unit_id,
            'venue' => $this->venue,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'trainer_name' => $isExternal ? null : $this->trainer_name,
            'trainer_contact' => $isExternal ? null : $this->trainer_contact,
            'max_participants' => $this->max_participants,
            'status' => $this->status,
            'notes' => $this->notes,
            'program' => $this->whenLoaded('program', fn () => new VolunteerTrainingProgramResource($this->program)),
            'administrative_unit' => $this->whenLoaded('administrativeUnit', fn () => [
                'id' => $this->administrativeUnit->id,
                'name_en' => $this->administrativeUnit->name_en,
                'name_bn' => $this->administrativeUnit->name_bn,
            ]),
            'enrollments' => $this->whenLoaded('enrollments', fn () => $this->enrollments->map(fn ($e) => [
                'id' => $e->id,
                'session_id' => $e->session_id,
                'volunteer_id' => $e->volunteer_id,
                'volunteer_code' => $e->volunteer?->volunteer_code,
                'volunteer_name_en' => $isExternal ? 'Verified Volunteer' : $e->volunteer?->full_name_en,
                'volunteer_name_bn' => $isExternal ? 'যাচাইকৃত স্বেচ্ছাসেবক' : $e->volunteer?->full_name_bn,
                'enrollment_status' => $e->enrollment_status,
                'attendance_hours' => $e->attendance_hours,
                'assessment_score' => $e->assessment_score,
                'passed' => $e->passed,
                'certificate_no' => $e->certificate_no,
                'issued_at' => $e->issued_at?->toDateString(),
                'expires_at' => $e->expires_at?->toDateString(),
                'has_certificate_file' => !empty($e->certificate_path),
            ])),
            'enrollments_count' => $this->whenCounted('enrollments'),
        ];
    }
}
