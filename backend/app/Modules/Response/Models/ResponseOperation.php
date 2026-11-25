<?php

namespace App\Modules\Response\Models;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Core\Traits\HasJurisdictionScope;
use App\Modules\Disaster\Models\DisasterEvent;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResponseOperation extends Model
{
    use HasUuids, SoftDeletes, HasJurisdictionScope;

    public const STATUS_PLANNED = 'PLANNED';
    public const STATUS_ACTIVATED = 'ACTIVATED';
    public const STATUS_ONGOING = 'ONGOING';
    public const STATUS_SUSPENDED = 'SUSPENDED';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_CLOSED = 'CLOSED';

    public const STATUSES = [
        self::STATUS_PLANNED,
        self::STATUS_ACTIVATED,
        self::STATUS_ONGOING,
        self::STATUS_SUSPENDED,
        self::STATUS_COMPLETED,
        self::STATUS_CLOSED,
    ];

    public const HIGH_TIER_LIFECYCLE_TARGETS = [
        self::STATUS_ACTIVATED,
        self::STATUS_SUSPENDED,
        self::STATUS_COMPLETED,
        self::STATUS_CLOSED,
    ];

    public const PRIORITIES = ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'];
    public const SEVERITIES = ['ADVISORY', 'WARNING', 'DANGER', 'GREAT_DANGER', 'CATASTROPHIC'];

    protected $table = 'response.response_operations';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'response.response_operations'
            : 'response_operations';
    }

    protected $fillable = [
        'disaster_event_id',
        'administrative_unit_id',
        'response_center_id',
        'operation_no',
        'title_en',
        'title_bn',
        'objectives',
        'description',
        'priority',
        'severity_level',
        'status',
        'start_date',
        'expected_completion_date',
        'actual_completion_date',
        'lead_organization',
        'commander_name',
        'commander_user_id',
        'status_notes',
        'created_by',
        'updated_by',
        'activated_by',
        'activated_at',
        'closed_by',
        'closed_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'expected_completion_date' => 'date',
        'actual_completion_date' => 'date',
        'activated_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function disasterEvent(): BelongsTo
    {
        return $this->belongsTo(DisasterEvent::class, 'disaster_event_id');
    }

    public function administrativeUnit(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class, 'administrative_unit_id');
    }

    public function responseCenter(): BelongsTo
    {
        return $this->belongsTo(ResponseCenter::class, 'response_center_id');
    }

    public function commander(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commander_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function agencies(): HasMany
    {
        return $this->hasMany(ResponseOperationAgency::class, 'response_operation_id');
    }

    public function teams(): HasMany
    {
        return $this->hasMany(ResponseTeam::class, 'response_operation_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ResponseTask::class, 'response_operation_id');
    }

    public function resourceAllocations(): HasMany
    {
        return $this->hasMany(ResponseResourceAllocation::class, 'response_operation_id');
    }

    public function situationReports(): HasMany
    {
        return $this->hasMany(ResponseSituationReport::class, 'response_operation_id')->orderByDesc('reported_at');
    }
}
