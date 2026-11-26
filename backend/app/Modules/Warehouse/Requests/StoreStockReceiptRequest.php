<?php

namespace App\Modules\Warehouse\Requests;

use App\Modules\Warehouse\Models\Commodity;
use App\Modules\Warehouse\Models\StockReceipt;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreStockReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('receiveStock', StockReceipt::class);
    }

    public function rules(): array
    {
        $warehousesTable = (new Warehouse())->getTable();
        $commoditiesTable = (new Commodity())->getTable();

        return [
            'warehouse_id' => ['required', 'uuid', "exists:{$warehousesTable},id"],
            'supplier_name' => ['required', 'string', 'max:180'],
            'supplier_contact' => ['nullable', 'string', 'max:80'],
            'purchase_order_no' => ['nullable', 'string', 'max:80'],
            'received_date' => ['required', 'date'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.commodity_id' => ['required', 'uuid', "exists:{$commoditiesTable},id"],
            'items.*.batch_number' => ['required', 'string', 'max:50'],
            'items.*.quantity_received' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'items.*.unit_cost_bdt' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'items.*.expiry_date' => ['nullable', 'date'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
