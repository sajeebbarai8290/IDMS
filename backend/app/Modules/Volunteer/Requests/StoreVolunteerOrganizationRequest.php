<?php

namespace App\Modules\Volunteer\Requests;

use App\Modules\Volunteer\Models\VolunteerOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreVolunteerOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manageOrganization', VolunteerOrganization::class);
    }

    public function rules(): array
    {
        return [
            'org_code' => ['nullable', 'string', 'max:50'],
            'name_en' => ['required', 'string', 'max:180'],
            'name_bn' => ['nullable', 'string', 'max:220'],
            'org_type' => ['required', 'string', Rule::in(VolunteerOrganization::ORG_TYPES)],
            'registration_no' => ['nullable', 'string', 'max:80'],
            'administrative_unit_id' => ['required', 'uuid'],
            'coverage_unit_ids' => ['nullable', 'array'],
            'coverage_unit_ids.*' => ['uuid'],
            'address' => ['nullable', 'string', 'max:255'],
            'contact_person_name' => ['required', 'string', 'max:150'],
            'contact_person_designation' => ['nullable', 'string', 'max:120'],
            'contact_person_phone' => ['required', 'string', 'max:60'],
            'contact_person_email' => ['nullable', 'email', 'max:150'],
            'description' => ['nullable', 'string', 'max:1500'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
