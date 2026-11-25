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
        'capacity_mt',
        'latitude',
        'longitude',
        'is_active',
    ];

    protected $casts = [
        'capacity_mt' => 'decimal:2',
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

    public function inventories(): HasMany
    {
        return $this->hasMany(WarehouseInventory::class, 'warehouse_id');
    }
}
