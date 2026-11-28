<?php

namespace App\Modules\Notification\Models;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Core\Traits\HasJurisdictionScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationDispatch extends Model
{
    use HasUuids, HasJurisdictionScope;

    public const STATUS_QUEUED = 'QUEUED';
    public const STATUS_RETRYING = 'RETRYING';
    public const STATUS_SENT = 'SENT';
    public const STATUS_DELIVERED = 'DELIVERED';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_SKIPPED = 'SKIPPED';

    public const STATUSES = [
        self::STATUS_QUEUED,
        self::STATUS_RETRYING,
        self::STATUS_SENT,
        self::STATUS_DELIVERED,
        self::STATUS_FAILED,
        self::STATUS_SKIPPED,
    ];

    protected $table = 'notification.notification_dispatches';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'notification.notification_dispatches'
            : 'notification_dispatches';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'notification_id',
        'template_id',
        'recipient_user_id',
        'administrative_unit_id',
        'channel',
        'destination_address',
        'title_bn',
        'title_en',
        'message_body',
        'message_body_bn',
        'message_body_en',
        'delivery_status',
        'provider_name',
        'provider_reference',
        'attempt_count',
        'last_error',
        'dedup_hash',
        'read_at',
        'dispatched_at',
        'delivered_at',
    ];

    protected $hidden = [
        'destination_address',
        'dedup_hash',
    ];

    protected function casts(): array
    {
        return [
            'attempt_count' => 'integer',
            'read_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class, 'notification_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'template_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function administrativeUnit(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class, 'administrative_unit_id');
    }

    /**
     * Produce a masked representation of a contact address for secure delivery logs.
     */
    public static function maskDestination(?string $address, string $channel): string
    {
        if (!$address) {
            return '***';
        }

        if ($channel === 'EMAIL' && str_contains($address, '@')) {
            [$local, $domain] = explode('@', $address, 2);
            $prefix = mb_substr($local, 0, min(2, mb_strlen($local)));
            return $prefix . '***@' . $domain;
        }

        if ($channel === 'SMS') {
            $len = strlen($address);
            if ($len <= 4) {
                return str_repeat('*', $len);
            }
            return str_repeat('*', $len - 4) . substr($address, -4);
        }

        if ($channel === 'PUSH') {
            return 'device:' . substr($address, 0, 6) . '***';
        }

        return 'in-app';
    }
}
