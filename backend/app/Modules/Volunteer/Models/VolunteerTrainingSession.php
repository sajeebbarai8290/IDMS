<?php

namespace App\Modules\Volunteer\Models;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Core\Traits\HasJurisdictionScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VolunteerTrainingSession extends Model
{
    use HasUuids, HasJurisdictionScope;

    public const STATUSES = ['SCHEDULED', 'ONGOING', 'COMPLETED', 'CANCELLED'];

    protected $table = 'volunteer.volunteer_training_sessions';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'volunteer.volunteer_training_sessions'
            : 'volunteer_training_sessions';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'session_code',
        'program_id',
        'administrative_unit_id',
        'venue',
        'start_date',
        'end_date',
        'trainer_name',
        'trainer_contact',
        'max_participants',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'max_participants' => 'integer',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(VolunteerTrainingProgram::class, 'program_id');
    }

    public function administrativeUnit(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class, 'administrative_unit_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(VolunteerTrainingEnrollment::class, 'session_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
