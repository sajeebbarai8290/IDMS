<?php

namespace App\Modules\DamageAssessment\Requests;

use App\Modules\DamageAssessment\Models\DamageAssessment;
use App\Modules\DamageAssessment\Models\DamageCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreDamageCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manageCategory', DamageAssessment::class);
    }

    public function rules(): array
    {
        $table = (new DamageCategory())->getTable();
        $existing = $this->route('category');
        $ignoreId = $existing instanceof DamageCategory ? $existing->id : null;

        return [
            'code' => [
                $ignoreId ? 'sometimes' : 'required',
                'string',
                'max:50',
                'regex:/^[A-Za-z0-9_]+$/',
                Rule::unique($table, 'code')->ignore($ignoreId),
            ],
            'name_en' => ['required', 'string', 'max:120'],
            'name_bn' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'default_unit' => ['nullable', 'string', 'max:40'],
            'color_hex' => ['nullable', 'string', 'max:20', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
