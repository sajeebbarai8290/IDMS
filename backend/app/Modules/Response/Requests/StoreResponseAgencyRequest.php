<?php

namespace App\Modules\Response\Requests;

use App\Modules\Response\Models\ResponseOperation;
use App\Modules\Response\Models\ResponseOperationAgency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreResponseAgencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $operation = $this->route('operation');
        return $operation instanceof ResponseOperation && Gate::allows('manageCoordination', $operation);
    }

    public function rules(): array
    {
        return [
            'agency_name' => ['required', 'string', 'max:160'],
            'agency_type' => ['required', 'string', Rule::in(ResponseOperationAgency::AGENCY_TYPES)],
            'role_summary' => ['nullable', 'string', 'max:200'],
            'focal_person_name' => ['nullable', 'string', 'max:120'],
            'focal_contact' => ['nullable', 'string', 'max:60'],
        ];
    }
}
