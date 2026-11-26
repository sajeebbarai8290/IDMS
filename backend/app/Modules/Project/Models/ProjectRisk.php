<?php

namespace App\Modules\Project\Models;

use App\Core\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectRisk extends Model
{
    use HasUuids;

    public const KIND_RISK = 'RISK';
    public const KIND_ISSUE = 'ISSUE';

    public const SEVERITY_LOW = 'LOW';
    public const SEVERITY_MEDIUM = 'MEDIUM';
    public const SEVERITY_HIGH = 'HIGH';
    public const SEVERITY_CRITICAL = 'CRITICAL';

    public const STATUS_OPEN = 'OPEN';
    public const STATUS_MITIGATING = 'MITIGATING';
    public const STATUS_RESOLVED = 'RESOLVED';
    public const STATUS_CLOSED = 'CLOSED';

    protected $table = 'project.project_risks';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'project.project_risks'
            : 'project_risks';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'project_id',
        'risk_code',
        'record_kind',
        'title',
        'description',
        'severity',
        'likelihood',
        'mitigation_plan',
        'owner_name',
        'status',
        'identified_date',
        'resolved_date',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'identified_date' => 'date',
            'resolved_date' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
