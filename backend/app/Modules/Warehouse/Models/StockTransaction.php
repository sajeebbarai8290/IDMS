<?php

namespace App\Modules\Warehouse\Models;

use App\Core\Models\User;
use App\Modules\Relief\Models\DistributionVoucher;
use App\Modules\Relief\Models\ReliefAllocationItem;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransaction extends Model
{
    use HasUuids;

    public const TYPE_STOCK_IN_GRN = 'STOCK_IN_GRN';
    public const TYPE_INTER_WAREHOUSE_TRANSFER = 'INTER_WAREHOUSE_TRANSFER';
    public const TYPE_DISTRIBUTION_ISSUE = 'DISTRIBUTION_ISSUE';
    public const TYPE_ALLOCATION_RETURN = 'ALLOCATION_RETURN';
    public const TYPE_WRITE_OFF_DAMAGED = 'WRITE_OFF_DAMAGED';

    protected $table = 'logistics.stock_transactions';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'logistics.stock_transactions'
            : 'stock_transactions';
    }

    protected $fillable = [
        'warehouse_inventory_id',
        'transaction_type',
        'quantity',
        'balance_after',
        'reference_voucher_no',
        'relief_allocation_item_id',
        'distribution_voucher_id',
        'notes',
        'authorized_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function warehouseInventory(): BelongsTo
    {
        return $this->belongsTo(WarehouseInventory::class, 'warehouse_inventory_id');
    }

    public function reliefAllocationItem(): BelongsTo
    {
        return $this->belongsTo(ReliefAllocationItem::class, 'relief_allocation_item_id');
    }

    public function distributionVoucher(): BelongsTo
    {
        return $this->belongsTo(DistributionVoucher::class, 'distribution_voucher_id');
    }

    public function authorizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }
}
