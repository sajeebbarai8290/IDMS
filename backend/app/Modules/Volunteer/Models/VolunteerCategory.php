<?php

namespace App\Modules\Volunteer\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VolunteerCategory extends Model
{
    use HasUuids;

    protected $table = 'volunteer.volunteer_categories';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'volunteer.volunteer_categories'
            : 'volunteer_categories';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'code',
        'name_en',
        'name_bn',
        'description',
        'color_hex',
        'sort_order',
        'is_system',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function skills(): HasMany
    {
        return $this->hasMany(SkillCatalog::class, 'category_id');
    }

    public function volunteers(): HasMany
    {
        return $this->hasMany(Volunteer::class, 'primary_category_id');
    }
}
