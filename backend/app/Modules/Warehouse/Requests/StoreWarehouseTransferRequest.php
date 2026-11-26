<?php

namespace App\Modules\Warehouse\Requests;

use App\Modules\Warehouse\Models\Commodity;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Warehouse\Models\WarehouseTransfer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreWarehouseTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('requestTransfer', WarehouseTransfer::class);
    }

    public function rules(): array
    {
        $warehousesTable = (new Warehouse())->getTable();
        $commoditiesTable = (new Commodity())->getTable();

        return [
            'source_warehouse_id' => ['required', 'uuid', "exists:{$warehousesTable},id", 'different:destination_warehouse_id'],
            'destination_warehouse_id' => ['required', 'uuid', "exists:{$warehousesTable},id", 'different:source_warehouse_id'],
            'requested_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:requested_date'],
            'vehicle_info' => ['nullable', 'string', 'max:180'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.commodity_id' => ['required', 'uuid', "exists:{$commoditiesTable},id"],
            'items.*.batch_number' => ['nullable', 'string', 'max:50'],
            'items.*.quantity_requested' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
