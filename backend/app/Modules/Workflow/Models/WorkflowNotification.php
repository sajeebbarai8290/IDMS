<?php

namespace App\Modules\Workflow\Models;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Core\Traits\HasJurisdictionScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowNotification extends Model
{
    use HasUuids, HasJurisdictionScope;

    public const TYPE_ASSIGNED = 'ASSIGNED';
    public const TYPE_STATUS_CHANGED = 'STATUS_CHANGED';
    public const TYPE_REMINDER = 'REMINDER';
    public const TYPE_ESCALATION = 'ESCALATION';
    public const TYPE_INFO_REQUESTED = 'INFO_REQUESTED';
    public const TYPE_RETURNED_FOR_CORRECTION = 'RETURNED_FOR_CORRECTION';
    public const TYPE_COMPLETED = 'COMPLETED';

    public const DELIVERY_QUEUED = 'QUEUED';
    public const DELIVERY_SENT = 'SENT';
    public const DELIVERY_READ = 'READ';
    public const DELIVERY_FAILED = 'FAILED';

    protected $table = 'workflow.workflow_notifications';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'workflow.workflow_notifications'
            : 'workflow_notifications';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'workflow_instance_id',
        'workflow_stage_task_id',
        'recipient_user_id',
        'recipient_role_code',
        'administrative_unit_id',
        'notification_type',
        'channel',
        'title_en',
        'title_bn',
        'message_en',
        'message_bn',
        'delivery_status',
        'provider_reference',
        'read_at',
        'dispatched_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'dispatched_at' => 'datetime',
        ];
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(WorkflowStageTask::class, 'workflow_stage_task_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function administrativeUnit(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class, 'administrative_unit_id');
    }
}
