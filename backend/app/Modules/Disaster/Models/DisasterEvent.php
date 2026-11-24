<?php

namespace App\Modules\Disaster\Models;

use App\Core\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class DisasterEvent
 *
 * Represents an officially declared disaster event.
 *
 * @package App\Modules\Disaster\Models
 */
class DisasterEvent extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'disaster.disaster_events';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql' ? 'disaster.disaster_events' : 'disaster_events';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'disaster_code',
        'name_en',
        'name_bn',
        'disaster_type',
        'severity_level',
        'lifecycle_phase',
        'declaration_date',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'declaration_date' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
