<?php

namespace App\Modules\Volunteer\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CheckInVolunteerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $deployment = $this->route('deployment');
        return $deployment && Gate::allows('recordAttendance', $deployment);
    }

    public function rules(): array
    {
        return [
            'check_in_at' => ['nullable', 'date'],
            'gps_consent_given' => ['nullable', 'boolean'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_if:gps_consent_given,true'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_if:gps_consent_given,true'],
            'activity_summary' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
