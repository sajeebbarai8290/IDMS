<?php

namespace App\Modules\Relief\Models;

use App\Modules\Warehouse\Models\Commodity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReliefAllocationItem extends Model
{
    use HasUuids;

    protected $table = 'logistics.relief_allocation_items';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'logistics.relief_allocation_items'
            : 'relief_allocation_items';
    }

    protected $fillable = [
        'relief_allocation_id',
        'commodity_id',
        'quantity_allocated',
        'quantity_distributed',
        'quantity_cancelled',
    ];

    protected $casts = [
        'quantity_allocated' => 'decimal:2',
        'quantity_distributed' => 'decimal:2',
        'quantity_cancelled' => 'decimal:2',
    ];

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(ReliefAllocation::class, 'relief_allocation_id');
    }

    public function commodity(): BelongsTo
    {
        return $this->belongsTo(Commodity::class, 'commodity_id');
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(DistributionVoucher::class, 'relief_allocation_item_id');
    }

    public function getRemainingQuantityAttribute(): float
    {
        return max(
            0.0,
            (float) $this->quantity_allocated
            - (float) $this->quantity_distributed
            - (float) $this->quantity_cancelled
        );
    }
}
