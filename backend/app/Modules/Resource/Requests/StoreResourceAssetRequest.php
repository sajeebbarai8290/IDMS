<?php

namespace App\Modules\Resource\Requests;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Modules\Resource\Models\ResourceAsset;
use App\Modules\Resource\Models\ResourceCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreResourceAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('createAsset', ResourceAsset::class);
    }

    public function rules(): array
    {
        $catTable = (new ResourceCategory())->getTable();
        $adminTable = (new AdministrativeUnit())->getTable();
        $usersTable = (new User())->getTable();
        $assetsTable = (new ResourceAsset())->getTable();

        return [
            'category_id' => ['required', 'uuid', "exists:{$catTable},id"],
            'administrative_unit_id' => ['required', 'uuid', "exists:{$adminTable},id"],
            'asset_code' => ['required', 'string', 'max:40', "unique:{$assetsTable},asset_code"],
            'asset_type' => ['nullable', 'string', 'max:50'],
            'resource_type' => ['required', 'string', 'max:80'],
            'name_en' => ['required', 'string', 'max:150'],
            'name_bn' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'registration_no' => ['nullable', 'string', 'max:60'],
            'capacity_info' => ['nullable', 'string', 'max:120'],
            'specifications' => ['nullable', 'array'],
            'owning_organization' => ['required', 'string', 'max:180'],
            'current_location' => ['required', 'string', 'max:255'],
            'responsible_officer_name' => ['required', 'string', 'max:150'],
            'responsible_officer_contact' => ['nullable', 'string', 'max:60'],
            'responsible_user_id' => ['nullable', 'uuid', "exists:{$usersTable},id"],
            'acquisition_date' => ['nullable', 'date'],
            'acquisition_value_bdt' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'next_maintenance_due_at' => ['nullable', 'date'],
            'status' => ['sometimes', 'string', Rule::in(ResourceAsset::PRIMARY_STATUSES)],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
