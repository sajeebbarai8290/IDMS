<?php

namespace App\Modules\Disaster\Models;

use App\Core\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisasterTimeline extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'disaster.disaster_timelines';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'disaster.disaster_timelines'
            : 'disaster_timelines';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'disaster_event_id',
        'event_type',
        'from_phase',
        'to_phase',
        'title_en',
        'title_bn',
        'notes',
        'attachment_original_name',
        'attachment_path',
        'attachment_mime',
        'attachment_size_bytes',
        'attachment_sha256',
        'recorded_by',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'attachment_size_bytes' => 'integer',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function disasterEvent(): BelongsTo
    {
        return $this->belongsTo(DisasterEvent::class, 'disaster_event_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
