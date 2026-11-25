<?php

namespace App\Modules\Disaster\Models;

use App\Core\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisasterImpact extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'disaster.disaster_impacts';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'disaster.disaster_impacts'
            : 'disaster_impacts';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'disaster_event_id',
        'affected_population',
        'affected_households',
        'deaths_count',
        'injured_count',
        'missing_count',
        'damaged_houses_fully',
        'damaged_houses_partially',
        'damaged_infrastructure_count',
        'agricultural_loss_acres',
        'agricultural_loss_bdt',
        'livestock_loss_count',
        'livestock_loss_bdt',
        'total_economic_loss_bdt',
        'remarks',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'affected_population' => 'integer',
            'affected_households' => 'integer',
            'deaths_count' => 'integer',
            'injured_count' => 'integer',
            'missing_count' => 'integer',
            'damaged_houses_fully' => 'integer',
            'damaged_houses_partially' => 'integer',
            'damaged_infrastructure_count' => 'integer',
            'agricultural_loss_acres' => 'decimal:2',
            'agricultural_loss_bdt' => 'decimal:2',
            'livestock_loss_count' => 'integer',
            'livestock_loss_bdt' => 'decimal:2',
            'total_economic_loss_bdt' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function disasterEvent(): BelongsTo
    {
        return $this->belongsTo(DisasterEvent::class, 'disaster_event_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
