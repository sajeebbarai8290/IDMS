<?php

namespace App\Modules\Disaster\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDisasterCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_]+$/'],
            'name_en' => ['required', 'string', 'max:120'],
            'name_bn' => ['required', 'string', 'max:150'],
            'icon' => ['nullable', 'string', 'max:60'],
            'color_hex' => ['nullable', 'string', 'max:20'],
            'subtypes' => ['nullable', 'array'],
            'subtypes.*.code' => ['required', 'string', 'max:60'],
            'subtypes.*.name_en' => ['required', 'string', 'max:120'],
            'subtypes.*.name_bn' => ['required', 'string', 'max:150'],
        ];
    }
}
