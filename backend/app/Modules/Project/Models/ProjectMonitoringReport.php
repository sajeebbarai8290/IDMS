<?php

namespace App\Modules\Project\Models;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMonitoringReport extends Model
{
    use HasUuids;

    protected $table = 'project.project_monitoring_reports';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'project.project_monitoring_reports'
            : 'project_monitoring_reports';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'project_id',
        'administrative_unit_id',
        'report_no',
        'visit_date',
        'inspector_name',
        'inspector_designation',
        'inspector_user_id',
        'observed_physical_pct',
        'observed_financial_pct',
        'delay_observed',
        'delay_days',
        'delay_reason',
        'findings',
        'recommendations',
        'latitude',
        'longitude',
        'attachment_path',
        'attachment_original_name',
        'attachment_mime',
        'attachment_size_bytes',
        'attachment_sha256',
    ];

    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
            'observed_physical_pct' => 'decimal:2',
            'observed_financial_pct' => 'decimal:2',
            'delay_observed' => 'boolean',
            'delay_days' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'attachment_size_bytes' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function administrativeUnit(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class, 'administrative_unit_id');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_user_id');
    }
}
