<?php

namespace App\Modules\DamageAssessment\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreDamageBoundaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assessment = $this->route('assessment');
        return Gate::allows('updateAssessment', $assessment);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->boundary_geojson)) {
            $decoded = json_decode($this->boundary_geojson, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['boundary_geojson' => $decoded]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'boundary_geojson' => ['required', 'array'],
        ];
    }
}
