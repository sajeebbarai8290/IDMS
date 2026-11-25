<?php

namespace App\Modules\Disaster\Requests;

use App\Modules\Disaster\Models\DisasterEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionDisasterLifecycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lifecycle_phase' => [
                'required',
                'string',
                Rule::in(array_merge(DisasterEvent::LIFECYCLE_PHASES, ['IMPENDING', 'RESPONSE'])),
            ],
            'notes' => ['nullable', 'string', 'max:1500'],
        ];
    }
}
