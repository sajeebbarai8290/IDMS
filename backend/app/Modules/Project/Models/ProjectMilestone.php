<?php

namespace App\Modules\Project\Models;

use App\Core\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectMilestone extends Model
{
    use HasUuids;

    public const TYPE_PHASE = 'PHASE';
    public const TYPE_ACTIVITY = 'ACTIVITY';
    public const TYPE_MILESTONE = 'MILESTONE';

    public const ITEM_TYPES = [
        self::TYPE_PHASE,
        self::TYPE_ACTIVITY,
        self::TYPE_MILESTONE,
    ];

    public const APPROVAL_PENDING = 'PENDING';
    public const APPROVAL_SUBMITTED = 'SUBMITTED';
    public const APPROVAL_VERIFIED_ENGINEER = 'VERIFIED_ENGINEER';
    public const APPROVAL_APPROVED_PIO = 'APPROVED_PIO';
    public const APPROVAL_REJECTED = 'REJECTED';

    public const APPROVAL_STATUSES = [
        self::APPROVAL_PENDING,
        self::APPROVAL_SUBMITTED,
        self::APPROVAL_VERIFIED_ENGINEER,
        self::APPROVAL_APPROVED_PIO,
        self::APPROVAL_REJECTED,
    ];

    protected $table = 'project.project_milestones';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'project.project_milestones'
            : 'project_milestones';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'project_id',
        'parent_milestone_id',
        'wbs_code',
        'milestone_title',
        'milestone_title_bn',
        'item_type',
        'deliverables',
        'responsible_organization',
        'assigned_officer_name',
        'assigned_officer_user_id',
        'planned_start_date',
        'planned_end_date',
        'actual_start_date',
        'actual_end_date',
        'weight_percentage',
        'target_percentage',
        'completion_percentage',
        'approval_status',
        'delay_reason',
        'certification_notes',
        'updated_by',
        'certified_by',
        'certified_at',
    ];

    protected function casts(): array
    {
        return [
            'planned_start_date' => 'date',
            'planned_end_date' => 'date',
            'actual_start_date' => 'date',
            'actual_end_date' => 'date',
            'weight_percentage' => 'decimal:2',
            'target_percentage' => 'decimal:2',
            'completion_percentage' => 'decimal:2',
            'certified_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_milestone_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_milestone_id');
    }

    public function assignedOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_officer_user_id');
    }

    public function certifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'certified_by');
    }

    public function disbursements(): HasMany
    {
        return $this->hasMany(ProjectDisbursement::class, 'project_milestone_id');
    }
}
