<?php

namespace App\Modules\Warehouse\Models;

use App\Core\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockReceipt extends Model
{
    use HasUuids, SoftDeletes;

    public const STATUS_PENDING_VERIFICATION = 'PENDING_VERIFICATION';
    public const STATUS_VERIFIED = 'VERIFIED';
    public const STATUS_REJECTED = 'REJECTED';

    protected $table = 'logistics.stock_receipts';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'logistics.stock_receipts'
            : 'stock_receipts';
    }

    protected $fillable = [
        'receipt_no',
        'warehouse_id',
        'supplier_name',
        'supplier_contact',
        'purchase_order_no',
        'received_date',
        'status',
        'verification_notes',
        'attachment_path',
        'attachment_original_name',
        'attachment_mime',
        'attachment_size_bytes',
        'attachment_sha256',
        'idempotency_key',
        'received_by',
        'verified_by',
        'verified_at',
        'notes',
    ];

    protected $casts = [
        'received_date' => 'date',
        'verified_at' => 'datetime',
        'attachment_size_bytes' => 'integer',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockReceiptItem::class, 'stock_receipt_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(StockTransaction::class, 'stock_receipt_id');
    }
}
