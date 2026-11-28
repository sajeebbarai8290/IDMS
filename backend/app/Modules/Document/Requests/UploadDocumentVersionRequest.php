<?php

namespace App\Modules\Document\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadDocumentVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $maxKb = (int) config('services.document.max_file_size_kb', 20480);

        return [
            'file' => ['required', 'file', "max:{$maxKb}"],
            'version_label' => ['nullable', 'string', 'max:30'],
            'change_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
