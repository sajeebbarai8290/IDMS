<?php

namespace App\Modules\Workflow\Models;

use App\Core\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * Immutable historical workflow decision & state transition record.
 */
class WorkflowTransition extends Model
{
    use HasUuids;

    public const ACTION_SUBMIT = 'SUBMIT';
    public const ACTION_RESUBMIT = 'RESUBMIT';
    public const ACTION_APPROVE = 'APPROVE';
    public const ACTION_REJECT = 'REJECT';
    public const ACTION_RETURN_FOR_CORRECTION = 'RETURN_FOR_CORRECTION';
    public const ACTION_REQUEST_INFO = 'REQUEST_INFO';
    public const ACTION_PROVIDE_INFO = 'PROVIDE_INFO';
    public const ACTION_FORWARD = 'FORWARD';
    public const ACTION_ESCALATE = 'ESCALATE';
    public const ACTION_REASSIGN = 'REASSIGN';
    public const ACTION_CANCEL = 'CANCEL';

    public const ACTIONS = [
        self::ACTION_SUBMIT,
        self::ACTION_RESUBMIT,
        self::ACTION_APPROVE,
        self::ACTION_REJECT,
        self::ACTION_RETURN_FOR_CORRECTION,
        self::ACTION_REQUEST_INFO,
        self::ACTION_PROVIDE_INFO,
        self::ACTION_FORWARD,
        self::ACTION_ESCALATE,
        self::ACTION_REASSIGN,
        self::ACTION_CANCEL,
    ];

    protected $table = 'workflow.workflow_transitions';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'workflow.workflow_transitions'
            : 'workflow_transitions';
    }

    public $timestamps = false;
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'workflow_instance_id',
        'workflow_stage_task_id',
        'from_stage_id',
        'to_stage_id',
        'action',
        'from_state',
        'to_state',
        'action_by_user_id',
        'acted_on_behalf_of_user_id',
        'actor_name',
        'actor_role',
        'remarks',
        'metadata',
        'transitioned_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'transitioned_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Workflow transition history records are immutable and cannot be modified.');
        });

        static::deleting(function () {
            throw new LogicException('Workflow transition history records are immutable and cannot be deleted.');
        });
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(WorkflowStageTask::class, 'workflow_stage_task_id');
    }

    public function fromStage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'from_stage_id');
    }

    public function toStage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'to_stage_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'action_by_user_id');
    }

    public function actedOnBehalfOf(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_on_behalf_of_user_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(WorkflowAttachment::class, 'workflow_transition_id');
    }
}
