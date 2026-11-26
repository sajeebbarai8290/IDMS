<?php

namespace App\Modules\Warehouse\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockReceiptItem extends Model
{
    use HasUuids;

    protected $table = 'logistics.stock_receipt_items';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'logistics.stock_receipt_items'
            : 'stock_receipt_items';
    }

    protected $fillable = [
        'stock_receipt_id',
        'commodity_id',
        'batch_number',
        'quantity_received',
        'unit_cost_bdt',
        'expiry_date',
        'warehouse_inventory_id',
        'notes',
    ];

    protected $casts = [
        'quantity_received' => 'float',
        'unit_cost_bdt' => 'float',
        'expiry_date' => 'date',
    ];

    public function stockReceipt(): BelongsTo
    {
        return $this->belongsTo(StockReceipt::class, 'stock_receipt_id');
    }

    public function commodity(): BelongsTo
    {
        return $this->belongsTo(Commodity::class, 'commodity_id');
    }

    public function warehouseInventory(): BelongsTo
    {
        return $this->belongsTo(WarehouseInventory::class, 'warehouse_inventory_id');
    }
}
