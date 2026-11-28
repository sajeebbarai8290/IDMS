<?php

namespace App\Modules\Workflow\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowStage extends Model
{
    use HasUuids;

    public const LEVEL_SUBMITTED = 'SUBMITTED';
    public const LEVEL_INITIAL_REVIEW = 'INITIAL_REVIEW';
    public const LEVEL_SUPERVISOR_APPROVAL = 'SUPERVISOR_APPROVAL';
    public const LEVEL_DISTRICT_APPROVAL = 'DISTRICT_APPROVAL';
    public const LEVEL_DIRECTORATE_APPROVAL = 'DIRECTORATE_APPROVAL';
    public const LEVEL_MINISTRY_APPROVAL = 'MINISTRY_APPROVAL';
    public const LEVEL_FINAL_APPROVAL = 'FINAL_APPROVAL';

    public const APPROVAL_LEVELS = [
        self::LEVEL_SUBMITTED,
        self::LEVEL_INITIAL_REVIEW,
        self::LEVEL_SUPERVISOR_APPROVAL,
        self::LEVEL_DISTRICT_APPROVAL,
        self::LEVEL_DIRECTORATE_APPROVAL,
        self::LEVEL_MINISTRY_APPROVAL,
        self::LEVEL_FINAL_APPROVAL,
    ];

    public const MODE_SEQUENTIAL = 'SEQUENTIAL';
    public const MODE_PARALLEL_ALL = 'PARALLEL_ALL';
    public const MODE_PARALLEL_ANY = 'PARALLEL_ANY';

    public const EXECUTION_MODES = [
        self::MODE_SEQUENTIAL,
        self::MODE_PARALLEL_ALL,
        self::MODE_PARALLEL_ANY,
    ];

    protected $table = 'workflow.workflow_stages';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'workflow.workflow_stages'
            : 'workflow_stages';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'workflow_definition_id',
        'stage_order',
        'stage_code',
        'name_en',
        'name_bn',
        'approval_level',
        'execution_mode',
        'required_approvals',
        'min_tier_level',
        'sla_hours',
        'escalation_role_code',
        'escalation_stage_id',
        'allow_delegation',
        'allow_return_for_correction',
        'allow_info_request',
        'allow_forward',
        'is_terminal',
        'mapped_entity_status',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'stage_order' => 'integer',
            'required_approvals' => 'integer',
            'sla_hours' => 'integer',
            'allow_delegation' => 'boolean',
            'allow_return_for_correction' => 'boolean',
            'allow_info_request' => 'boolean',
            'allow_forward' => 'boolean',
            'is_terminal' => 'boolean',
        ];
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_definition_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(WorkflowStageAssignment::class, 'workflow_stage_id')
            ->orderBy('sort_order');
    }

    public function escalationStage(): BelongsTo
    {
        return $this->belongsTo(self::class, 'escalation_stage_id');
    }
}
