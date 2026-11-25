<?php

namespace App\Modules\Response\Models;

use App\Core\Models\AdministrativeUnit;
use App\Core\Traits\HasJurisdictionScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResourceAsset extends Model
{
    use HasUuids, SoftDeletes, HasJurisdictionScope;

    public const STATUS_AVAILABLE = 'AVAILABLE';
    public const STATUS_DEPLOYED = 'DEPLOYED';
    public const STATUS_MAINTENANCE = 'MAINTENANCE';
    public const STATUS_RETIRED = 'RETIRED';

    protected $table = 'resource.resource_assets';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'resource.resource_assets'
            : 'resource_assets';
    }

    protected $fillable = [
        'administrative_unit_id',
        'asset_code',
        'asset_type',
        'name_en',
        'name_bn',
        'registration_no',
        'capacity_info',
        'status',
        'latitude',
        'longitude',
        'is_active',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'is_active' => 'boolean',
    ];

    public function administrativeUnit(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class, 'administrative_unit_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ResponseResourceAllocation::class, 'resource_asset_id');
    }
}
