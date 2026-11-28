<?php

namespace App\Modules\Volunteer\Requests;

use App\Modules\Volunteer\Models\VolunteerTrainingSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreTrainingSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manageTrainingSession', VolunteerTrainingSession::class);
    }

    public function rules(): array
    {
        return [
            'session_code' => ['nullable', 'string', 'max:50'],
            'program_id' => ['required', 'uuid'],
            'administrative_unit_id' => ['required', 'uuid'],
            'venue' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'trainer_name' => ['required', 'string', 'max:150'],
            'trainer_contact' => ['nullable', 'string', 'max:60'],
            'max_participants' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'status' => ['nullable', 'string', Rule::in(VolunteerTrainingSession::STATUSES)],
            'notes' => ['nullable', 'string', 'max:1500'],
        ];
    }
}
