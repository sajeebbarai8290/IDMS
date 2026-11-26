<?php

namespace App\Modules\Warehouse\Models;

use App\Core\Models\User;
use App\Modules\Relief\Models\DistributionVoucher;
use App\Modules\Relief\Models\ReliefAllocationItem;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class StockTransaction extends Model
{
    use HasUuids;

    public const TYPE_OPENING_STOCK = 'OPENING_STOCK';
    public const TYPE_STOCK_IN_GRN = 'STOCK_IN_GRN';
    public const TYPE_INTER_WAREHOUSE_TRANSFER = 'INTER_WAREHOUSE_TRANSFER';
    public const TYPE_DISTRIBUTION_ISSUE = 'DISTRIBUTION_ISSUE';
    public const TYPE_STOCK_ISSUE = 'STOCK_ISSUE';
    public const TYPE_ALLOCATION_RETURN = 'ALLOCATION_RETURN';
    public const TYPE_RETURN_IN = 'RETURN_IN';
    public const TYPE_STOCK_ADJUSTMENT = 'STOCK_ADJUSTMENT';
    public const TYPE_WRITE_OFF_DAMAGED = 'WRITE_OFF_DAMAGED';
    public const TYPE_EXPIRED_STOCK = 'EXPIRED_STOCK';

    protected $table = 'logistics.stock_transactions';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'logistics.stock_transactions'
            : 'stock_transactions';
    }

    protected $fillable = [
        'warehouse_inventory_id',
        'warehouse_id',
        'commodity_id',
        'stock_receipt_id',
        'warehouse_transfer_id',
        'transaction_type',
        'reference_type',
        'reason_code',
        'quantity',
        'balance_after',
        'reference_voucher_no',
        'idempotency_key',
        'relief_allocation_item_id',
        'distribution_voucher_id',
        'notes',
        'authorized_by',
        'approved_by',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'float',
        'balance_after' => 'float',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Stock ledger transactions are immutable and cannot be modified. Post an authorized adjustment transaction instead.');
        });

        static::deleting(function (): void {
            throw new LogicException('Stock ledger transactions are immutable and cannot be deleted.');
        });
    }

    public function warehouseInventory(): BelongsTo
    {
        return $this->belongsTo(WarehouseInventory::class, 'warehouse_inventory_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function commodity(): BelongsTo
    {
        return $this->belongsTo(Commodity::class, 'commodity_id');
    }

    public function stockReceipt(): BelongsTo
    {
        return $this->belongsTo(StockReceipt::class, 'stock_receipt_id');
    }

    public function warehouseTransfer(): BelongsTo
    {
        return $this->belongsTo(WarehouseTransfer::class, 'warehouse_transfer_id');
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

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
