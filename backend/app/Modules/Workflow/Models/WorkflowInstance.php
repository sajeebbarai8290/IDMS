<?php

namespace App\Modules\Workflow\Models;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Core\Traits\HasJurisdictionScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkflowInstance extends Model
{
    use HasUuids, SoftDeletes, HasJurisdictionScope;

    public const STATE_SUBMITTED = 'SUBMITTED';
    public const STATE_INITIAL_REVIEW = 'INITIAL_REVIEW';
    public const STATE_SUPERVISOR_APPROVAL = 'SUPERVISOR_APPROVAL';
    public const STATE_DISTRICT_APPROVAL = 'DISTRICT_APPROVAL';
    public const STATE_DIRECTORATE_APPROVAL = 'DIRECTORATE_APPROVAL';
    public const STATE_MINISTRY_APPROVAL = 'MINISTRY_APPROVAL';
    public const STATE_FINAL_APPROVED = 'FINAL_APPROVED';
    public const STATE_REJECTED = 'REJECTED';
    public const STATE_RETURNED_FOR_CORRECTION = 'RETURNED_FOR_CORRECTION';
    public const STATE_INFO_REQUESTED = 'INFO_REQUESTED';
    public const STATE_CANCELLED = 'CANCELLED';

    public const STATES = [
        self::STATE_SUBMITTED,
        self::STATE_INITIAL_REVIEW,
        self::STATE_SUPERVISOR_APPROVAL,
        self::STATE_DISTRICT_APPROVAL,
        self::STATE_DIRECTORATE_APPROVAL,
        self::STATE_MINISTRY_APPROVAL,
        self::STATE_FINAL_APPROVED,
        self::STATE_REJECTED,
        self::STATE_RETURNED_FOR_CORRECTION,
        self::STATE_INFO_REQUESTED,
        self::STATE_CANCELLED,
    ];

    public const TERMINAL_STATES = [
        self::STATE_FINAL_APPROVED,
        self::STATE_REJECTED,
        self::STATE_CANCELLED,
    ];

    public const PRIORITIES = ['LOW', 'NORMAL', 'HIGH', 'URGENT'];

    protected $table = 'workflow.workflow_instances';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'workflow.workflow_instances'
            : 'workflow_instances';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'instance_no',
        'workflow_definition_id',
        'module_code',
        'entity_type',
        'entity_id',
        'entity_reference',
        'title_en',
        'title_bn',
        'administrative_unit_id',
        'current_stage_id',
        'current_state',
        'priority',
        'submitted_by',
        'submitted_at',
        'assigned_to_user_id',
        'assigned_role_code',
        'due_at',
        'reminder_sent_at',
        'escalated_at',
        'escalation_count',
        'completed_at',
        'last_action_by',
        'last_action_at',
        'submission_notes',
        'final_remarks',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'due_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'escalated_at' => 'datetime',
            'escalation_count' => 'integer',
            'completed_at' => 'datetime',
            'last_action_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function isTerminal(): bool
    {
        return in_array($this->current_state, self::TERMINAL_STATES, true);
    }

    public function isOverdue(): bool
    {
        return !$this->isTerminal()
            && $this->due_at !== null
            && $this->due_at->isPast();
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_definition_id');
    }

    public function currentStage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'current_stage_id');
    }

    public function administrativeUnit(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class, 'administrative_unit_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function lastActor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_action_by');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(WorkflowStageTask::class, 'workflow_instance_id')
            ->orderBy('step_sequence')
            ->orderBy('created_at');
    }

    public function activeTasks(): HasMany
    {
        return $this->hasMany(WorkflowStageTask::class, 'workflow_instance_id')
            ->where('status', WorkflowStageTask::STATUS_PENDING);
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(WorkflowTransition::class, 'workflow_instance_id')
            ->orderBy('transitioned_at');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(WorkflowAttachment::class, 'workflow_instance_id')
            ->orderByDesc('created_at');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(WorkflowNotification::class, 'workflow_instance_id')
            ->orderByDesc('created_at');
    }
}
