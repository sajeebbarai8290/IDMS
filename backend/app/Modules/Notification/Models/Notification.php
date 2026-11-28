<?php

namespace App\Modules\Notification\Models;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Core\Traits\HasJurisdictionScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model
{
    use HasUuids, SoftDeletes, HasJurisdictionScope;

    public const CHANNELS = ['IN_APP', 'EMAIL', 'SMS', 'PUSH'];

    public const SEVERITIES = ['INFO', 'WARNING', 'HIGH', 'CRITICAL'];

    public const PRIORITIES = ['LOW', 'NORMAL', 'HIGH', 'URGENT'];

    public const TARGET_TYPES = ['USER', 'ROLE', 'ORGANIZATION', 'GEOGRAPHY', 'ALL'];

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_SCHEDULED = 'SCHEDULED';
    public const STATUS_QUEUED = 'QUEUED';
    public const STATUS_PROCESSING = 'PROCESSING';
    public const STATUS_DISPATCHED = 'DISPATCHED';
    public const STATUS_PARTIALLY_FAILED = 'PARTIALLY_FAILED';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_CANCELLED = 'CANCELLED';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SCHEDULED,
        self::STATUS_QUEUED,
        self::STATUS_PROCESSING,
        self::STATUS_DISPATCHED,
        self::STATUS_PARTIALLY_FAILED,
        self::STATUS_FAILED,
        self::STATUS_CANCELLED,
    ];

    protected $table = 'notification.notifications';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'notification.notifications'
            : 'notifications';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'notification_no',
        'template_id',
        'category',
        'severity',
        'priority',
        'channels',
        'title_bn',
        'title_en',
        'message_bn',
        'message_en',
        'target_type',
        'target_criteria',
        'administrative_unit_id',
        'related_entity_type',
        'related_entity_id',
        'status',
        'idempotency_key',
        'scheduled_at',
        'dispatched_at',
        'total_recipients',
        'delivered_count',
        'failed_count',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'target_criteria' => 'array',
            'metadata' => 'array',
            'scheduled_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'total_recipients' => 'integer',
            'delivered_count' => 'integer',
            'failed_count' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'template_id');
    }

    public function administrativeUnit(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class, 'administrative_unit_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function dispatches(): HasMany
    {
        return $this->hasMany(NotificationDispatch::class, 'notification_id');
    }
}
