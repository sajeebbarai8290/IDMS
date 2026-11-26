<?php

namespace App\Modules\Resource\Models;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Core\Traits\HasJurisdictionScope;
use App\Modules\Disaster\Models\DisasterEvent;
use App\Modules\Response\Models\ResponseOperation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResourceAllocation extends Model
{
    use HasUuids, SoftDeletes, HasJurisdictionScope;

    public const STATUS_ASSIGNED = 'ASSIGNED';
    public const STATUS_IN_USE = 'IN_USE';
    public const STATUS_RETURNED = 'RETURNED';
    public const STATUS_CANCELLED = 'CANCELLED';

    public const ACTIVE_STATUSES = [
        self::STATUS_ASSIGNED,
        self::STATUS_IN_USE,
    ];

    public const STATUSES = [
        self::STATUS_ASSIGNED,
        self::STATUS_IN_USE,
        self::STATUS_RETURNED,
        self::STATUS_CANCELLED,
    ];

    protected $table = 'resource.resource_allocations';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'resource.resource_allocations'
            : 'resource_allocations';
    }

    protected $fillable = [
        'resource_asset_id',
        'disaster_event_id',
        'response_operation_id',
        'administrative_unit_id',
        'allocation_no',
        'allocation_location',
        'latitude',
        'longitude',
        'assignment_date',
        'expected_return_date',
        'actual_return_date',
        'responsible_officer_name',
        'responsible_officer_contact',
        'responsible_user_id',
        'status',
        'purpose',
        'utilization_notes',
        'return_condition_notes',
        'allocated_by',
        'released_by',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'assignment_date' => 'date',
        'expected_return_date' => 'date',
        'actual_return_date' => 'date',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(ResourceAsset::class, 'resource_asset_id');
    }

    public function disasterEvent(): BelongsTo
    {
        return $this->belongsTo(DisasterEvent::class, 'disaster_event_id');
    }

    public function responseOperation(): BelongsTo
    {
        return $this->belongsTo(ResponseOperation::class, 'response_operation_id');
    }

    public function administrativeUnit(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class, 'administrative_unit_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function allocator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allocated_by');
    }

    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }
}
