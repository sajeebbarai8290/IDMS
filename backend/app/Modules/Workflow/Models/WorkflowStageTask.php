<?php

namespace App\Modules\Workflow\Models;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowStageTask extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_RETURNED = 'RETURNED';
    public const STATUS_INFO_REQUESTED = 'INFO_REQUESTED';
    public const STATUS_FORWARDED = 'FORWARDED';
    public const STATUS_ESCALATED = 'ESCALATED';
    public const STATUS_SKIPPED = 'SKIPPED';
    public const STATUS_CANCELLED = 'CANCELLED';

    protected $table = 'workflow.workflow_stage_tasks';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'workflow.workflow_stage_tasks'
            : 'workflow_stage_tasks';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'workflow_instance_id',
        'workflow_stage_id',
        'step_sequence',
        'assignee_type',
        'assignee_user_id',
        'assignee_role_code',
        'administrative_unit_id',
        'delegated_from_user_id',
        'authority_type',
        'status',
        'due_at',
        'acted_by_user_id',
        'acted_at',
        'comments',
    ];

    protected function casts(): array
    {
        return [
            'step_sequence' => 'integer',
            'due_at' => 'datetime',
            'acted_at' => 'datetime',
        ];
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'workflow_stage_id');
    }

    public function assigneeUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }

    public function delegatedFromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegated_from_user_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by_user_id');
    }

    public function administrativeUnit(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class, 'administrative_unit_id');
    }
}
