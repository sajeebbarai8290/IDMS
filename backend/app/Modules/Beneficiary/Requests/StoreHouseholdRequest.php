<?php

namespace App\Modules\Beneficiary\Requests;

use App\Modules\Beneficiary\Models\Household;
use Illuminate\Foundation\Http\FormRequest;

class StoreHouseholdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Household::class) ?? false;
    }

    public function rules(): array
    {
        $adminTable = (new \App\Core\Models\AdministrativeUnit())->getTable();

        return [
            'administrative_unit_id' => "required|uuid|exists:{$adminTable},id",
            'village_ward_name' => 'nullable|string|max:150',
            'present_address' => 'required|string|max:500',
            'permanent_address' => 'nullable|string|max:500',
            'hoh_name_en' => 'required|string|max:150',
            'hoh_name_bn' => 'required|string|max:200',
            'hoh_nid' => ['required', 'string', 'regex:/^(?:\d{10}|\d{13}|\d{17})$/'],
            'hoh_dob' => 'nullable|date',
            'hoh_gender' => 'required|in:MALE,FEMALE,OTHER',
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
            'members' => 'nullable|array',
            'members.*.full_name_en' => 'required_with:members|string|max:150',
            'members.*.full_name_bn' => 'nullable|string|max:200',
            'members.*.relationship_to_hoh' => 'required_with:members|in:SPOUSE,CHILD,PARENT,SIBLING,OTHER',
            'members.*.gender' => 'required_with:members|in:MALE,FEMALE,OTHER',
            'members.*.age' => 'nullable|integer|min:0|max:130',
            'members.*.date_of_birth' => 'nullable|date',
            'members.*.has_disability' => 'nullable|boolean',
            'members.*.disability_type' => 'nullable|string|max:50',
            'members.*.is_pregnant_lactating' => 'nullable|boolean',
            'members.*.nid' => 'nullable|string|regex:/^(?:\d{10}|\d{13}|\d{17})$/',
        ];
    }

    public function messages(): array
    {
        return [
            'hoh_name_en.required' => 'Head of Household English name is required (পরিবারের প্রধানের নাম আবশ্যক)।',
            'hoh_name_bn.required' => 'Head of Household Bangla name is required (পরিবারের প্রধানের বাংলা নাম আবশ্যক)।',
            'hoh_nid.required' => 'National ID (NID) is required for registration (জাতীয় পরিচয়পত্র নম্বর আবশ্যক)।',
            'hoh_nid.regex' => 'National ID must be 10, 13, or 17 numeric digits (এনআইডি ১০, ১৩ বা ১৭ ডিজিটের হতে হবে)।',
            'administrative_unit_id.required' => 'Jurisdiction (Union/Ward) is required (অধিক্ষেত্র বা ইউনিয়ন আবশ্যক)।',
        ];
    }
}
