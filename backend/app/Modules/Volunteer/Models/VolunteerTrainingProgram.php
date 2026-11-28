<?php

namespace App\Modules\Volunteer\Models;

use App\Core\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VolunteerTrainingProgram extends Model
{
    use HasUuids;

    protected $table = 'volunteer.volunteer_training_programs';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'volunteer.volunteer_training_programs'
            : 'volunteer_training_programs';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'program_code',
        'title_en',
        'title_bn',
        'category_id',
        'skill_id',
        'provider_organization_id',
        'provider_name',
        'duration_hours',
        'validity_months',
        'description',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'duration_hours' => 'integer',
            'validity_months' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(VolunteerCategory::class, 'category_id');
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(SkillCatalog::class, 'skill_id');
    }

    public function providerOrganization(): BelongsTo
    {
        return $this->belongsTo(VolunteerOrganization::class, 'provider_organization_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(VolunteerTrainingSession::class, 'program_id')->orderByDesc('start_date');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
