<?php

namespace App\Modules\Response\Models;

use App\Core\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResponseTaskAttachment extends Model
{
    use HasUuids;

    protected $table = 'response.response_task_attachments';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'response.response_task_attachments'
            : 'response_task_attachments';
    }

    protected $fillable = [
        'response_task_id',
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

    public function task(): BelongsTo
    {
        return $this->belongsTo(ResponseTask::class, 'response_task_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
