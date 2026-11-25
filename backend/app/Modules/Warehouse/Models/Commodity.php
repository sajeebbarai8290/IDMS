<?php

namespace App\Modules\Warehouse\Models;

use App\Modules\Relief\Models\ReliefCategory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Commodity extends Model
{
    use HasUuids;

    protected $table = 'logistics.commodities';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'logistics.commodities'
            : 'commodities';
    }

    protected $fillable = [
        'relief_category_id',
        'commodity_code',
        'name_en',
        'name_bn',
        'category',
        'unit_of_measure',
        'is_perishable',
        'is_active',
    ];

    protected $casts = [
        'is_perishable' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function reliefCategory(): BelongsTo
    {
        return $this->belongsTo(ReliefCategory::class, 'relief_category_id');
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(WarehouseInventory::class, 'commodity_id');
    }
}
