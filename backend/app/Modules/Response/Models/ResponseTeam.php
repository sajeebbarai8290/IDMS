<?php

namespace App\Modules\Response\Models;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Core\Traits\HasJurisdictionScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResponseTeam extends Model
{
    use HasUuids, HasJurisdictionScope;

    public const TEAM_TYPES = [
        'FIRE_SERVICE',
        'ARMED_FORCES',
        'COAST_GUARD',
        'CPP_VOLUNTEERS',
        'MEDICAL_TEAM',
        'BD_POLICE',
        'SAR_UNIT',
        'ENGINEERING_WATSAN',
    ];

    public const STATUSES = ['STANDBY', 'DEPLOYED', 'ACTIVE', 'DEMOBILIZED'];

    protected $table = 'response.response_teams';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'response.response_teams'
            : 'response_teams';
    }

    protected $fillable = [
        'response_operation_id',
        'administrative_unit_id',
        'team_code',
        'team_name',
        'agency_name',
        'team_type',
        'leader_name',
        'leader_contact',
        'leader_user_id',
        'member_count',
        'status',
        'latitude',
        'longitude',
        'last_checkin_at',
    ];

    protected $casts = [
        'member_count' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'last_checkin_at' => 'datetime',
    ];

    public function operation(): BelongsTo
    {
        return $this->belongsTo(ResponseOperation::class, 'response_operation_id');
    }

    public function administrativeUnit(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class, 'administrative_unit_id');
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_user_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ResponseTask::class, 'response_team_id');
    }
}
