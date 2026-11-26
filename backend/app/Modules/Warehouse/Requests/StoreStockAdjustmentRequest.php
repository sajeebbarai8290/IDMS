<?php

namespace App\Modules\Warehouse\Requests;

use App\Core\Models\User;
use App\Modules\Warehouse\Models\Commodity;
use App\Modules\Warehouse\Models\StockTransaction;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $warehousesTable = (new Warehouse())->getTable();
        $commoditiesTable = (new Commodity())->getTable();
        $usersTable = (new User())->getTable();

        return [
            'warehouse_id' => ['required', 'uuid', "exists:{$warehousesTable},id"],
            'commodity_id' => ['required', 'uuid', "exists:{$commoditiesTable},id"],
            'batch_number' => ['required', 'string', 'max:50'],
            'transaction_type' => [
                'required',
                'string',
                Rule::in([
                    StockTransaction::TYPE_OPENING_STOCK,
                    StockTransaction::TYPE_STOCK_ADJUSTMENT,
                    StockTransaction::TYPE_WRITE_OFF_DAMAGED,
                    StockTransaction::TYPE_EXPIRED_STOCK,
                    StockTransaction::TYPE_RETURN_IN,
                ]),
            ],
            'quantity' => ['required', 'numeric', 'not_in:0', 'max:999999999', 'min:-999999999'],
            'unit_cost_bdt' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'expiry_date' => ['nullable', 'date'],
            'reference_voucher_no' => ['required', 'string', 'max:60'],
            'reason_code' => ['required', 'string', 'max:60'],
            'notes' => ['required', 'string', 'max:2000'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
            'approved_by' => ['nullable', 'uuid', "exists:{$usersTable},id"],
        ];
    }
}
