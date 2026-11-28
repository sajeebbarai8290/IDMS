<?php

namespace App\Modules\Document\Requests;

use App\Modules\Document\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:60', 'regex:/^[A-Z0-9_]+$/'],
            'name_en' => ['required', 'string', 'max:150'],
            'name_bn' => ['required', 'string', 'max:200'],
            'description_en' => ['nullable', 'string', 'max:1000'],
            'description_bn' => ['nullable', 'string', 'max:1000'],
            'default_classification' => ['nullable', 'string', Rule::in(Document::CLASSIFICATIONS)],
            'allowed_formats' => ['nullable', 'array'],
            'allowed_formats.*' => ['string', 'max:15'],
            'retention_days' => ['nullable', 'integer', 'min:1', 'max:36500'],
            'requires_approval' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
