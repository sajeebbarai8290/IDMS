<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class AuditLog
 *
 * Immutable ledger of state mutations, administrative decisions, and PII accesses.
 *
 * @package App\Core\Models
 */
class AuditLog extends Model
{
    use HasUuids;

    protected $table = 'audit.audit_logs';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql' ? 'audit.audit_logs' : 'audit_logs';
    }

    public $timestamps = false;
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'action_name',
        'auditable_type',
        'auditable_id',
        'administrative_unit_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function administrativeUnit(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class, 'administrative_unit_id');
    }

    /**
     * Helper to quickly record an audit log entry.
     */
    public static function log(
        string $actionName,
        string $auditableType,
        ?string $auditableId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?User $user = null,
        ?string $administrativeUnitId = null
    ): self {
        $currentUser = $user ?? auth()->user();
        $resolvedUnitId = $administrativeUnitId ?? $currentUser?->administrative_unit_id;
        if ($resolvedUnitId && !AdministrativeUnit::whereKey($resolvedUnitId)->exists()) {
            $resolvedUnitId = null;
        }

        return self::create([
            'user_id' => $currentUser?->id,
            'action_name' => $actionName,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'administrative_unit_id' => $resolvedUnitId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}
