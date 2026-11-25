<?php

namespace App\Modules\Response\Models;

use App\Core\Models\AdministrativeUnit;
use App\Core\Traits\HasJurisdictionScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResponseCenter extends Model
{
    use HasUuids, SoftDeletes, HasJurisdictionScope;

    protected $table = 'response.response_centers';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'response.response_centers'
            : 'response_centers';
    }

    protected $fillable = [
        'administrative_unit_id',
        'center_code',
        'center_type',
        'name_en',
        'name_bn',
        'address',
        'contact_phone',
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

    public function operations(): HasMany
    {
        return $this->hasMany(ResponseOperation::class, 'response_center_id');
    }
}
