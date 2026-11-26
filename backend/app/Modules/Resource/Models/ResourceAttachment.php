<?php

namespace App\Modules\Resource\Models;

use App\Core\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResourceAttachment extends Model
{
    use HasUuids;

    public const KIND_DOCUMENT = 'DOCUMENT';
    public const KIND_PHOTOGRAPH = 'PHOTOGRAPH';

    public const KINDS = [
        self::KIND_DOCUMENT,
        self::KIND_PHOTOGRAPH,
    ];

    protected $table = 'resource.resource_attachments';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'resource.resource_attachments'
            : 'resource_attachments';
    }

    protected $fillable = [
        'resource_asset_id',
        'attachment_kind',
        'title',
        'original_name',
        'storage_path',
        'mime_type',
        'file_size_bytes',
        'sha256_hash',
        'uploaded_by',
    ];

    protected $casts = [
        'file_size_bytes' => 'integer',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(ResourceAsset::class, 'resource_asset_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
