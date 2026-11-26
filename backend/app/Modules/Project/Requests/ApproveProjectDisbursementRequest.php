<?php

namespace App\Modules\Project\Requests;

use App\Modules\Project\Models\ProjectDisbursement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ApproveProjectDisbursementRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ProjectDisbursement $disbursement */
        $disbursement = $this->route('disbursement');
        return Gate::allows('approveDisbursement', $disbursement);
    }

    public function rules(): array
    {
        return [
            'payment_status' => [
                'required',
                'string',
                Rule::in([
                    ProjectDisbursement::STATUS_APPROVED,
                    ProjectDisbursement::STATUS_RELEASED,
                    ProjectDisbursement::STATUS_RECONCILED,
                    ProjectDisbursement::STATUS_REJECTED,
                ]),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
