<?php

namespace App\Modules\Notification\Models;

use App\Core\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    use HasUuids;

    /**
     * Critical categories mandated by MoDMR emergency policy where IN_APP and SMS cannot be muted.
     */
    public const CRITICAL_LOCKED_CATEGORIES = [
        NotificationTemplate::CATEGORY_EMERGENCY_ALERT,
        NotificationTemplate::CATEGORY_DISASTER_WARNING,
    ];

    protected $table = 'notification.notification_preferences';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'notification.notification_preferences'
            : 'notification_preferences';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'category',
        'in_app_enabled',
        'email_enabled',
        'sms_enabled',
        'push_enabled',
        'quiet_hours_start',
        'quiet_hours_end',
        'policy_locked',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'in_app_enabled' => 'boolean',
            'email_enabled' => 'boolean',
            'sms_enabled' => 'boolean',
            'push_enabled' => 'boolean',
            'policy_locked' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function allowsChannel(string $channel, string $severity = 'INFO'): bool
    {
        // Statutory emergency override: CRITICAL severity or policy-locked emergency categories always deliver
        if ($severity === 'CRITICAL' || in_array($this->category, self::CRITICAL_LOCKED_CATEGORIES, true)) {
            if (in_array($channel, ['IN_APP', 'SMS'], true)) {
                return true;
            }
        }

        return match ($channel) {
            'IN_APP' => (bool) $this->in_app_enabled,
            'EMAIL' => (bool) $this->email_enabled,
            'SMS' => (bool) $this->sms_enabled,
            'PUSH' => (bool) $this->push_enabled,
            default => false,
        };
    }
}
