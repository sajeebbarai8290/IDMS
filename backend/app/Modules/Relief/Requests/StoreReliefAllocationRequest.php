<?php

namespace App\Modules\Relief\Requests;

use App\Core\Models\AdministrativeUnit;
use App\Modules\Beneficiary\Models\Household;
use App\Modules\Disaster\Models\DisasterEvent;
use App\Modules\Relief\Models\DistributionPoint;
use App\Modules\Relief\Models\ReliefAllocation;
use App\Modules\Relief\Models\ReliefRequisition;
use App\Modules\Warehouse\Models\Commodity;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreReliefAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('createAllocation', ReliefAllocation::class);
    }

    public function rules(): array
    {
        $reqTable = (new ReliefRequisition())->getTable();
        $disasterTable = (new DisasterEvent())->getTable();
        $adminTable = (new AdministrativeUnit())->getTable();
        $pointTable = (new DistributionPoint())->getTable();
        $warehouseTable = (new Warehouse())->getTable();
        $commodityTable = (new Commodity())->getTable();
        $householdTable = (new Household())->getTable();

        return [
            'relief_requisition_id' => ['nullable', 'uuid', "exists:{$reqTable},id"],
            'disaster_event_id' => ['required', 'uuid', "exists:{$disasterTable},id"],
            'administrative_unit_id' => ['required', 'uuid', "exists:{$adminTable},id"],
            'distribution_point_id' => ['nullable', 'uuid', "exists:{$pointTable},id"],
            'warehouse_id' => ['required', 'uuid', "exists:{$warehouseTable},id"],
            'scheduled_start_at' => ['nullable', 'date'],
            'scheduled_end_at' => ['nullable', 'date', 'after_or_equal:scheduled_start_at'],
            'duplicate_window_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.commodity_id' => ['required', 'uuid', "exists:{$commodityTable},id"],
            'items.*.quantity_allocated' => ['required', 'numeric', 'gt:0', 'max:10000000'],
            'beneficiary_household_ids' => ['nullable', 'array'],
            'beneficiary_household_ids.*' => ['uuid', "exists:{$householdTable},id"],
        ];
    }
}
