<?php

namespace App\Modules\Disaster\Requests;

use App\Modules\Disaster\Models\DisasterEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDisasterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'disaster_code' => ['nullable', 'string', 'max:30'],
            'category_id' => ['nullable', 'uuid'],
            'disaster_type' => ['required', 'string', 'max:50'],
            'subtype' => ['nullable', 'string', 'max:80'],
            'name_en' => ['required', 'string', 'max:150'],
            'name_bn' => ['required', 'string', 'max:200'],
            'description_en' => ['nullable', 'string', 'max:5000'],
            'description_bn' => ['nullable', 'string', 'max:5000'],
            'severity_level' => ['required', 'string', Rule::in(DisasterEvent::SEVERITY_LEVELS)],
            'lifecycle_phase' => ['nullable', 'string', Rule::in(array_merge(DisasterEvent::LIFECYCLE_PHASES, ['IMPENDING', 'RESPONSE']))],
            'declaration_date' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'epicenter_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'epicenter_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'affected_areas_summary' => ['nullable', 'string', 'max:2000'],
            'affected_zone_geojson' => ['nullable', 'array'],
            'is_national' => ['nullable', 'boolean'],
            'is_emergency_declared' => ['nullable', 'boolean'],
            'locations' => ['nullable', 'array'],
            'locations.*.administrative_unit_id' => ['required', 'uuid'],
            'locations.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'locations.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'locations.*.severity_override' => ['nullable', 'string', Rule::in(DisasterEvent::SEVERITY_LEVELS)],
            'locations.*.notes' => ['nullable', 'string', 'max:500'],
            'impact' => ['nullable', 'array'],
            'impact.affected_population' => ['nullable', 'integer', 'min:0'],
            'impact.affected_households' => ['nullable', 'integer', 'min:0'],
            'impact.deaths_count' => ['nullable', 'integer', 'min:0'],
            'impact.injured_count' => ['nullable', 'integer', 'min:0'],
            'impact.missing_count' => ['nullable', 'integer', 'min:0'],
            'impact.damaged_houses_fully' => ['nullable', 'integer', 'min:0'],
            'impact.damaged_houses_partially' => ['nullable', 'integer', 'min:0'],
            'impact.damaged_infrastructure_count' => ['nullable', 'integer', 'min:0'],
            'impact.agricultural_loss_acres' => ['nullable', 'numeric', 'min:0'],
            'impact.agricultural_loss_bdt' => ['nullable', 'numeric', 'min:0'],
            'impact.livestock_loss_count' => ['nullable', 'integer', 'min:0'],
            'impact.livestock_loss_bdt' => ['nullable', 'numeric', 'min:0'],
            'impact.total_economic_loss_bdt' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
