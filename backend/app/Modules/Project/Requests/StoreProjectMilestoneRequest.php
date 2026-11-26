<?php

namespace App\Modules\Project\Requests;

use App\Core\Models\User;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectMilestone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreProjectMilestoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Project $project */
        $project = $this->route('project');
        return Gate::allows('updateMilestone', $project);
    }

    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');
        $milestonesTable = (new ProjectMilestone())->getTable();
        $usersTable = (new User())->getTable();

        return [
            'parent_milestone_id' => ['nullable', 'uuid', "exists:{$milestonesTable},id"],
            'wbs_code' => [
                'required',
                'string',
                'max:40',
                Rule::unique($milestonesTable, 'wbs_code')->where('project_id', $project->id),
            ],
            'milestone_title' => ['required', 'string', 'max:200'],
            'milestone_title_bn' => ['nullable', 'string', 'max:240'],
            'item_type' => ['nullable', 'string', Rule::in(ProjectMilestone::ITEM_TYPES)],
            'deliverables' => ['nullable', 'string', 'max:2000'],
            'responsible_organization' => ['nullable', 'string', 'max:180'],
            'assigned_officer_name' => ['nullable', 'string', 'max:150'],
            'assigned_officer_user_id' => ['nullable', 'uuid', "exists:{$usersTable},id"],
            'planned_start_date' => ['nullable', 'date'],
            'planned_end_date' => ['nullable', 'date', 'after_or_equal:planned_start_date'],
            'weight_percentage' => ['nullable', 'numeric', 'min:0.01', 'max:100'],
            'target_percentage' => ['nullable', 'numeric', 'min:1', 'max:100'],
        ];
    }
}
