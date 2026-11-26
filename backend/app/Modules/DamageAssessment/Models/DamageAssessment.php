<?php

namespace App\Modules\DamageAssessment\Models;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Core\Traits\HasJurisdictionScope;
use App\Modules\Disaster\Models\DisasterEvent;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DamageAssessment extends Model
{
    use HasUuids, SoftDeletes, HasJurisdictionScope;

    public const TYPE_INITIAL_RAPID = 'INITIAL_RAPID';
    public const TYPE_PRELIMINARY_D_FORM = 'PRELIMINARY_D_FORM';
    public const TYPE_DETAILED_SECTORAL = 'DETAILED_SECTORAL';
    public const TYPE_FINAL_CONSOLIDATED = 'FINAL_CONSOLIDATED';

    public const ASSESSMENT_TYPES = [
        self::TYPE_INITIAL_RAPID,
        self::TYPE_PRELIMINARY_D_FORM,
        self::TYPE_DETAILED_SECTORAL,
        self::TYPE_FINAL_CONSOLIDATED,
    ];

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_SUBMITTED = 'SUBMITTED';
    public const STATUS_UNDER_VERIFICATION = 'UNDER_VERIFICATION';
    public const STATUS_CORRECTION_REQUESTED = 'CORRECTION_REQUESTED';
    public const STATUS_VERIFIED = 'VERIFIED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_APPROVED = 'APPROVED';

    public const VERIFICATION_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SUBMITTED,
        self::STATUS_UNDER_VERIFICATION,
        self::STATUS_CORRECTION_REQUESTED,
        self::STATUS_VERIFIED,
        self::STATUS_REJECTED,
        self::STATUS_APPROVED,
    ];

    public const REPORTABLE_STATUSES = [
        self::STATUS_VERIFIED,
        self::STATUS_APPROVED,
    ];

    public const SEVERITY_LOW = 'LOW';
    public const SEVERITY_MODERATE = 'MODERATE';
    public const SEVERITY_SEVERE = 'SEVERE';
    public const SEVERITY_CRITICAL = 'CRITICAL';

    public const SEVERITY_LEVELS = [
        self::SEVERITY_LOW,
        self::SEVERITY_MODERATE,
        self::SEVERITY_SEVERE,
        self::SEVERITY_CRITICAL,
    ];

    public const SYNC_ONLINE = 'ONLINE';
    public const SYNC_SYNCED = 'SYNCED';
    public const SYNC_CONFLICT = 'CONFLICT';

    protected $table = 'damage.damage_assessments';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'damage.damage_assessments'
            : 'damage_assessments';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'disaster_event_id',
        'administrative_unit_id',
        'd_form_code',
        'reference_no',
        'assessment_type',
        'assessment_date',
        'assessed_at',
        'assessing_organization',
        'team_name',
        'team_lead_name',
        'team_members',
        'assessor_user_id',
        'assessor_name',
        'assessor_designation',
        'assessor_contact',
        'ward_no',
        'location_summary',
        'latitude',
        'longitude',
        'boundary_geojson',
        'severity_level',
        'affected_households',
        'affected_population',
        'displaced_population',
        'deaths_count',
        'injured_count',
        'missing_count',
        'total_fully_damaged',
        'total_partially_damaged',
        'total_minor_damaged',
        'total_estimated_loss_bdt',
        'field_observations',
        'verification_status',
        'correction_notes',
        'rejection_reason',
        'submitted_by',
        'submitted_at',
        'verified_by',
        'verified_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'created_by',
        'updated_by',
        'client_uuid',
        'idempotency_key',
        'device_id',
        'offline_created_at',
        'synced_at',
        'sync_status',
        'sync_version',
    ];

    protected function casts(): array
    {
        return [
            'assessment_date' => 'date',
            'assessed_at' => 'datetime',
            'team_members' => 'array',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'boundary_geojson' => 'array',
            'affected_households' => 'integer',
            'affected_population' => 'integer',
            'displaced_population' => 'integer',
            'deaths_count' => 'integer',
            'injured_count' => 'integer',
            'missing_count' => 'integer',
            'total_fully_damaged' => 'integer',
            'total_partially_damaged' => 'integer',
            'total_minor_damaged' => 'integer',
            'total_estimated_loss_bdt' => 'decimal:2',
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'offline_created_at' => 'datetime',
            'synced_at' => 'datetime',
            'sync_version' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function disasterEvent(): BelongsTo
    {
        return $this->belongsTo(DisasterEvent::class, 'disaster_event_id');
    }

    public function administrativeUnit(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class, 'administrative_unit_id');
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessor_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(DamageLocation::class, 'damage_assessment_id');
    }

    public function sectorItems(): HasMany
    {
        return $this->hasMany(DamageSectorItem::class, 'damage_assessment_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(DamageAttachment::class, 'damage_assessment_id');
    }

    public function verificationLogs(): HasMany
    {
        return $this->hasMany(DamageVerificationLog::class, 'damage_assessment_id')
            ->orderByDesc('created_at');
    }

    public function isEditable(): bool
    {
        return in_array($this->verification_status, [
            self::STATUS_DRAFT,
            self::STATUS_CORRECTION_REQUESTED,
        ], true);
    }
}
