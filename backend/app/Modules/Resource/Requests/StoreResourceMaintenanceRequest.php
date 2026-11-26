<?php

namespace App\Modules\Resource\Requests;

use App\Modules\Resource\Models\ResourceAsset;
use App\Modules\Resource\Models\ResourceMaintenance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreResourceMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $asset = $this->route('asset');
        return $asset instanceof ResourceAsset && Gate::allows('manageMaintenance', $asset);
    }

    public function rules(): array
    {
        return [
            'record_type' => ['required', 'string', Rule::in(ResourceMaintenance::RECORD_TYPES)],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'scheduled_date' => ['nullable', 'date'],
            'performed_date' => ['nullable', 'date'],
            'next_due_date' => ['nullable', 'date'],
            'reminder_days_before' => ['nullable', 'integer', 'min:1', 'max:180'],
            'inspection_findings' => ['nullable', 'string', 'max:2000'],
            'inspection_result' => ['nullable', 'string', Rule::in(ResourceMaintenance::INSPECTION_RESULTS)],
            'repair_cost_bdt' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'vendor_or_workshop' => ['nullable', 'string', 'max:180'],
            'status' => ['sometimes', 'string', Rule::in(ResourceMaintenance::STATUSES)],
            'performed_by_name' => ['nullable', 'string', 'max:150'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx'],
        ];
    }
}
