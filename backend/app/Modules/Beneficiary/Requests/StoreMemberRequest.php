<?php

namespace App\Modules\Beneficiary\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $household = $this->route('household');
        return $this->user()?->can('update', $household) ?? false;
    }

    public function rules(): array
    {
        return [
            'full_name_en' => 'required|string|max:150',
            'full_name_bn' => 'nullable|string|max:200',
            'relationship_to_hoh' => 'required|in:SPOUSE,CHILD,PARENT,SIBLING,OTHER',
            'gender' => 'required|in:MALE,FEMALE,OTHER',
            'age' => 'nullable|integer|min:0|max:130',
            'date_of_birth' => 'nullable|date',
            'nid' => 'nullable|string|regex:/^(?:\d{10}|\d{13}|\d{17})$/',
            'has_disability' => 'nullable|boolean',
            'disability_type' => 'nullable|string|max:50',
            'is_pregnant_lactating' => 'nullable|boolean',
            'occupation' => 'nullable|string|max:100',
        ];
    }
}
