<?php

namespace App\Modules\Disaster\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDisasterBoundaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->affected_zone_geojson)) {
            $decoded = json_decode($this->affected_zone_geojson, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $this->merge(['affected_zone_geojson' => $decoded]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'affected_zone_geojson' => ['required', 'array'],
            'epicenter_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'epicenter_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'source_filename' => ['nullable', 'string', 'max:255'],
            'shapefile_archive' => ['nullable', 'file', 'max:20480', 'mimes:zip,json,geojson'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
