<?php

namespace App\Modules\Response\Requests;

use App\Core\Models\AdministrativeUnit;
use App\Modules\Response\Models\ResponseOperation;
use App\Modules\Response\Models\ResponseTeam;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreResponseTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        $operation = $this->route('operation');
        return $operation instanceof ResponseOperation && Gate::allows('manageCoordination', $operation);
    }

    public function rules(): array
    {
        $adminTable = (new AdministrativeUnit())->getTable();

        return [
            'administrative_unit_id' => ['nullable', 'uuid', "exists:{$adminTable},id"],
            'team_name' => ['required', 'string', 'max:150'],
            'agency_name' => ['required', 'string', 'max:150'],
            'team_type' => ['required', 'string', Rule::in(ResponseTeam::TEAM_TYPES)],
            'leader_name' => ['nullable', 'string', 'max:120'],
            'leader_contact' => ['nullable', 'string', 'max:50'],
            'member_count' => ['required', 'integer', 'min:1', 'max:2000'],
            'status' => ['sometimes', 'string', Rule::in(ResponseTeam::STATUSES)],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
