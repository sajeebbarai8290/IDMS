<?php

namespace App\Modules\Workflow\Models;

use App\Core\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkflowDefinition extends Model
{
    use HasUuids, SoftDeletes;

    public const MODULES = [
        'BENEFICIARY',
        'RELIEF',
        'DAMAGE',
        'PROJECT',
        'RESOURCE',
        'WAREHOUSE',
        'VOLUNTEER',
        'DISASTER',
        'RESPONSE',
        'GENERAL',
    ];

    public const ENTITY_TYPES = [
        'HOUSEHOLD',
        'RELIEF_REQUISITION',
        'RELIEF_ALLOCATION',
        'DAMAGE_ASSESSMENT',
        'PROJECT',
        'PROJECT_DISBURSEMENT',
        'RESOURCE_ALLOCATION',
        'WAREHOUSE_TRANSFER',
        'VOLUNTEER',
        'GENERAL_REQUEST',
    ];

    protected $table = 'workflow.workflow_definitions';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'workflow.workflow_definitions'
            : 'workflow_definitions';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'code',
        'name_en',
        'name_bn',
        'module_code',
        'entity_type',
        'description',
        'is_active',
        'is_default',
        'is_system',
        'allow_self_approval',
        'allow_delegation',
        'sync_entity_status',
        'sla_hours',
        'escalation_enabled',
        'version',
        'config_metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'is_system' => 'boolean',
            'allow_self_approval' => 'boolean',
            'allow_delegation' => 'boolean',
            'sync_entity_status' => 'boolean',
            'sla_hours' => 'integer',
            'escalation_enabled' => 'boolean',
            'version' => 'integer',
            'config_metadata' => 'array',
        ];
    }

    public function stages(): HasMany
    {
        return $this->hasMany(WorkflowStage::class, 'workflow_definition_id')
            ->orderBy('stage_order');
    }

    public function instances(): HasMany
    {
        return $this->hasMany(WorkflowInstance::class, 'workflow_definition_id');
    }

    public function delegations(): HasMany
    {
        return $this->hasMany(WorkflowDelegation::class, 'workflow_definition_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
