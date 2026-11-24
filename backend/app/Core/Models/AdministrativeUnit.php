<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class AdministrativeUnit
 *
 * Represents the 7-tier sovereign administrative hierarchy of Bangladesh.
 *
 * @package App\Core\Models
 */
class AdministrativeUnit extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'core.administrative_units';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql' ? 'core.administrative_units' : 'administrative_units';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'parent_id',
        'code',
        'name_en',
        'name_bn',
        'tier_level',
        'ancestry_path',
        'latitude',
        'longitude',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
