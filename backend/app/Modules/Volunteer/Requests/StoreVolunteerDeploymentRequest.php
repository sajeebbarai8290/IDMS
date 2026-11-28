<?php

namespace App\Modules\Volunteer\Requests;

use App\Modules\Volunteer\Models\VolunteerDeployment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreVolunteerDeploymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('deploy', VolunteerDeployment::class);
    }

    public function rules(): array
    {
        return [
            'volunteer_id' => ['required', 'uuid'],
            'disaster_event_id' => ['nullable', 'uuid'],
            'response_operation_id' => ['nullable', 'uuid'],
            'response_team_id' => ['nullable', 'uuid'],
            'administrative_unit_id' => ['required', 'uuid'],
            'deployment_location' => ['required', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'assigned_role' => ['required', 'string', 'max:100'],
            'assigned_duties' => ['required', 'string', 'max:2000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'supervisor_user_id' => ['nullable', 'uuid'],
            'supervisor_name' => ['required', 'string', 'max:150'],
            'supervisor_contact' => ['nullable', 'string', 'max:60'],
            'activate_immediately' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1500'],
        ];
    }
}
