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

class AlertRule extends Model
{
    use HasUuids, SoftDeletes, HasJurisdictionScope;

    public const EVENT_TYPES = [
        'CYCLONE_SIGNAL_THRESHOLD',
        'FLOOD_DANGER_LEVEL',
        'LOW_STOCK_THRESHOLD',
        'STOCK_EXPIRY_WARNING',
        'WORKFLOW_SLA_OVERDUE',
        'SHELTER_CAPACITY_THRESHOLD',
        'PROJECT_MILESTONE_DELAY',
        'SYSTEM_HEALTH_DEGRADATION',
        'CUSTOM_THRESHOLD',
    ];

    protected $table = 'notification.alert_rules';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'notification.alert_rules'
            : 'alert_rules';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'rule_code',
        'name_bn',
        'name_en',
        'description_bn',
        'description_en',
        'category',
        'event_type',
        'severity',
        'condition_config',
        'channels',
        'target_roles',
        'escalation_delay_minutes',
        'escalation_role_code',
        'cooldown_minutes',
        'administrative_unit_id',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'condition_config' => 'array',
            'channels' => 'array',
            'target_roles' => 'array',
            'escalation_delay_minutes' => 'integer',
            'cooldown_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function administrativeUnit(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class, 'administrative_unit_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class, 'alert_rule_id');
    }
}
