<?php

namespace App\Modules\Volunteer\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VolunteerSkillAssignment extends Model
{
    use HasUuids;

    public const PROFICIENCY_LEVELS = ['BASIC', 'INTERMEDIATE', 'ADVANCED', 'INSTRUCTOR'];
    public const VERIFICATION_STATUSES = ['PENDING', 'VERIFIED', 'REJECTED'];

    protected $table = 'volunteer.volunteer_skill_assignments';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'volunteer.volunteer_skill_assignments'
            : 'volunteer_skill_assignments';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'volunteer_id',
        'skill_id',
        'proficiency_level',
        'certification_level',
        'certified_by',
        'issued_at',
        'expires_at',
        'verification_status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'expires_at' => 'date',
        ];
    }

    public function volunteer(): BelongsTo
    {
        return $this->belongsTo(Volunteer::class, 'volunteer_id');
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(SkillCatalog::class, 'skill_id');
    }
}
