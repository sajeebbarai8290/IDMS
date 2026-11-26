<?php

namespace App\Modules\Warehouse\Models;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Core\Traits\HasJurisdictionScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use HasUuids, SoftDeletes, HasJurisdictionScope;

    public const TIER_CENTRAL_CSD = 'CENTRAL_CSD';
    public const TIER_REGIONAL_LSD = 'REGIONAL_LSD';
    public const TIER_DISTRICT_GODOWN = 'DISTRICT_GODOWN';
    public const TIER_UPAZILA_STORE = 'UPAZILA_STORE';

    public const TYPE_RELIEF_GODOWN = 'RELIEF_GODOWN';
    public const TYPE_COLD_STORAGE = 'COLD_STORAGE';
    public const TYPE_FOOD_SILO = 'FOOD_SILO';
    public const TYPE_EMERGENCY_HUB = 'EMERGENCY_HUB';
    public const TYPE_MEDICAL_DEPOT = 'MEDICAL_DEPOT';

    protected $table = 'logistics.warehouses';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'logistics.warehouses'
            : 'warehouses';
    }

    protected $fillable = [
        'administrative_unit_id',
        'warehouse_code',
        'warehouse_name',
        'warehouse_name_bn',
        'tier',
        'warehouse_type',
        'owning_organization',
        'responsible_officer_name',
        'responsible_officer_contact',
        'responsible_user_id',
        'address',
        'contact_phone',
        'contact_email',
        'capacity_mt',
        'latitude',
        'longitude',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'capacity_mt' => 'float',
        'latitude' => 'float',
        'longitude' => 'float',
        'is_active' => 'boolean',
    ];

    /**
     * Warehouses located in the user's jurisdiction OR upstream depots along their ancestry path.
     */
    public function scopeSupplyingJurisdiction(Builder $query, User $user): Builder
    {
        if ($user->isNationalScope()) {
            return $query;
        }

        $scope = $user->getAdministrativeScope();
        $officerPath = $scope['ancestry_path'] ?? '/';
        if ($officerPath === '/' || $officerPath === '') {
            return $query;
        }

        $normPath = rtrim($officerPath, '/') . '/';
        $segments = array_values(array_filter(explode('/', trim($normPath, '/'))));
        $ancestorPaths = ['/'];
        $built = '/';
        foreach ($segments as $seg) {
            $built .= $seg . '/';
            $ancestorPaths[] = $built;
        }

        $adminTable = (new AdministrativeUnit())->getTable();
        $warehouseTable = $this->getTable();

        return $query->whereExists(function ($subQuery) use ($adminTable, $warehouseTable, $normPath, $ancestorPaths) {
            $subQuery->selectRaw('1')
                ->from($adminTable)
                ->whereColumn("{$adminTable}.id", "{$warehouseTable}.administrative_unit_id")
                ->where(function ($w) use ($adminTable, $normPath, $ancestorPaths) {
                    $w->where("{$adminTable}.ancestry_path", 'like', $normPath . '%')
                        ->orWhereIn("{$adminTable}.ancestry_path", $ancestorPaths);
                });
        });
    }

    public function administrativeUnit(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class, 'administrative_unit_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(WarehouseInventory::class, 'warehouse_id');
    }

    public function stockReceipts(): HasMany
    {
        return $this->hasMany(StockReceipt::class, 'warehouse_id');
    }

    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(WarehouseTransfer::class, 'source_warehouse_id');
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(WarehouseTransfer::class, 'destination_warehouse_id');
    }
}
