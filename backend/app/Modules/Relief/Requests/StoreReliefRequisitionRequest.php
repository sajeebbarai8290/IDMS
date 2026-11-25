<?php

namespace App\Modules\Relief\Requests;

use App\Core\Models\AdministrativeUnit;
use App\Modules\Disaster\Models\DisasterEvent;
use App\Modules\Relief\Models\ReliefRequisition;
use App\Modules\Warehouse\Models\Commodity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreReliefRequisitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $existing = $this->route('requisition');
        if ($existing instanceof ReliefRequisition) {
            $existing->loadMissing('administrativeUnit');
            return Gate::allows('updateRequisition', $existing);
        }

        return Gate::allows('createRequisition', ReliefRequisition::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('items'))) {
            $decoded = json_decode($this->input('items'), true);
            if (is_array($decoded)) {
                $this->merge(['items' => $decoded]);
            }
        }

        if ($this->has('submit_immediately') && !is_bool($this->input('submit_immediately'))) {
            $this->merge([
                'submit_immediately' => filter_var($this->input('submit_immediately'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }

    public function rules(): array
    {
        $disasterTable = (new DisasterEvent())->getTable();
        $adminTable = (new AdministrativeUnit())->getTable();
        $commodityTable = (new Commodity())->getTable();

        return [
            'disaster_event_id' => ['required', 'uuid', "exists:{$disasterTable},id"],
            'administrative_unit_id' => ['required', 'uuid', "exists:{$adminTable},id"],
            'requesting_organization' => ['nullable', 'string', 'max:150'],
            'estimated_beneficiaries' => ['required', 'integer', 'min:1', 'max:5000000'],
            'priority' => ['required', 'string', Rule::in(ReliefRequisition::PRIORITIES)],
            'request_date' => ['required', 'date'],
            'needed_by_date' => ['nullable', 'date', 'after_or_equal:request_date'],
            'justification' => ['nullable', 'string', 'max:2000'],
            'submit_immediately' => ['sometimes', 'boolean'],
            'supporting_doc' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.commodity_id' => ['required', 'uuid', "exists:{$commodityTable},id"],
            'items.*.quantity_requested' => ['required', 'numeric', 'gt:0', 'max:10000000'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
