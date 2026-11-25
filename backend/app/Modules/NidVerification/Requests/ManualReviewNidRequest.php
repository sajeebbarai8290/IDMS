<?php

namespace App\Modules\NidVerification\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManualReviewNidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision_status' => ['required', 'string', Rule::in(['VERIFIED', 'FAILED'])],
            'override_reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'decision_status.required' => 'Manual review decision is required (সিদ্ধান্ত নির্বাচন আবশ্যক)।',
            'decision_status.in' => 'Decision must be VERIFIED or FAILED.',
            'override_reason.required' => 'A detailed justification reason is required for manual review override (ম্যানুয়াল রিভিউয়ের কারণ উল্লেখ আবশ্যক)।',
            'override_reason.min' => 'Override reason must be at least 10 characters long (কারণ কমপক্ষে ১০ অক্ষরের হতে হবে)।',
        ];
    }
}
