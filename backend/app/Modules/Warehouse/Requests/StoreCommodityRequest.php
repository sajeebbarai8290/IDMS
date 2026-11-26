<?php

namespace App\Modules\Warehouse\Requests;

use App\Modules\Relief\Models\ReliefCategory;
use App\Modules\Warehouse\Models\Commodity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreCommodityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manageItems', Commodity::class);
    }

    public function rules(): array
    {
        $commoditiesTable = (new Commodity())->getTable();
        $categoriesTable = (new ReliefCategory())->getTable();

        return [
            'relief_category_id' => ['nullable', 'uuid', "exists:{$categoriesTable},id"],
            'commodity_code' => ['required', 'string', 'max:30', "unique:{$commoditiesTable},commodity_code"],
            'name_en' => ['required', 'string', 'max:150'],
            'name_bn' => ['required', 'string', 'max:200'],
            'category' => ['required', 'string', 'max:50'],
            'unit_of_measure' => ['required', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:2000'],
            'minimum_stock_level' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'is_perishable' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
