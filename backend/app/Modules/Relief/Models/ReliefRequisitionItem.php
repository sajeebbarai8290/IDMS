<?php

namespace App\Modules\Relief\Models;

use App\Modules\Warehouse\Models\Commodity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReliefRequisitionItem extends Model
{
    use HasUuids;

    protected $table = 'logistics.relief_requisition_items';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'logistics.relief_requisition_items'
            : 'relief_requisition_items';
    }

    protected $fillable = [
        'relief_requisition_id',
        'commodity_id',
        'quantity_requested',
        'quantity_approved',
        'notes',
    ];

    protected $casts = [
        'quantity_requested' => 'decimal:2',
        'quantity_approved' => 'decimal:2',
    ];

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(ReliefRequisition::class, 'relief_requisition_id');
    }

    public function commodity(): BelongsTo
    {
        return $this->belongsTo(Commodity::class, 'commodity_id');
    }
}
