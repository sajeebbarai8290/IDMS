<?php

namespace App\Modules\Volunteer\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CheckOutVolunteerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $attendance = $this->route('attendance');
        return $attendance && Gate::allows('recordAttendance', $attendance->deployment);
    }

    public function rules(): array
    {
        return [
            'check_out_at' => ['nullable', 'date'],
            'gps_consent_given' => ['nullable', 'boolean'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'activity_summary' => ['required', 'string', 'max:2000'],
            'tasks_completed' => ['nullable', 'string', 'max:2000'],
            'beneficiaries_assisted' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ];
    }
}
