<?php

namespace App\Modules\Beneficiary\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResolveDuplicateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:CONFIRMED_DUPLICATE,FALSE_POSITIVE_RESOLVED',
            'resolution_notes' => 'required|string|max:1000',
        ];
    }
}
