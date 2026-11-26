<?php

namespace App\Modules\Warehouse\Requests;

use App\Modules\Relief\Models\ReliefCategory;
use App\Modules\Warehouse\Models\Commodity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateCommodityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manageItems', Commodity::class);
    }

    public function rules(): array
    {
        $commoditiesTable = (new Commodity())->getTable();
        $categoriesTable = (new ReliefCategory())->getTable();
        $commodityId = $this->route('id');

        return [
            'relief_category_id' => ['nullable', 'uuid', "exists:{$categoriesTable},id"],
            'commodity_code' => [
                'sometimes',
                'string',
                'max:30',
                Rule::unique($commoditiesTable, 'commodity_code')->ignore($commodityId),
            ],
            'name_en' => ['sometimes', 'string', 'max:150'],
            'name_bn' => ['sometimes', 'string', 'max:200'],
            'category' => ['sometimes', 'string', 'max:50'],
            'unit_of_measure' => ['sometimes', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:2000'],
            'minimum_stock_level' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'is_perishable' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
