<?php

namespace App\Modules\Project\Models;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Core\Traits\HasJurisdictionScope;
use App\Modules\Disaster\Models\DisasterEvent;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasUuids, SoftDeletes, HasJurisdictionScope;

    public const STATUS_PROPOSED = 'PROPOSED';
    public const STATUS_UNDER_REVIEW = 'UNDER_REVIEW';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_ONGOING = 'ONGOING';
    public const STATUS_SUSPENDED = 'SUSPENDED';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_CLOSED = 'CLOSED';

    public const LIFECYCLE_STATUSES = [
        self::STATUS_PROPOSED,
        self::STATUS_UNDER_REVIEW,
        self::STATUS_APPROVED,
        self::STATUS_ONGOING,
        self::STATUS_SUSPENDED,
        self::STATUS_COMPLETED,
        self::STATUS_CLOSED,
    ];

    public const ACTIVE_STATUSES = [
        self::STATUS_APPROVED,
        self::STATUS_ONGOING,
    ];

    public const ALLOWED_TRANSITIONS = [
        self::STATUS_PROPOSED => [self::STATUS_UNDER_REVIEW],
        self::STATUS_UNDER_REVIEW => [self::STATUS_APPROVED, self::STATUS_PROPOSED],
        self::STATUS_APPROVED => [self::STATUS_ONGOING, self::STATUS_SUSPENDED],
        self::STATUS_ONGOING => [self::STATUS_SUSPENDED, self::STATUS_COMPLETED],
        self::STATUS_SUSPENDED => [self::STATUS_ONGOING, self::STATUS_CLOSED],
        self::STATUS_COMPLETED => [self::STATUS_CLOSED],
        self::STATUS_CLOSED => [],
    ];

    protected $table = 'project.projects';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'project.projects'
            : 'projects';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'category_id',
        'disaster_event_id',
        'administrative_unit_id',
        'project_code',
        'reference_no',
        'title_en',
        'title_bn',
        'description',
        'objectives',
        'implementing_organization',
        'project_manager_name',
        'project_manager_contact',
        'project_manager_user_id',
        'funding_source',
        'approved_budget_bdt',
        'revised_budget_bdt',
        'released_funds_bdt',
        'expenditure_bdt',
        'physical_progress_pct',
        'financial_progress_pct',
        'start_date',
        'expected_end_date',
        'actual_end_date',
        'ward_no',
        'location_summary',
        'latitude',
        'longitude',
        'boundary_geojson',
        'status',
        'status_notes',
        'is_delayed',
        'delay_days',
        'delay_reason',
        'created_by',
        'updated_by',
        'reviewed_by',
        'reviewed_at',
        'approved_by',
        'approved_at',
        'closed_by',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'approved_budget_bdt' => 'decimal:2',
            'revised_budget_bdt' => 'decimal:2',
            'released_funds_bdt' => 'decimal:2',
            'expenditure_bdt' => 'decimal:2',
            'physical_progress_pct' => 'decimal:2',
            'financial_progress_pct' => 'decimal:2',
            'start_date' => 'date',
            'expected_end_date' => 'date',
            'actual_end_date' => 'date',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'boundary_geojson' => 'array',
            'is_delayed' => 'boolean',
            'delay_days' => 'integer',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * Effective budget ceiling (revised budget if approved/set, otherwise approved budget).
     */
    public function getEffectiveBudgetBdtAttribute(): float
    {
        $revised = $this->revised_budget_bdt !== null ? (float) $this->revised_budget_bdt : null;
        return $revised !== null && $revised > 0 ? $revised : (float) $this->approved_budget_bdt;
    }

    /**
     * Remaining budget balance (effective budget minus recorded expenditure).
     */
    public function getRemainingBalanceBdtAttribute(): float
    {
        return round(max(0.0, $this->effective_budget_bdt - (float) $this->expenditure_bdt), 2);
    }

    /**
     * Unspent released funds (released funds minus recorded expenditure).
     */
    public function getUnreleasedBalanceBdtAttribute(): float
    {
        return round(max(0.0, $this->effective_budget_bdt - (float) $this->released_funds_bdt), 2);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProjectCategory::class, 'category_id');
    }

    public function disasterEvent(): BelongsTo
    {
        return $this->belongsTo(DisasterEvent::class, 'disaster_event_id');
    }

    public function administrativeUnit(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class, 'administrative_unit_id');
    }

    public function projectManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'project_manager_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(ProjectLocation::class, 'project_id');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class, 'project_id')->orderBy('wbs_code');
    }

    public function disbursements(): HasMany
    {
        return $this->hasMany(ProjectDisbursement::class, 'project_id')->orderByDesc('disbursed_at');
    }

    public function monitoringReports(): HasMany
    {
        return $this->hasMany(ProjectMonitoringReport::class, 'project_id')->orderByDesc('visit_date');
    }

    public function risks(): HasMany
    {
        return $this->hasMany(ProjectRisk::class, 'project_id')->orderByDesc('identified_date');
    }
}
