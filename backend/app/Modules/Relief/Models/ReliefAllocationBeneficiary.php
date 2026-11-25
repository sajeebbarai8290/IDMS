<?php

namespace App\Modules\Relief\Models;

use App\Core\Models\User;
use App\Modules\Beneficiary\Models\Household;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReliefAllocationBeneficiary extends Model
{
    use HasUuids;

    public const STATUS_SELECTED = 'SELECTED';
    public const STATUS_DISTRIBUTED = 'DISTRIBUTED';
    public const STATUS_ABSENT = 'ABSENT';
    public const STATUS_REMOVED = 'REMOVED';

    protected $table = 'logistics.relief_allocation_beneficiaries';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'logistics.relief_allocation_beneficiaries'
            : 'relief_allocation_beneficiaries';
    }

    protected $fillable = [
        'relief_allocation_id',
        'household_id',
        'selection_status',
        'assigned_by',
        'notes',
    ];

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(ReliefAllocation::class, 'relief_allocation_id');
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class, 'household_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
