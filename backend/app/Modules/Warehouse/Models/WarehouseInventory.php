<?php

namespace App\Modules\Warehouse\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseInventory extends Model
{
    use HasUuids;

    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_EXHAUSTED = 'EXHAUSTED';
    public const STATUS_EXPIRED = 'EXPIRED';
    public const STATUS_QUARANTINED = 'QUARANTINED';

    protected $table = 'logistics.warehouse_inventories';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'logistics.warehouse_inventories'
            : 'warehouse_inventories';
    }

    protected $fillable = [
        'warehouse_id',
        'commodity_id',
        'batch_number',
        'opening_quantity',
        'current_quantity',
        'reserved_quantity',
        'damaged_quantity',
        'expired_quantity',
        'unit_cost_bdt',
        'received_date',
        'expiry_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'opening_quantity' => 'float',
        'current_quantity' => 'float',
        'reserved_quantity' => 'float',
        'damaged_quantity' => 'float',
        'expired_quantity' => 'float',
        'unit_cost_bdt' => 'float',
        'received_date' => 'date',
        'expiry_date' => 'date',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function commodity(): BelongsTo
    {
        return $this->belongsTo(Commodity::class, 'commodity_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(StockTransaction::class, 'warehouse_inventory_id');
    }

    public function getAvailableQuantityAttribute(): float
    {
        return max(0.0, (float) $this->current_quantity - (float) $this->reserved_quantity);
    }
}
