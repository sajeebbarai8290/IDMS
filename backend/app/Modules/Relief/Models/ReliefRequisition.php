<?php

namespace App\Modules\Relief\Models;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Core\Traits\HasJurisdictionScope;
use App\Modules\Disaster\Models\DisasterEvent;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReliefRequisition extends Model
{
    use HasUuids, SoftDeletes, HasJurisdictionScope;

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_SUBMITTED = 'SUBMITTED';
    public const STATUS_UNDER_REVIEW = 'UNDER_REVIEW';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_PARTIALLY_APPROVED = 'PARTIALLY_APPROVED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_COMPLETED = 'COMPLETED';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SUBMITTED,
        self::STATUS_UNDER_REVIEW,
        self::STATUS_APPROVED,
        self::STATUS_PARTIALLY_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_COMPLETED,
    ];

    public const PRIORITIES = ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'];

    protected $table = 'logistics.relief_requisitions';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'logistics.relief_requisitions'
            : 'relief_requisitions';
    }

    protected $fillable = [
        'disaster_event_id',
        'administrative_unit_id',
        'requisition_no',
        'requesting_organization',
        'estimated_beneficiaries',
        'priority',
        'request_date',
        'needed_by_date',
        'status',
        'justification',
        'review_notes',
        'supporting_doc_path',
        'supporting_doc_name',
        'supporting_doc_mime',
        'supporting_doc_checksum',
        'submitted_by',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'approved_by',
        'approved_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'estimated_beneficiaries' => 'integer',
        'request_date' => 'date',
        'needed_by_date' => 'date',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function disasterEvent(): BelongsTo
    {
        return $this->belongsTo(DisasterEvent::class, 'disaster_event_id');
    }

    public function administrativeUnit(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class, 'administrative_unit_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReliefRequisitionItem::class, 'relief_requisition_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ReliefAllocation::class, 'relief_requisition_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
