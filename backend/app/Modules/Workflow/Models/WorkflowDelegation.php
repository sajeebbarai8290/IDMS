<?php

namespace App\Modules\Workflow\Models;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Core\Traits\HasJurisdictionScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowDelegation extends Model
{
    use HasUuids, HasJurisdictionScope;

    protected $table = 'workflow.workflow_delegations';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'workflow.workflow_delegations'
            : 'workflow_delegations';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'workflow_definition_id',
        'delegator_user_id',
        'delegate_user_id',
        'administrative_unit_id',
        'delegated_role_code',
        'valid_from',
        'valid_until',
        'reason',
        'is_active',
        'approved_by',
        'revoked_by',
        'revoked_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'is_active' => 'boolean',
            'revoked_at' => 'datetime',
        ];
    }

    public function scopeCurrentlyValid(Builder $query): Builder
    {
        $now = now();
        return $query->where('is_active', true)
            ->whereNull('revoked_at')
            ->where('valid_from', '<=', $now)
            ->where('valid_until', '>=', $now);
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_definition_id');
    }

    public function delegator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegator_user_id');
    }

    public function delegate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegate_user_id');
    }

    public function administrativeUnit(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class, 'administrative_unit_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }
}
