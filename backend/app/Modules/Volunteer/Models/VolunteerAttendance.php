<?php

namespace App\Modules\Volunteer\Models;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Core\Traits\HasJurisdictionScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VolunteerAttendance extends Model
{
    use HasUuids, HasJurisdictionScope;

    public const CONFIRMATION_PENDING = 'PENDING';
    public const CONFIRMATION_CONFIRMED = 'CONFIRMED';
    public const CONFIRMATION_DISPUTED = 'DISPUTED';

    public const CONFIRMATION_STATUSES = [
        self::CONFIRMATION_PENDING,
        self::CONFIRMATION_CONFIRMED,
        self::CONFIRMATION_DISPUTED,
    ];

    protected $table = 'volunteer.volunteer_attendances';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'volunteer.volunteer_attendances'
            : 'volunteer_attendances';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'deployment_id',
        'volunteer_id',
        'administrative_unit_id',
        'attendance_date',
        'check_in_at',
        'check_out_at',
        'duration_minutes',
        'gps_consent_given',
        'check_in_lat',
        'check_in_lng',
        'check_out_lat',
        'check_out_lng',
        'activity_summary',
        'tasks_completed',
        'beneficiaries_assisted',
        'evidence_path',
        'evidence_original_name',
        'evidence_mime',
        'evidence_sha256',
        'confirmation_status',
        'supervisor_confirmed_by',
        'supervisor_confirmed_at',
        'supervisor_remarks',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'duration_minutes' => 'integer',
            'gps_consent_given' => 'boolean',
            'check_in_lat' => 'decimal:7',
            'check_in_lng' => 'decimal:7',
            'check_out_lat' => 'decimal:7',
            'check_out_lng' => 'decimal:7',
            'beneficiaries_assisted' => 'integer',
            'supervisor_confirmed_at' => 'datetime',
        ];
    }

    public function deployment(): BelongsTo
    {
        return $this->belongsTo(VolunteerDeployment::class, 'deployment_id');
    }

    public function volunteer(): BelongsTo
    {
        return $this->belongsTo(Volunteer::class, 'volunteer_id');
    }

    public function administrativeUnit(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class, 'administrative_unit_id');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_confirmed_by');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
