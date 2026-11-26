<?php

namespace App\Modules\Project\Requests;

use App\Modules\Project\Models\ProjectCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreProjectCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manageCategory', ProjectCategory::class);
    }

    public function rules(): array
    {
        $table = (new ProjectCategory())->getTable();

        return [
            'code' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique($table, 'code')],
            'name_en' => ['required', 'string', 'max:120'],
            'name_bn' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'color_hex' => ['nullable', 'string', 'max:20'],
        ];
    }
}
