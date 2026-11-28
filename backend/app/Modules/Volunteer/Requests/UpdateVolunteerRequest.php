<?php

namespace App\Modules\Volunteer\Requests;

use App\Modules\Volunteer\Models\Volunteer;
use App\Modules\Volunteer\Models\VolunteerSkillAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateVolunteerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $volunteer = $this->route('volunteer');
        return $volunteer && Gate::allows('updateVolunteer', $volunteer);
    }

    public function rules(): array
    {
        return [
            'administrative_unit_id' => ['sometimes', 'uuid'],
            'organization_id' => ['nullable', 'uuid'],
            'primary_category_id' => ['nullable', 'uuid'],
            'full_name_en' => ['sometimes', 'string', 'max:150'],
            'full_name_bn' => ['sometimes', 'string', 'max:180'],
            'gender' => ['nullable', 'string', Rule::in(Volunteer::GENDERS)],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'blood_group' => ['nullable', 'string', 'max:10'],
            'nid_number' => ['nullable', 'string', 'regex:/^(?:\d{10}|\d{13}|\d{17})$/'],
            'mobile_number' => ['sometimes', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'present_address' => ['nullable', 'string', 'max:255'],
            'permanent_address' => ['nullable', 'string', 'max:255'],
            'ward_village' => ['nullable', 'string', 'max:120'],
            'emergency_contact_name' => ['sometimes', 'string', 'max:150'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:60'],
            'emergency_contact_phone' => ['sometimes', 'string', 'max:30'],
            'availability_status' => ['sometimes', 'string', Rule::in(Volunteer::AVAILABILITY_STATUSES)],
            'location_consent_given' => ['nullable', 'boolean'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'skills' => ['nullable', 'array'],
            'skills.*.skill_id' => ['required', 'uuid'],
            'skills.*.proficiency_level' => ['nullable', 'string', Rule::in(VolunteerSkillAssignment::PROFICIENCY_LEVELS)],
            'skills.*.certification_level' => ['nullable', 'string', 'max:60'],
            'skills.*.certified_by' => ['nullable', 'string', 'max:150'],
            'skills.*.issued_at' => ['nullable', 'date'],
            'skills.*.expires_at' => ['nullable', 'date'],
        ];
    }
}
