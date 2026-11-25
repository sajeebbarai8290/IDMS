<?php

namespace App\Modules\Relief\Requests;

use App\Modules\Relief\Models\ReliefCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateReliefCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manageCategories', ReliefCategory::class);
    }

    public function rules(): array
    {
        return [
            'name_en' => ['sometimes', 'required', 'string', 'max:120'],
            'name_bn' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'icon' => ['nullable', 'string', 'max:60'],
            'color_hex' => ['nullable', 'string', 'max:20'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
