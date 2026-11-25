<?php

namespace App\Modules\Response\Requests;

use App\Core\Models\AdministrativeUnit;
use App\Modules\Disaster\Models\DisasterEvent;
use App\Modules\Response\Models\ResponseCenter;
use App\Modules\Response\Models\ResponseOperation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreResponseOperationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $existing = $this->route('operation');
        if ($existing instanceof ResponseOperation) {
            return Gate::allows('updateOperation', $existing);
        }

        return Gate::allows('createOperation', ResponseOperation::class);
    }

    public function rules(): array
    {
        $disasterTable = (new DisasterEvent())->getTable();
        $adminTable = (new AdministrativeUnit())->getTable();
        $centerTable = (new ResponseCenter())->getTable();

        return [
            'disaster_event_id' => ['required', 'uuid', "exists:{$disasterTable},id"],
            'administrative_unit_id' => ['required', 'uuid', "exists:{$adminTable},id"],
            'response_center_id' => ['nullable', 'uuid', "exists:{$centerTable},id"],
            'title_en' => ['required', 'string', 'max:180'],
            'title_bn' => ['nullable', 'string', 'max:220'],
            'objectives' => ['nullable', 'string', 'max:3000'],
            'description' => ['nullable', 'string', 'max:3000'],
            'priority' => ['required', 'string', Rule::in(ResponseOperation::PRIORITIES)],
            'severity_level' => ['required', 'string', Rule::in(ResponseOperation::SEVERITIES)],
            'start_date' => ['required', 'date'],
            'expected_completion_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'lead_organization' => ['required', 'string', 'max:160'],
            'commander_name' => ['nullable', 'string', 'max:150'],
        ];
    }
}
