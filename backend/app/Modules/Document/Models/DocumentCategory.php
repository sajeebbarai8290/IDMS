<?php

namespace App\Modules\Document\Models;

use App\Core\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentCategory extends Model
{
    use HasUuids, SoftDeletes;

    public const CODE_GOVERNMENT_ORDER = 'GOVERNMENT_ORDER';
    public const CODE_DISASTER_REPORT = 'DISASTER_REPORT';
    public const CODE_ASSESSMENT_REPORT = 'ASSESSMENT_REPORT';
    public const CODE_APPROVAL_DOCUMENT = 'APPROVAL_DOCUMENT';
    public const CODE_PROJECT_DOCUMENT = 'PROJECT_DOCUMENT';
    public const CODE_RELIEF_DISTRIBUTION_DOCUMENT = 'RELIEF_DISTRIBUTION_DOCUMENT';
    public const CODE_AGREEMENT = 'AGREEMENT';
    public const CODE_PHOTOGRAPH = 'PHOTOGRAPH';
    public const CODE_OTHER = 'OTHER';

    public const SYSTEM_CODES = [
        self::CODE_GOVERNMENT_ORDER,
        self::CODE_DISASTER_REPORT,
        self::CODE_ASSESSMENT_REPORT,
        self::CODE_APPROVAL_DOCUMENT,
        self::CODE_PROJECT_DOCUMENT,
        self::CODE_RELIEF_DISTRIBUTION_DOCUMENT,
        self::CODE_AGREEMENT,
        self::CODE_PHOTOGRAPH,
        self::CODE_OTHER,
    ];

    protected $table = 'document.document_categories';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'document.document_categories'
            : 'document_categories';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'code',
        'name_en',
        'name_bn',
        'description_en',
        'description_bn',
        'default_classification',
        'allowed_formats',
        'retention_days',
        'requires_approval',
        'is_system',
        'is_active',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'allowed_formats' => 'array',
            'retention_days' => 'integer',
            'requires_approval' => 'boolean',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
