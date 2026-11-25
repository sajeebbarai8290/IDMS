<?php

namespace App\Modules\Relief\Requests;

use App\Modules\Beneficiary\Models\Household;
use Illuminate\Foundation\Http\FormRequest;

class AssignAllocationBeneficiariesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $householdTable = (new Household())->getTable();

        return [
            'household_ids' => ['required', 'array', 'min:1'],
            'household_ids.*' => ['required', 'uuid', "exists:{$householdTable},id"],
        ];
    }
}
