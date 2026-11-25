<?php

namespace App\Modules\NidVerification\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RetryNidVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nid_number' => ['required', 'string', 'regex:/^(?:\d{10}|\d{13}|\d{17})$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'nid_number.required' => 'National ID number is required to retry verification (পুনরায় যাচাইয়ের জন্য এনআইডি নম্বর আবশ্যক)।',
            'nid_number.regex' => 'National ID must be 10, 13, or 17 numeric digits.',
        ];
    }
}
