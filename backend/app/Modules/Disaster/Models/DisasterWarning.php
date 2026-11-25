<?php

namespace App\Modules\Disaster\Models;

use App\Core\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisasterWarning extends Model
{
    use HasFactory, HasUuids;

    public const LEVELS = [
        'ADVISORY',
        'WATCH',
        'WARNING',
        'DANGER',
        'GREAT_DANGER',
        'EVACUATION_ORDER',
    ];

    protected $table = 'disaster.disaster_warnings';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'disaster.disaster_warnings'
            : 'disaster_warnings';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'disaster_event_id',
        'warning_code',
        'warning_level',
        'title_en',
        'title_bn',
        'message_en',
        'message_bn',
        'issuing_authority',
        'issued_at',
        'expires_at',
        'affected_unit_ids',
        'status',
        'notification_dispatch_status',
        'notification_reference',
        'issued_by',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'affected_unit_ids' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function disasterEvent(): BelongsTo
    {
        return $this->belongsTo(DisasterEvent::class, 'disaster_event_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
