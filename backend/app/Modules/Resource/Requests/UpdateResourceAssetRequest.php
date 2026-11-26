<?php

namespace App\Modules\Resource\Requests;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Modules\Resource\Models\ResourceAsset;
use App\Modules\Resource\Models\ResourceCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateResourceAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $asset = $this->route('asset');
        return $asset instanceof ResourceAsset && Gate::allows('updateAsset', $asset);
    }

    public function rules(): array
    {
        $catTable = (new ResourceCategory())->getTable();
        $adminTable = (new AdministrativeUnit())->getTable();
        $usersTable = (new User())->getTable();
        $assetsTable = (new ResourceAsset())->getTable();
        $asset = $this->route('asset');

        return [
            'category_id' => ['sometimes', 'uuid', "exists:{$catTable},id"],
            'administrative_unit_id' => ['sometimes', 'uuid', "exists:{$adminTable},id"],
            'asset_code' => [
                'sometimes',
                'string',
                'max:40',
                Rule::unique($assetsTable, 'asset_code')->ignore($asset?->id),
            ],
            'asset_type' => ['nullable', 'string', 'max:50'],
            'resource_type' => ['sometimes', 'string', 'max:80'],
            'name_en' => ['sometimes', 'string', 'max:150'],
            'name_bn' => ['sometimes', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'registration_no' => ['nullable', 'string', 'max:60'],
            'capacity_info' => ['nullable', 'string', 'max:120'],
            'specifications' => ['nullable', 'array'],
            'owning_organization' => ['sometimes', 'string', 'max:180'],
            'current_location' => ['sometimes', 'string', 'max:255'],
            'responsible_officer_name' => ['sometimes', 'string', 'max:150'],
            'responsible_officer_contact' => ['nullable', 'string', 'max:60'],
            'responsible_user_id' => ['nullable', 'uuid', "exists:{$usersTable},id"],
            'acquisition_date' => ['nullable', 'date'],
            'acquisition_value_bdt' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'next_maintenance_due_at' => ['nullable', 'date'],
            'status' => ['sometimes', 'string', Rule::in(ResourceAsset::STATUSES)],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
