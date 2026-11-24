<?php

namespace App\Modules\Beneficiary\Requests;

use App\Core\Models\AdministrativeUnit;
use App\Modules\Beneficiary\Models\Household;
use Illuminate\Foundation\Http\FormRequest;

class UpdateHouseholdRequest extends FormRequest
{
    public function authorize(): bool
    {
        $household = $this->route('household');
        return $this->user()?->can('update', $household) ?? false;
    }

    public function rules(): array
    {
        $adminTable = (new AdministrativeUnit())->getTable();

        return [
            'administrative_unit_id' => "nullable|uuid|exists:{$adminTable},id",
            'village_ward_name' => 'nullable|string|max:150',
            'present_address' => 'nullable|string|max:500',
            'permanent_address' => 'nullable|string|max:500',
            'hoh_name_en' => 'nullable|string|max:150',
            'hoh_name_bn' => 'nullable|string|max:200',
            'hoh_dob' => 'nullable|date',
            'hoh_gender' => 'nullable|in:MALE,FEMALE,OTHER',
            'hoh_mobile' => 'nullable|string|max:20',
            'hoh_occupation' => 'nullable|string|max:100',
            'monthly_income_bdt' => 'nullable|numeric|min:0',
            'income_category' => 'nullable|in:EXTREME_POOR,POOR,LOWER_MIDDLE,MIDDLE',
            'family_member_count' => 'nullable|integer|min:1',
            'is_female_headed' => 'nullable|boolean',
            'has_elderly' => 'nullable|boolean',
            'has_disabled' => 'nullable|boolean',
            'has_children_infants' => 'nullable|boolean',
            'has_pregnant_lactating' => 'nullable|boolean',
            'is_displaced' => 'nullable|boolean',
            'other_vulnerabilities' => 'nullable|array',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ];
    }
}
