<?php

namespace App\Modules\Response\Requests;

use App\Modules\Response\Models\ResourceAsset;
use App\Modules\Response\Models\ResponseOperation;
use App\Modules\Response\Models\ResponseResourceAllocation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreResponseResourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $operation = $this->route('operation');
        return $operation instanceof ResponseOperation && Gate::allows('manageCoordination', $operation);
    }

    public function rules(): array
    {
        $assetTable = (new ResourceAsset())->getTable();

        return [
            'resource_asset_id' => ['nullable', 'uuid', "exists:{$assetTable},id"],
            'requirement_title' => ['required', 'string', 'max:180'],
            'category' => ['nullable', 'string', 'max:60'],
            'quantity_needed' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'quantity_assigned' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'unit' => ['nullable', 'string', 'max:30'],
            'priority' => ['required', 'string', Rule::in(ResponseOperation::PRIORITIES)],
            'status' => ['sometimes', 'string', Rule::in(ResponseResourceAllocation::STATUSES)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
