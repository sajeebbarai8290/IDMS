<?php

namespace App\Modules\Document\Models;

use App\Core\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentVersion extends Model
{
    use HasUuids;

    protected $table = 'document.document_versions';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'document.document_versions'
            : 'document_versions';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'document_id',
        'version_number',
        'version_label',
        'change_notes',
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
        'is_current',
        'uploaded_by',
    ];

    protected $hidden = [
        'storage_disk',
        'storage_path',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'file_size_bytes' => 'integer',
            'is_current' => 'boolean',
            'scanned_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
