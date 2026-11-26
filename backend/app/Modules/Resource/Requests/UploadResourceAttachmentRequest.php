<?php

namespace App\Modules\Resource\Requests;

use App\Modules\Resource\Models\ResourceAsset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UploadResourceAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $asset = $this->route('asset');
        return $asset instanceof ResourceAsset && Gate::allows('updateAsset', $asset);
    }

    public function rules(): array
    {
        return [
            'attachment_kind' => ['required', 'string', Rule::in(['DOCUMENT', 'PHOTOGRAPH'])],
            'title' => ['nullable', 'string', 'max:160'],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx'],
        ];
    }
}
