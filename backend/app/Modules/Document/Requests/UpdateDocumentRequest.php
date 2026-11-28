<?php

namespace App\Modules\Document\Requests;

use App\Modules\Document\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title_bn' => ['sometimes', 'required', 'string', 'max:300'],
            'title_en' => ['sometimes', 'required', 'string', 'max:300'],
            'reference_no' => ['nullable', 'string', 'max:120'],
            'category_id' => ['sometimes', 'required', 'uuid'],
            'document_type' => ['nullable', 'string', Rule::in(Document::DOCUMENT_TYPES)],
            'description_bn' => ['nullable', 'string', 'max:5000'],
            'description_en' => ['nullable', 'string', 'max:5000'],
            'issuing_organization' => ['nullable', 'string', 'max:200'],
            'organization_id' => ['nullable', 'uuid'],
            'administrative_unit_id' => ['nullable', 'uuid'],
            'owner_user_id' => ['nullable', 'uuid'],
            'effective_date' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'module_code' => ['nullable', 'string', Rule::in(Document::SUPPORTED_MODULES)],
            'entity_type' => ['nullable', 'string', 'max:80'],
            'entity_id' => ['nullable', 'uuid'],
            'classification' => ['nullable', 'string', Rule::in(Document::CLASSIFICATIONS)],
            'tags' => ['nullable'],
        ];
    }
}
