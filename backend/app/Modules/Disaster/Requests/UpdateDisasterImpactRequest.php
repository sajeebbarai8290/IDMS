<?php

namespace App\Modules\Disaster\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDisasterImpactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'affected_population' => ['required', 'integer', 'min:0'],
            'affected_households' => ['required', 'integer', 'min:0'],
            'deaths_count' => ['nullable', 'integer', 'min:0'],
            'injured_count' => ['nullable', 'integer', 'min:0'],
            'missing_count' => ['nullable', 'integer', 'min:0'],
            'damaged_houses_fully' => ['nullable', 'integer', 'min:0'],
            'damaged_houses_partially' => ['nullable', 'integer', 'min:0'],
            'damaged_infrastructure_count' => ['nullable', 'integer', 'min:0'],
            'agricultural_loss_acres' => ['nullable', 'numeric', 'min:0'],
            'agricultural_loss_bdt' => ['nullable', 'numeric', 'min:0'],
            'livestock_loss_count' => ['nullable', 'integer', 'min:0'],
            'livestock_loss_bdt' => ['nullable', 'numeric', 'min:0'],
            'total_economic_loss_bdt' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
