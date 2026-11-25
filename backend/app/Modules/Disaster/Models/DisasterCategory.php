<?php

namespace App\Modules\Disaster\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DisasterCategory extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'disaster.disaster_categories';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'disaster.disaster_categories'
            : 'disaster_categories';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'code',
        'name_en',
        'name_bn',
        'subtypes',
        'icon',
        'color_hex',
        'is_system',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'subtypes' => 'array',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(DisasterEvent::class, 'category_id');
    }
}
