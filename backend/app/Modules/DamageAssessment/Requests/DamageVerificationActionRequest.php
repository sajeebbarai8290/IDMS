<?php

namespace App\Modules\DamageAssessment\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DamageVerificationActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:2000'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
