<?php

namespace App\Modules\Resource\Requests;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Modules\Disaster\Models\DisasterEvent;
use App\Modules\Resource\Models\ResourceAllocation;
use App\Modules\Resource\Models\ResourceAsset;
use App\Modules\Response\Models\ResponseOperation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreResourceAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('deployResource', ResourceAllocation::class);
    }

    public function rules(): array
    {
        $assetsTable = (new ResourceAsset())->getTable();
        $disastersTable = (new DisasterEvent())->getTable();
        $opsTable = (new ResponseOperation())->getTable();
        $adminTable = (new AdministrativeUnit())->getTable();
        $usersTable = (new User())->getTable();

        return [
            'resource_asset_id' => ['required', 'uuid', "exists:{$assetsTable},id"],
            'disaster_event_id' => ['nullable', 'uuid', "exists:{$disastersTable},id", 'required_without:response_operation_id'],
            'response_operation_id' => ['nullable', 'uuid', "exists:{$opsTable},id"],
            'administrative_unit_id' => ['nullable', 'uuid', "exists:{$adminTable},id"],
            'allocation_location' => ['required', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'assignment_date' => ['required', 'date'],
            'expected_return_date' => ['nullable', 'date', 'after_or_equal:assignment_date'],
            'responsible_officer_name' => ['required', 'string', 'max:150'],
            'responsible_officer_contact' => ['nullable', 'string', 'max:60'],
            'responsible_user_id' => ['nullable', 'uuid', "exists:{$usersTable},id"],
            'status' => ['sometimes', 'string', Rule::in([ResourceAllocation::STATUS_ASSIGNED, ResourceAllocation::STATUS_IN_USE])],
            'purpose' => ['nullable', 'string', 'max:1500'],
            'utilization_notes' => ['nullable', 'string', 'max:1500'],
        ];
    }
}
