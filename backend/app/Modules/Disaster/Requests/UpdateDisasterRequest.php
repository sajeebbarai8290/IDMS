<?php

namespace App\Modules\Disaster\Requests;

use App\Modules\Disaster\Models\DisasterEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDisasterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'uuid'],
            'disaster_type' => ['sometimes', 'string', 'max:50'],
            'subtype' => ['nullable', 'string', 'max:80'],
            'name_en' => ['sometimes', 'string', 'max:150'],
            'name_bn' => ['sometimes', 'string', 'max:200'],
            'description_en' => ['nullable', 'string', 'max:5000'],
            'description_bn' => ['nullable', 'string', 'max:5000'],
            'severity_level' => ['sometimes', 'string', Rule::in(DisasterEvent::SEVERITY_LEVELS)],
            'declaration_date' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'epicenter_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'epicenter_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'affected_areas_summary' => ['nullable', 'string', 'max:2000'],
            'affected_zone_geojson' => ['nullable', 'array'],
            'is_national' => ['nullable', 'boolean'],
            'is_emergency_declared' => ['nullable', 'boolean'],
            'update_notes' => ['nullable', 'string', 'max:1000'],
            'locations' => ['nullable', 'array'],
            'locations.*.administrative_unit_id' => ['required', 'uuid'],
            'locations.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'locations.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'locations.*.severity_override' => ['nullable', 'string', Rule::in(DisasterEvent::SEVERITY_LEVELS)],
            'locations.*.notes' => ['nullable', 'string', 'max:500'],
            'impact' => ['nullable', 'array'],
        ];
    }
}
