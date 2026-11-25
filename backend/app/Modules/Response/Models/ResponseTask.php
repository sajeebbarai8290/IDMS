<?php

namespace App\Modules\Response\Models;

use App\Core\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResponseTask extends Model
{
    use HasUuids, SoftDeletes;

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    public const STATUS_BLOCKED = 'BLOCKED';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_CANCELLED = 'CANCELLED';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_IN_PROGRESS,
        self::STATUS_BLOCKED,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    protected $table = 'response.response_tasks';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'response.response_tasks'
            : 'response_tasks';
    }

    protected $fillable = [
        'response_operation_id',
        'activity_type_id',
        'response_team_id',
        'assigned_user_id',
        'task_code',
        'title_en',
        'title_bn',
        'description',
        'assigned_officer_name',
        'priority',
        'status',
        'progress_percentage',
        'deadline_at',
        'completion_notes',
        'completed_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'progress_percentage' => 'integer',
        'deadline_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function operation(): BelongsTo
    {
        return $this->belongsTo(ResponseOperation::class, 'response_operation_id');
    }

    public function activityType(): BelongsTo
    {
        return $this->belongsTo(ResponseActivityType::class, 'activity_type_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(ResponseTeam::class, 'response_team_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ResponseTaskAttachment::class, 'response_task_id')->orderByDesc('created_at');
    }
}
