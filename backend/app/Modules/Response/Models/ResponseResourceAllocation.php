<?php

namespace App\Modules\Response\Models;

use App\Core\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResponseResourceAllocation extends Model
{
    use HasUuids;

    public const STATUS_REQUESTED = 'REQUESTED';
    public const STATUS_ALLOCATED = 'ALLOCATED';
    public const STATUS_DEPLOYED = 'DEPLOYED';
    public const STATUS_RETURNED = 'RETURNED';

    public const STATUSES = [
        self::STATUS_REQUESTED,
        self::STATUS_ALLOCATED,
        self::STATUS_DEPLOYED,
        self::STATUS_RETURNED,
    ];

    protected $table = 'response.response_resource_allocations';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'response.response_resource_allocations'
            : 'response_resource_allocations';
    }

    protected $fillable = [
        'response_operation_id',
        'resource_asset_id',
        'requirement_title',
        'category',
        'quantity_needed',
        'quantity_assigned',
        'unit',
        'priority',
        'status',
        'notes',
        'requested_by',
    ];

    protected $casts = [
        'quantity_needed' => 'decimal:2',
        'quantity_assigned' => 'decimal:2',
    ];

    public function operation(): BelongsTo
    {
        return $this->belongsTo(ResponseOperation::class, 'response_operation_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(ResourceAsset::class, 'resource_asset_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
