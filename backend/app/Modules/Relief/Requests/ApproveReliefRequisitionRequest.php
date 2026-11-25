<?php

namespace App\Modules\Relief\Requests;

use App\Modules\Relief\Models\ReliefRequisitionItem;
use Illuminate\Foundation\Http\FormRequest;

class ApproveReliefRequisitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $itemTable = (new ReliefRequisitionItem())->getTable();

        return [
            'review_notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['nullable', 'array', 'min:1'],
            'items.*.id' => ['required_with:items', 'uuid', "exists:{$itemTable},id"],
            'items.*.quantity_approved' => ['required_with:items', 'numeric', 'min:0', 'max:10000000'],
        ];
    }
}
