<?php

namespace App\Modules\Warehouse\Requests;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('createFacility', Warehouse::class);
    }

    public function rules(): array
    {
        $warehousesTable = (new Warehouse())->getTable();
        $adminTable = (new AdministrativeUnit())->getTable();
        $usersTable = (new User())->getTable();

        return [
            'administrative_unit_id' => ['required', 'uuid', "exists:{$adminTable},id"],
            'warehouse_code' => ['required', 'string', 'max:30', "unique:{$warehousesTable},warehouse_code"],
            'warehouse_name' => ['required', 'string', 'max:150'],
            'warehouse_name_bn' => ['nullable', 'string', 'max:200'],
            'tier' => [
                'required',
                'string',
                Rule::in([
                    Warehouse::TIER_CENTRAL_CSD,
                    Warehouse::TIER_REGIONAL_LSD,
                    Warehouse::TIER_DISTRICT_GODOWN,
                    Warehouse::TIER_UPAZILA_STORE,
                ]),
            ],
            'warehouse_type' => [
                'nullable',
                'string',
                Rule::in([
                    Warehouse::TYPE_RELIEF_GODOWN,
                    Warehouse::TYPE_COLD_STORAGE,
                    Warehouse::TYPE_FOOD_SILO,
                    Warehouse::TYPE_EMERGENCY_HUB,
                    Warehouse::TYPE_MEDICAL_DEPOT,
                ]),
            ],
            'owning_organization' => ['nullable', 'string', 'max:160'],
            'responsible_officer_name' => ['nullable', 'string', 'max:120'],
            'responsible_officer_contact' => ['nullable', 'string', 'max:40'],
            'responsible_user_id' => ['nullable', 'uuid', "exists:{$usersTable},id"],
            'address' => ['nullable', 'string', 'max:1000'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'contact_email' => ['nullable', 'email', 'max:150'],
            'capacity_mt' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
