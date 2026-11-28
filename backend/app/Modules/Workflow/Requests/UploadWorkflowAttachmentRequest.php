<?php

namespace App\Modules\Workflow\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadWorkflowAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,txt,csv,docx,xlsx'],
            'title' => ['nullable', 'string', 'max:180'],
            'workflow_transition_id' => ['nullable', 'uuid'],
        ];
    }
}
