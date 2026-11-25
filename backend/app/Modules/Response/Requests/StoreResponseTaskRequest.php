<?php

namespace App\Modules\Response\Requests;

use App\Modules\Response\Models\ResponseActivityType;
use App\Modules\Response\Models\ResponseOperation;
use App\Modules\Response\Models\ResponseTeam;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreResponseTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $operation = $this->route('operation');
        return $operation instanceof ResponseOperation && Gate::allows('manageTasks', $operation);
    }

    public function rules(): array
    {
        $actTable = (new ResponseActivityType())->getTable();
        $teamTable = (new ResponseTeam())->getTable();

        return [
            'activity_type_id' => ['required', 'uuid', "exists:{$actTable},id"],
            'response_team_id' => ['nullable', 'uuid', "exists:{$teamTable},id"],
            'title_en' => ['required', 'string', 'max:180'],
            'title_bn' => ['nullable', 'string', 'max:220'],
            'description' => ['nullable', 'string', 'max:2000'],
            'assigned_officer_name' => ['nullable', 'string', 'max:140'],
            'priority' => ['required', 'string', Rule::in(ResponseOperation::PRIORITIES)],
            'deadline_at' => ['nullable', 'date'],
        ];
    }
}
