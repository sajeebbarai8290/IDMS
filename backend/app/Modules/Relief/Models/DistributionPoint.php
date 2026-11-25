<?php

namespace App\Modules\Relief\Models;

use App\Core\Models\AdministrativeUnit;
use App\Core\Traits\HasJurisdictionScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DistributionPoint extends Model
{
    use HasUuids, SoftDeletes, HasJurisdictionScope;

    protected $table = 'distribution.distribution_points';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'distribution.distribution_points'
            : 'distribution_points';
    }

    protected $fillable = [
        'administrative_unit_id',
        'point_code',
        'point_name',
        'point_name_bn',
        'address',
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

    public function vouchers(): HasMany
    {
        return $this->hasMany(DistributionVoucher::class, 'distribution_point_id');
    }
}
