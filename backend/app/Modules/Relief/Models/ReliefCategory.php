<?php

namespace App\Modules\Relief\Models;

use App\Modules\Warehouse\Models\Commodity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReliefCategory extends Model
{
    use HasUuids;

    protected $table = 'logistics.relief_categories';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'logistics.relief_categories'
            : 'relief_categories';
    }

    protected $fillable = [
        'code',
        'name_en',
        'name_bn',
        'description',
        'icon',
        'color_hex',
        'is_system',
        'is_active',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function commodities(): HasMany
    {
        return $this->hasMany(Commodity::class, 'relief_category_id');
    }
}
