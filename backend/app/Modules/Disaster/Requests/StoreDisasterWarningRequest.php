<?php

namespace App\Modules\Disaster\Requests;

use App\Modules\Disaster\Models\DisasterWarning;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDisasterWarningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warning_level' => ['required', 'string', Rule::in(DisasterWarning::LEVELS)],
            'title_en' => ['required', 'string', 'max:180'],
            'title_bn' => ['nullable', 'string', 'max:220'],
            'message_en' => ['required', 'string', 'max:5000'],
            'message_bn' => ['nullable', 'string', 'max:5000'],
            'issuing_authority' => ['required', 'string', 'max:150'],
            'issued_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:issued_at'],
            'affected_unit_ids' => ['nullable', 'array'],
            'affected_unit_ids.*' => ['uuid'],
        ];
    }
}
