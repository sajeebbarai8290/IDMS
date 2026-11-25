<?php

namespace App\Modules\Relief\Requests;

use App\Modules\Beneficiary\Models\Household;
use App\Modules\Relief\Models\DistributionPoint;
use App\Modules\Relief\Models\DistributionVoucher;
use App\Modules\Relief\Models\ReliefAllocationItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class RecordDistributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('recordDistribution', [DistributionVoucher::class, null]);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('allow_duplicate_exception') && !is_bool($this->input('allow_duplicate_exception'))) {
            $this->merge([
                'allow_duplicate_exception' => filter_var(
                    $this->input('allow_duplicate_exception'),
                    FILTER_VALIDATE_BOOLEAN
                ),
            ]);
        }
    }

    public function rules(): array
    {
        $allocItemTable = (new ReliefAllocationItem())->getTable();
        $householdTable = (new Household())->getTable();
        $pointTable = (new DistributionPoint())->getTable();

        return [
            'relief_allocation_item_id' => ['required', 'uuid', "exists:{$allocItemTable},id"],
            'household_id' => ['required', 'uuid', "exists:{$householdTable},id"],
            'distribution_point_id' => ['required', 'uuid', "exists:{$pointTable},id"],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:100'],
            'quantity_disbursed' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'verification_method' => ['required', 'string', Rule::in(DistributionVoucher::VERIFICATION_METHODS)],
            'verification_proof' => ['nullable', 'string', 'max:255'],
            'acknowledgment_signature' => ['nullable', 'string', 'max:2000'],
            'photo_evidence' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'distribution_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'distribution_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'allow_duplicate_exception' => ['sometimes', 'boolean'],
            'exception_reason' => ['nullable', 'string', 'max:1000'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
