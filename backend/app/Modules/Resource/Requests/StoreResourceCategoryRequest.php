<?php

namespace App\Modules\Resource\Requests;

use App\Modules\Resource\Models\ResourceCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreResourceCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manageCategory', ResourceCategory::class);
    }

    public function rules(): array
    {
        $table = (new ResourceCategory())->getTable();

        return [
            'code' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9_]+$/', "unique:{$table},code"],
            'name_en' => ['required', 'string', 'max:120'],
            'name_bn' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'icon' => ['nullable', 'string', 'max:60'],
            'color_hex' => ['nullable', 'string', 'max:20'],
            'is_human_resource' => ['sometimes', 'boolean'],
        ];
    }
}
