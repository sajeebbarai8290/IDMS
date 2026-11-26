<?php

namespace App\Modules\Warehouse\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseTransferItem extends Model
{
    use HasUuids;

    protected $table = 'logistics.warehouse_transfer_items';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'logistics.warehouse_transfer_items'
            : 'warehouse_transfer_items';
    }

    protected $fillable = [
        'warehouse_transfer_id',
        'commodity_id',
        'batch_number',
        'quantity_requested',
        'quantity_dispatched',
        'quantity_received',
        'unit_cost_bdt',
        'expiry_date',
        'notes',
    ];

    protected $casts = [
        'quantity_requested' => 'float',
        'quantity_dispatched' => 'float',
        'quantity_received' => 'float',
        'unit_cost_bdt' => 'float',
        'expiry_date' => 'date',
    ];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(WarehouseTransfer::class, 'warehouse_transfer_id');
    }

    public function commodity(): BelongsTo
    {
        return $this->belongsTo(Commodity::class, 'commodity_id');
    }
}
