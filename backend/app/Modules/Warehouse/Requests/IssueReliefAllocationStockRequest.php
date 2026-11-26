<?php

namespace App\Modules\Warehouse\Requests;

use App\Modules\Relief\Models\ReliefAllocationItem;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class IssueReliefAllocationStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('issueStock', Warehouse::class);
    }

    public function rules(): array
    {
        $allocationItemsTable = (new ReliefAllocationItem())->getTable();

        return [
            'reference_voucher_no' => ['nullable', 'string', 'max:60'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.relief_allocation_item_id' => ['required', 'uuid', "exists:{$allocationItemsTable},id"],
            'items.*.quantity_to_issue' => ['required', 'numeric', 'gt:0', 'max:999999999'],
        ];
    }
}
