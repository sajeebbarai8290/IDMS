<?php

namespace App\Modules\Beneficiary\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyNidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nid_number' => ['required', 'string', 'regex:/^(?:\d{10}|\d{13}|\d{17})$/'],
            'date_of_birth' => 'nullable|date',
            'household_id' => 'nullable|uuid',
            'household_member_id' => 'nullable|uuid',
        ];
    }

    public function messages(): array
    {
        return [
            'nid_number.required' => 'National ID number is required (জাতীয় পরিচয়পত্র নম্বর আবশ্যক)।',
            'nid_number.regex' => 'National ID must be 10, 13, or 17 digits (জাতীয় পরিচয়পত্র ১০, ১৩ বা ১৭ সংখ্যার হতে হবে)।',
        ];
    }
}
