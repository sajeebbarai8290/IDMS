<?php

namespace App\Modules\Beneficiary\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerificationActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'stage' => 'required|in:NID_CHECK,ADDRESS_CHECK,FIELD_SURVEY,COMMITTEE_APPROVAL',
            'status' => 'required|in:VERIFIED,REJECTED',
            'remarks' => 'nullable|string|max:1000',
            'evidence_document_path' => 'nullable|string|max:500',
        ];
    }
}
