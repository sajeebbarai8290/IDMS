<?php

namespace App\Modules\Project\Requests;

use App\Modules\Project\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreProjectBoundaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Project $project */
        $project = $this->route('project');
        return Gate::allows('updateProject', $project);
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
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
