<?php

namespace App\Modules\Volunteer\Requests;

use App\Modules\Volunteer\Models\VolunteerTrainingProgram;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreTrainingProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manageTrainingProgram', VolunteerTrainingProgram::class);
    }

    public function rules(): array
    {
        return [
            'program_code' => ['nullable', 'string', 'max:50'],
            'title_en' => ['required', 'string', 'max:180'],
            'title_bn' => ['required', 'string', 'max:220'],
            'category_id' => ['nullable', 'uuid'],
            'skill_id' => ['nullable', 'uuid'],
            'provider_organization_id' => ['nullable', 'uuid'],
            'provider_name' => ['required', 'string', 'max:180'],
            'duration_hours' => ['required', 'integer', 'min:1', 'max:2000'],
            'validity_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
