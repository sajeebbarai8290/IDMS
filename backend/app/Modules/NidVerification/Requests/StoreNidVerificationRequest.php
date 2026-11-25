<?php

namespace App\Modules\NidVerification\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreNidVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nid_number' => ['required', 'string', 'regex:/^(?:\d{10}|\d{13}|\d{17})$/'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'submitted_name_en' => ['nullable', 'string', 'max:150'],
            'household_id' => ['nullable', 'uuid'],
            'household_member_id' => ['nullable', 'uuid'],
            'administrative_unit_id' => ['nullable', 'uuid'],
            'async' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nid_number.required' => 'National ID number is required (জাতীয় পরিচয়পত্র নম্বর আবশ্যক)।',
            'nid_number.regex' => 'National ID must be 10, 13, or 17 numeric digits (জাতীয় পরিচয়পত্র ১০, ১৩ বা ১৭ সংখ্যার হতে হবে)।',
            'date_of_birth.before' => 'Date of birth must be in the past (জন্ম তারিখ অবশ্যই অতীতের হতে হবে)।',
        ];
    }
}
