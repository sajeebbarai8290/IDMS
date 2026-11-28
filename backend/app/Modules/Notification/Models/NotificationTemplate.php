<?php

namespace App\Modules\Notification\Models;

use App\Core\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotificationTemplate extends Model
{
    use HasUuids, SoftDeletes;

    public const CATEGORY_DISASTER_WARNING = 'DISASTER_WARNING';
    public const CATEGORY_EMERGENCY_ALERT = 'EMERGENCY_ALERT';
    public const CATEGORY_RELIEF_APPROVAL = 'RELIEF_APPROVAL';
    public const CATEGORY_BENEFICIARY_VERIFICATION = 'BENEFICIARY_VERIFICATION';
    public const CATEGORY_WORKFLOW_APPROVAL = 'WORKFLOW_APPROVAL';
    public const CATEGORY_INVENTORY_ALERT = 'INVENTORY_ALERT';
    public const CATEGORY_PROJECT_MONITORING = 'PROJECT_MONITORING';
    public const CATEGORY_SYSTEM_ANNOUNCEMENT = 'SYSTEM_ANNOUNCEMENT';

    public const CATEGORIES = [
        self::CATEGORY_DISASTER_WARNING,
        self::CATEGORY_EMERGENCY_ALERT,
        self::CATEGORY_RELIEF_APPROVAL,
        self::CATEGORY_BENEFICIARY_VERIFICATION,
        self::CATEGORY_WORKFLOW_APPROVAL,
        self::CATEGORY_INVENTORY_ALERT,
        self::CATEGORY_PROJECT_MONITORING,
        self::CATEGORY_SYSTEM_ANNOUNCEMENT,
    ];

    public const CHANNELS = [
        'IN_APP',
        'EMAIL',
        'SMS',
        'PUSH',
        'MULTI',
    ];

    public const SEVERITIES = [
        'INFO',
        'WARNING',
        'HIGH',
        'CRITICAL',
    ];

    protected $table = 'notification.notification_templates';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'notification.notification_templates'
            : 'notification_templates';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'template_code',
        'category',
        'channel',
        'default_severity',
        'title_bn',
        'title_en',
        'subject_bn',
        'subject_en',
        'body_bn',
        'body_en',
        'variables',
        'is_system',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Render bilingual title, subject, and body using {{variable}} placeholders.
     *
     * @param array<string, scalar|null> $data
     * @return array{title_bn: string, title_en: string, subject_bn: ?string, subject_en: ?string, body_bn: string, body_en: string}
     */
    public function render(array $data = []): array
    {
        $replace = function (?string $text) use ($data): ?string {
            if ($text === null) {
                return null;
            }
            foreach ($data as $key => $value) {
                $safeVal = is_scalar($value) ? (string) $value : '';
                $text = str_replace('{{' . $key . '}}', $safeVal, $text);
            }
            return $text;
        };

        return [
            'title_bn' => $replace($this->title_bn) ?? '',
            'title_en' => $replace($this->title_en) ?? '',
            'subject_bn' => $replace($this->subject_bn),
            'subject_en' => $replace($this->subject_en),
            'body_bn' => $replace($this->body_bn) ?? '',
            'body_en' => $replace($this->body_en) ?? '',
        ];
    }
}
