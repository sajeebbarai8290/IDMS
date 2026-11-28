<?php

namespace App\Modules\Volunteer\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SkillCatalog extends Model
{
    use HasUuids;

    protected $table = 'volunteer.skill_catalog';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'volunteer.skill_catalog'
            : 'skill_catalog';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'category_id',
        'skill_code',
        'name_en',
        'name_bn',
        'description',
        'requires_certification',
        'is_system',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requires_certification' => 'boolean',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(VolunteerCategory::class, 'category_id');
    }

    public function skillAssignments(): HasMany
    {
        return $this->hasMany(VolunteerSkillAssignment::class, 'skill_id');
    }
}
