<?php

namespace App\Modules\Workflow\Models;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowStageAssignment extends Model
{
    use HasUuids;

    public const ASSIGNEE_USER = 'USER';
    public const ASSIGNEE_ROLE = 'ROLE';
    public const ASSIGNEE_ADMINISTRATIVE_UNIT = 'ADMINISTRATIVE_UNIT';

    public const ASSIGNEE_TYPES = [
        self::ASSIGNEE_USER,
        self::ASSIGNEE_ROLE,
        self::ASSIGNEE_ADMINISTRATIVE_UNIT,
    ];

    public const AUTHORITY_REVIEWER = 'REVIEWER';
    public const AUTHORITY_APPROVER = 'APPROVER';
    public const AUTHORITY_ENDORSE = 'ENDORSE';
    public const AUTHORITY_OBSERVER = 'OBSERVER';

    public const AUTHORITY_TYPES = [
        self::AUTHORITY_REVIEWER,
        self::AUTHORITY_APPROVER,
        self::AUTHORITY_ENDORSE,
        self::AUTHORITY_OBSERVER,
    ];

    protected $table = 'workflow.workflow_stage_assignments';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'workflow.workflow_stage_assignments'
            : 'workflow_stage_assignments';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'workflow_stage_id',
        'assignee_type',
        'user_id',
        'role_code',
        'administrative_unit_id',
        'tier_level',
        'authority_type',
        'can_delegate',
        'is_mandatory',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'can_delegate' => 'boolean',
            'is_mandatory' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'workflow_stage_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function administrativeUnit(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class, 'administrative_unit_id');
    }
}
