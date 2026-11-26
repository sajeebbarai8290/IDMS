<?php

namespace App\Modules\Resource\Models;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Core\Traits\HasJurisdictionScope;
use App\Modules\Disaster\Models\DisasterEvent;
use App\Modules\Response\Models\ResponseOperation;
use App\Modules\Response\Models\ResponseTeam;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HumanResourceDeployment extends Model
{
    use HasUuids, HasJurisdictionScope;

    public const STATUS_ASSIGNED = 'ASSIGNED';
    public const STATUS_IN_USE = 'IN_USE';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_CANCELLED = 'CANCELLED';

    public const ACTIVE_STATUSES = [
        self::STATUS_ASSIGNED,
        self::STATUS_IN_USE,
    ];

    public const STATUSES = [
        self::STATUS_ASSIGNED,
        self::STATUS_IN_USE,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    protected $table = 'resource.human_resource_deployments';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'resource.human_resource_deployments'
            : 'human_resource_deployments';
    }

    protected $fillable = [
        'resource_asset_id',
        'user_id',
        'response_team_id',
        'response_operation_id',
        'disaster_event_id',
        'administrative_unit_id',
        'deployment_no',
        'personnel_name',
        'personnel_designation',
        'personnel_contact',
        'assigned_duty',
        'deployment_location',
        'deployment_start_date',
        'deployment_end_date',
        'actual_return_date',
        'availability_status',
        'notes',
        'assigned_by',
        'released_by',
    ];

    protected $casts = [
        'deployment_start_date' => 'date',
        'deployment_end_date' => 'date',
        'actual_return_date' => 'date',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(ResourceAsset::class, 'resource_asset_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(ResponseTeam::class, 'response_team_id');
    }

    public function responseOperation(): BelongsTo
    {
        return $this->belongsTo(ResponseOperation::class, 'response_operation_id');
    }

    public function disasterEvent(): BelongsTo
    {
        return $this->belongsTo(DisasterEvent::class, 'disaster_event_id');
    }

    public function administrativeUnit(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class, 'administrative_unit_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }
}
