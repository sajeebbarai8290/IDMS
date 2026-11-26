<?php

namespace App\Modules\Resource\Requests;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Modules\Disaster\Models\DisasterEvent;
use App\Modules\Resource\Models\HumanResourceDeployment;
use App\Modules\Resource\Models\ResourceAsset;
use App\Modules\Response\Models\ResponseOperation;
use App\Modules\Response\Models\ResponseTeam;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreHumanResourceDeploymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('deployResource', HumanResourceDeployment::class);
    }

    public function rules(): array
    {
        $assetsTable = (new ResourceAsset())->getTable();
        $usersTable = (new User())->getTable();
        $teamsTable = (new ResponseTeam())->getTable();
        $opsTable = (new ResponseOperation())->getTable();
        $disastersTable = (new DisasterEvent())->getTable();
        $adminTable = (new AdministrativeUnit())->getTable();

        return [
            'resource_asset_id' => ['nullable', 'uuid', "exists:{$assetsTable},id"],
            'user_id' => ['nullable', 'uuid', "exists:{$usersTable},id"],
            'response_team_id' => ['nullable', 'uuid', "exists:{$teamsTable},id"],
            'response_operation_id' => ['nullable', 'uuid', "exists:{$opsTable},id", 'required_without:disaster_event_id'],
            'disaster_event_id' => ['nullable', 'uuid', "exists:{$disastersTable},id"],
            'administrative_unit_id' => ['nullable', 'uuid', "exists:{$adminTable},id"],
            'personnel_name' => ['required', 'string', 'max:150'],
            'personnel_designation' => ['nullable', 'string', 'max:120'],
            'personnel_contact' => ['nullable', 'string', 'max:60'],
            'assigned_duty' => ['required', 'string', 'max:1500'],
            'deployment_location' => ['nullable', 'string', 'max:255'],
            'deployment_start_date' => ['required', 'date'],
            'deployment_end_date' => ['nullable', 'date', 'after_or_equal:deployment_start_date'],
            'availability_status' => ['sometimes', 'string', Rule::in([HumanResourceDeployment::STATUS_ASSIGNED, HumanResourceDeployment::STATUS_IN_USE])],
            'notes' => ['nullable', 'string', 'max:1500'],
        ];
    }
}
