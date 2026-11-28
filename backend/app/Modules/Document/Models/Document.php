<?php

namespace App\Modules\Document\Models;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Core\Traits\HasJurisdictionScope;
use App\Modules\UserManagement\Models\Organization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use HasUuids, SoftDeletes, HasJurisdictionScope;

    public const CLASS_PUBLIC = 'PUBLIC';
    public const CLASS_INTERNAL = 'INTERNAL';
    public const CLASS_CONFIDENTIAL = 'CONFIDENTIAL';
    public const CLASS_RESTRICTED = 'RESTRICTED';

    public const CLASSIFICATIONS = [
        self::CLASS_PUBLIC,
        self::CLASS_INTERNAL,
        self::CLASS_CONFIDENTIAL,
        self::CLASS_RESTRICTED,
    ];

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_PENDING_APPROVAL = 'PENDING_APPROVAL';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_ARCHIVED = 'ARCHIVED';

    public const APPROVAL_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PENDING_APPROVAL,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_ARCHIVED,
    ];

    public const TYPE_GOVERNMENT_ORDER = 'GOVERNMENT_ORDER';
    public const TYPE_CIRCULAR = 'CIRCULAR';
    public const TYPE_SITREP = 'SITREP';
    public const TYPE_SURVEY_REPORT = 'SURVEY_REPORT';
    public const TYPE_APPROVAL_LETTER = 'APPROVAL_LETTER';
    public const TYPE_MUSTER_ROLL = 'MUSTER_ROLL';
    public const TYPE_VOUCHER = 'VOUCHER';
    public const TYPE_AGREEMENT = 'AGREEMENT';
    public const TYPE_BLUEPRINT = 'BLUEPRINT';
    public const TYPE_PHOTOGRAPH = 'PHOTOGRAPH';
    public const TYPE_CERTIFICATE = 'CERTIFICATE';
    public const TYPE_OTHER = 'OTHER';

    public const DOCUMENT_TYPES = [
        self::TYPE_GOVERNMENT_ORDER,
        self::TYPE_CIRCULAR,
        self::TYPE_SITREP,
        self::TYPE_SURVEY_REPORT,
        self::TYPE_APPROVAL_LETTER,
        self::TYPE_MUSTER_ROLL,
        self::TYPE_VOUCHER,
        self::TYPE_AGREEMENT,
        self::TYPE_BLUEPRINT,
        self::TYPE_PHOTOGRAPH,
        self::TYPE_CERTIFICATE,
        self::TYPE_OTHER,
    ];

    public const MODULE_GENERAL = 'GENERAL';
    public const MODULE_BENEFICIARY = 'BENEFICIARY';
    public const MODULE_DISASTER = 'DISASTER';
    public const MODULE_DAMAGE = 'DAMAGE';
    public const MODULE_RELIEF = 'RELIEF';
    public const MODULE_PROJECT = 'PROJECT';
    public const MODULE_WORKFLOW = 'WORKFLOW';
    public const MODULE_RESPONSE = 'RESPONSE';
    public const MODULE_RESOURCE = 'RESOURCE';
    public const MODULE_WAREHOUSE = 'WAREHOUSE';
    public const MODULE_VOLUNTEER = 'VOLUNTEER';

    public const SUPPORTED_MODULES = [
        self::MODULE_GENERAL,
        self::MODULE_BENEFICIARY,
        self::MODULE_DISASTER,
        self::MODULE_DAMAGE,
        self::MODULE_RELIEF,
        self::MODULE_PROJECT,
        self::MODULE_WORKFLOW,
        self::MODULE_RESPONSE,
        self::MODULE_RESOURCE,
        self::MODULE_WAREHOUSE,
        self::MODULE_VOLUNTEER,
    ];

    public const PREVIEWABLE_MIMES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'text/csv',
        'text/plain',
    ];

    protected $table = 'document.documents';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'document.documents'
            : 'documents';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'document_no',
        'reference_no',
        'category_id',
        'document_type',
        'title_bn',
        'title_en',
        'description_bn',
        'description_en',
        'issuing_organization',
        'organization_id',
        'administrative_unit_id',
        'owner_user_id',
        'uploaded_by',
        'uploaded_at',
        'effective_date',
        'expires_at',
        'module_code',
        'entity_type',
        'entity_id',
        'entity_reference',
        'classification',
        'tags',
        'approval_status',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'approval_notes',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'current_version_number',
        'current_version_id',
        'original_filename',
        'storage_disk',
        'storage_path',
        'mime_type',
        'file_extension',
        'file_size_bytes',
        'file_hash_sha256',
        'scan_status',
        'scanned_at',
        'scan_engine',
        'scan_details',
        'is_confidential',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $hidden = [
        'storage_disk',
        'storage_path',
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'metadata' => 'array',
            'uploaded_at' => 'datetime',
            'effective_date' => 'date',
            'expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'scanned_at' => 'datetime',
            'current_version_number' => 'integer',
            'file_size_bytes' => 'integer',
            'is_confidential' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function isPreviewable(): bool
    {
        return in_array($this->mime_type, self::PREVIEWABLE_MIMES, true);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isConfidentialFile(): bool
    {
        return (bool) $this->is_confidential
            || in_array($this->classification, [self::CLASS_CONFIDENTIAL, self::CLASS_RESTRICTED], true);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'category_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function administrativeUnit(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class, 'administrative_unit_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class, 'document_id')->orderByDesc('version_number');
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(DocumentAccessLog::class, 'document_id')->orderByDesc('created_at');
    }
}
