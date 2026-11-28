<?php

namespace App\Modules\Notification\Models;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Core\Traits\HasJurisdictionScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Alert extends Model
{
    use HasUuids, SoftDeletes, HasJurisdictionScope;

    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_ACKNOWLEDGED = 'ACKNOWLEDGED';
    public const STATUS_ESCALATED = 'ESCALATED';
    public const STATUS_RESOLVED = 'RESOLVED';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_ACKNOWLEDGED,
        self::STATUS_ESCALATED,
        self::STATUS_RESOLVED,
    ];

    protected $table = 'notification.alerts';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'notification.alerts'
            : 'alerts';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'alert_no',
        'alert_rule_id',
        'category',
        'severity',
        'status',
        'title_bn',
        'title_en',
        'message_bn',
        'message_en',
        'source_module',
        'source_entity_type',
        'source_entity_id',
        'metric_payload',
        'administrative_unit_id',
        'idempotency_key',
        'triggered_at',
        'acknowledged_by',
        'acknowledged_at',
        'acknowledgement_notes',
        'escalated_at',
        'escalated_to_role',
        'resolved_by',
        'resolved_at',
        'resolution_notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'metric_payload' => 'array',
            'triggered_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'escalated_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class, 'alert_rule_id');
    }

    public function administrativeUnit(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class, 'administrative_unit_id');
    }

    public function acknowledgedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
