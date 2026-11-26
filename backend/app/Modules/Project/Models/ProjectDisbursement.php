<?php

namespace App\Modules\Project\Models;

use App\Core\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectDisbursement extends Model
{
    use HasUuids;

    public const TYPE_BUDGET_REVISION = 'BUDGET_REVISION';
    public const TYPE_FUND_RELEASE = 'FUND_RELEASE';
    public const TYPE_EXPENDITURE = 'EXPENDITURE';

    public const RECORD_TYPES = [
        self::TYPE_BUDGET_REVISION,
        self::TYPE_FUND_RELEASE,
        self::TYPE_EXPENDITURE,
    ];

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_RELEASED = 'RELEASED';
    public const STATUS_RECONCILED = 'RECONCILED';
    public const STATUS_REJECTED = 'REJECTED';

    public const PAYMENT_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_RELEASED,
        self::STATUS_RECONCILED,
        self::STATUS_REJECTED,
    ];

    protected $table = 'project.project_disbursements';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'project.project_disbursements'
            : 'project_disbursements';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'project_id',
        'project_milestone_id',
        'record_type',
        'voucher_no',
        'fiscal_year',
        'amount_bdt',
        'payment_status',
        'description',
        'supporting_doc_path',
        'supporting_doc_name',
        'supporting_doc_mime',
        'supporting_doc_size',
        'supporting_doc_sha256',
        'disbursed_at',
        'created_by',
        'approved_by',
        'approved_at',
        'approval_notes',
    ];

    protected function casts(): array
    {
        return [
            'amount_bdt' => 'decimal:2',
            'supporting_doc_size' => 'integer',
            'disbursed_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(ProjectMilestone::class, 'project_milestone_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
