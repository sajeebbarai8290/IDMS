<?php

namespace App\Modules\Document\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DocumentApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:2000'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
