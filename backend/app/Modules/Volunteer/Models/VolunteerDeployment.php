<?php

namespace App\Modules\Volunteer\Models;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Core\Traits\HasJurisdictionScope;
use App\Modules\Disaster\Models\DisasterEvent;
use App\Modules\Response\Models\ResponseOperation;
use App\Modules\Response\Models\ResponseTeam;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class VolunteerDeployment extends Model
{
    use HasUuids, SoftDeletes, HasJurisdictionScope;

    public const STATUS_ASSIGNED = 'ASSIGNED';
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_CANCELLED = 'CANCELLED';

    public const ACTIVE_STATUSES = [
        self::STATUS_ASSIGNED,
        self::STATUS_ACTIVE,
    ];

    public const STATUSES = [
        self::STATUS_ASSIGNED,
        self::STATUS_ACTIVE,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    protected $table = 'volunteer.volunteer_deployments';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'volunteer.volunteer_deployments'
            : 'volunteer_deployments';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'deployment_no',
        'volunteer_id',
        'disaster_event_id',
        'response_operation_id',
        'response_team_id',
        'administrative_unit_id',
        'deployment_location',
        'latitude',
        'longitude',
        'assigned_role',
        'assigned_duties',
        'start_date',
        'end_date',
        'actual_end_date',
        'supervisor_user_id',
        'supervisor_name',
        'supervisor_contact',
        'deployment_status',
        'assigned_by',
        'released_by',
        'notes',
    ];

    protected $hidden = ['deleted_at'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'actual_end_date' => 'date',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function volunteer(): BelongsTo
    {
        return $this->belongsTo(Volunteer::class, 'volunteer_id');
    }

    public function disasterEvent(): BelongsTo
    {
        return $this->belongsTo(DisasterEvent::class, 'disaster_event_id');
    }

    public function responseOperation(): BelongsTo
    {
        return $this->belongsTo(ResponseOperation::class, 'response_operation_id');
    }

    public function responseTeam(): BelongsTo
    {
        return $this->belongsTo(ResponseTeam::class, 'response_team_id');
    }

    public function administrativeUnit(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class, 'administrative_unit_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_user_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(VolunteerAttendance::class, 'deployment_id')->orderByDesc('check_in_at');
    }
}
