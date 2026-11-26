<?php

namespace App\Modules\Project\Requests;

use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectDisbursement;
use App\Modules\Project\Models\ProjectMilestone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreProjectDisbursementRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Project $project */
        $project = $this->route('project');
        return Gate::allows('createDisbursement', $project);
    }

    public function rules(): array
    {
        $disbursementsTable = (new ProjectDisbursement())->getTable();
        $milestonesTable = (new ProjectMilestone())->getTable();

        return [
            'project_milestone_id' => ['nullable', 'uuid', "exists:{$milestonesTable},id"],
            'record_type' => ['required', 'string', Rule::in(ProjectDisbursement::RECORD_TYPES)],
            'voucher_no' => ['nullable', 'string', 'max:60', Rule::unique($disbursementsTable, 'voucher_no')],
            'fiscal_year' => ['nullable', 'string', 'max:20'],
            'amount_bdt' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:3000'],
            'disbursed_at' => ['nullable', 'date'],
            'supporting_doc' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
        ];
    }
}
