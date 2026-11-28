<?php

namespace App\Modules\Notification\Models;

use App\Core\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class NotificationDevice extends Model
{
    use HasUuids;

    public const PLATFORMS = ['ANDROID', 'IOS', 'WEB'];
    public const PUSH_PROVIDERS = ['FCM', 'APNS', 'WEB_PUSH'];

    protected $table = 'notification.notification_devices';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'notification.notification_devices'
            : 'notification_devices';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'device_uuid',
        'platform',
        'push_provider',
        'push_token_encrypted',
        'push_token_hash',
        'device_name',
        'app_version',
        'locale',
        'is_active',
        'last_seen_at',
    ];

    protected $hidden = [
        'push_token_encrypted',
        'push_token_hash',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function setPlainPushToken(string $token): void
    {
        $this->push_token_encrypted = Crypt::encryptString($token);
        $this->push_token_hash = hash('sha256', $token);
    }

    public function getPlainPushToken(): ?string
    {
        if (empty($this->push_token_encrypted)) {
            return null;
        }

        return Crypt::decryptString($this->push_token_encrypted);
    }
}
