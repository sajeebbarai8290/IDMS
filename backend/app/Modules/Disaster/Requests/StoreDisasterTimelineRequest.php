<?php

namespace App\Modules\Disaster\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDisasterTimelineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_type' => ['nullable', 'string', Rule::in(['SITUATION_UPDATE', 'FIELD_REPORT', 'ATTACHMENT_ADDED'])],
            'title_en' => ['required', 'string', 'max:180'],
            'title_bn' => ['nullable', 'string', 'max:220'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'occurred_at' => ['nullable', 'date'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,csv,txt'],
        ];
    }
}
