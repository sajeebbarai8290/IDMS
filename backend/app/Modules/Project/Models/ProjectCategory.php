<?php

namespace App\Modules\Project\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectCategory extends Model
{
    use HasUuids;

    protected $table = 'project.project_categories';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'project.project_categories'
            : 'project_categories';
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
        'is_system',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'category_id');
    }
}
