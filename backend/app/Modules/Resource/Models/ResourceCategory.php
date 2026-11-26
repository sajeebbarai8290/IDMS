<?php

namespace App\Modules\Resource\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResourceCategory extends Model
{
    use HasUuids;

    protected $table = 'resource.resource_categories';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'resource.resource_categories'
            : 'resource_categories';
    }

    protected $fillable = [
        'code',
        'name_en',
        'name_bn',
        'description',
        'icon',
        'color_hex',
        'is_human_resource',
        'is_system',
        'is_active',
    ];

    protected $casts = [
        'is_human_resource' => 'boolean',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function assets(): HasMany
    {
        return $this->hasMany(ResourceAsset::class, 'category_id');
    }
}
