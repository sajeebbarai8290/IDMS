<?php

namespace App\Modules\Response\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResponseActivityType extends Model
{
    use HasUuids;

    protected $table = 'response.response_activity_types';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'response.response_activity_types'
            : 'response_activity_types';
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

    public function tasks(): HasMany
    {
        return $this->hasMany(ResponseTask::class, 'activity_type_id');
    }
}
