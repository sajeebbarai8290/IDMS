<?php

namespace App\Modules\Document\Models;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentAccessLog extends Model
{
    use HasUuids;

    public const ACTION_UPLOADED = 'UPLOADED';
    public const ACTION_VIEWED_METADATA = 'VIEWED_METADATA';
    public const ACTION_PREVIEWED = 'PREVIEWED';
    public const ACTION_DOWNLOADED = 'DOWNLOADED';
    public const ACTION_SIGNED_URL_ISSUED = 'SIGNED_URL_ISSUED';
    public const ACTION_VERSION_UPLOADED = 'VERSION_UPLOADED';
    public const ACTION_VERSION_RESTORED = 'VERSION_RESTORED';
    public const ACTION_UPDATED = 'UPDATED';
    public const ACTION_SUBMITTED_FOR_APPROVAL = 'SUBMITTED_FOR_APPROVAL';
    public const ACTION_APPROVED = 'APPROVED';
    public const ACTION_REJECTED = 'REJECTED';
    public const ACTION_ARCHIVED = 'ARCHIVED';
    public const ACTION_INTEGRITY_VERIFIED = 'INTEGRITY_VERIFIED';
    public const ACTION_DELETED = 'DELETED';

    protected $table = 'document.document_access_logs';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'document.document_access_logs'
            : 'document_access_logs';
    }

    public $timestamps = false;
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'document_id',
        'document_version_id',
        'user_id',
        'administrative_unit_id',
        'action',
        'ip_address',
        'user_agent',
        'details',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
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
