<?php

namespace App\Modules\Relief\Models;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Core\Traits\HasJurisdictionScope;
use App\Modules\Disaster\Models\DisasterEvent;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReliefAllocation extends Model
{
    use HasUuids, SoftDeletes, HasJurisdictionScope;

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_SANCTIONED = 'SANCTIONED';
    public const STATUS_DISPATCHED = 'DISPATCHED';
    public const STATUS_PARTIALLY_DISTRIBUTED = 'PARTIALLY_DISTRIBUTED';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_CANCELLED = 'CANCELLED';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SANCTIONED,
        self::STATUS_DISPATCHED,
        self::STATUS_PARTIALLY_DISTRIBUTED,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    protected $table = 'logistics.relief_allocations';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'logistics.relief_allocations'
            : 'relief_allocations';
    }

    protected $fillable = [
        'relief_requisition_id',
        'disaster_event_id',
        'administrative_unit_id',
        'distribution_point_id',
        'warehouse_id',
        'allocation_order_no',
        'status',
        'scheduled_start_at',
        'scheduled_end_at',
        'duplicate_window_days',
        'remarks',
        'created_by',
        'updated_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'scheduled_start_at' => 'datetime',
        'scheduled_end_at' => 'datetime',
        'duplicate_window_days' => 'integer',
        'approved_at' => 'datetime',
    ];

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(ReliefRequisition::class, 'relief_requisition_id');
    }

    public function disasterEvent(): BelongsTo
    {
        return $this->belongsTo(DisasterEvent::class, 'disaster_event_id');
    }

    public function administrativeUnit(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class, 'administrative_unit_id');
    }

    public function distributionPoint(): BelongsTo
    {
        return $this->belongsTo(DistributionPoint::class, 'distribution_point_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReliefAllocationItem::class, 'relief_allocation_id');
    }

    public function beneficiaries(): HasMany
    {
        return $this->hasMany(ReliefAllocationBeneficiary::class, 'relief_allocation_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
