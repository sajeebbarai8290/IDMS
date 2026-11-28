<?php

namespace App\Modules\Volunteer\Models;

use App\Core\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VolunteerTrainingEnrollment extends Model
{
    use HasUuids;

    public const ENROLLMENT_STATUSES = ['ENROLLED', 'ATTENDED', 'ABSENT', 'DROPPED'];

    protected $table = 'volunteer.volunteer_training_enrollments';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'volunteer.volunteer_training_enrollments'
            : 'volunteer_training_enrollments';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'session_id',
        'volunteer_id',
        'enrollment_status',
        'attendance_hours',
        'assessment_score',
        'passed',
        'certificate_no',
        'issued_at',
        'expires_at',
        'certificate_path',
        'certificate_original_name',
        'certificate_mime',
        'certificate_sha256',
        'remarks',
        'verified_by',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'attendance_hours' => 'decimal:2',
            'assessment_score' => 'decimal:2',
            'passed' => 'boolean',
            'issued_at' => 'date',
            'expires_at' => 'date',
            'verified_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(VolunteerTrainingSession::class, 'session_id');
    }

    public function volunteer(): BelongsTo
    {
        return $this->belongsTo(Volunteer::class, 'volunteer_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
