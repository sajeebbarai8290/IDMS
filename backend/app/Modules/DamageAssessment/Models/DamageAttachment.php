<?php

namespace App\Modules\DamageAssessment\Models;

use App\Core\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DamageAttachment extends Model
{
    use HasUuids;

    public const KIND_PHOTOGRAPH = 'PHOTOGRAPH';
    public const KIND_DOCUMENT = 'DOCUMENT';

    public const KINDS = [
        self::KIND_PHOTOGRAPH,
        self::KIND_DOCUMENT,
    ];

    protected $table = 'damage.damage_attachments';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'damage.damage_attachments'
            : 'damage_attachments';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'damage_assessment_id',
        'damage_sector_item_id',
        'attachment_kind',
        'title',
        'caption',
        'original_name',
        'storage_path',
        'mime_type',
        'file_size_bytes',
        'sha256_hash',
        'latitude',
        'longitude',
        'captured_at',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'file_size_bytes' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'captured_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(DamageAssessment::class, 'damage_assessment_id');
    }

    public function sectorItem(): BelongsTo
    {
        return $this->belongsTo(DamageSectorItem::class, 'damage_sector_item_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
