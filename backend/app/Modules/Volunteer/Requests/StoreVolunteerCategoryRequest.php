<?php

namespace App\Modules\Volunteer\Requests;

use App\Modules\Volunteer\Models\VolunteerCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreVolunteerCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manageCategory', VolunteerCategory::class);
    }

    public function rules(): array
    {
        $categoryId = $this->route('category')?->id;
        return [
            'code' => ['required', 'string', 'max:60', 'regex:/^[A-Z0-9_]+$/', 'unique:volunteer_categories,code,' . ($categoryId ?? 'NULL') . ',id'],
            'name_en' => ['required', 'string', 'max:120'],
            'name_bn' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
            'color_hex' => ['nullable', 'string', 'max:20'],
            'sort_order' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
        }
    }
}
