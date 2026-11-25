<?php

namespace App\Modules\Response\Requests;

use App\Modules\Response\Models\ResponseActivityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreResponseActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manageActivities', ResponseActivityType::class);
    }

    public function rules(): array
    {
        $table = (new ResponseActivityType())->getTable();

        return [
            'code' => ['required', 'string', 'max:50', 'alpha_dash', "unique:{$table},code"],
            'name_en' => ['required', 'string', 'max:120'],
            'name_bn' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'icon' => ['nullable', 'string', 'max:60'],
            'color_hex' => ['nullable', 'string', 'max:20'],
        ];
    }
}
