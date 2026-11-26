<?php

namespace App\Modules\Resource\Models;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Core\Traits\HasJurisdictionScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResourceMaintenance extends Model
{
    use HasUuids, HasJurisdictionScope;

    public const TYPE_SCHEDULED = 'SCHEDULED';
    public const TYPE_PREVENTIVE = 'PREVENTIVE';
    public const TYPE_CORRECTIVE = 'CORRECTIVE';
    public const TYPE_INSPECTION = 'INSPECTION';
    public const TYPE_REPAIR = 'REPAIR';

    public const RECORD_TYPES = [
        self::TYPE_SCHEDULED,
        self::TYPE_PREVENTIVE,
        self::TYPE_CORRECTIVE,
        self::TYPE_INSPECTION,
        self::TYPE_REPAIR,
    ];

    public const STATUS_SCHEDULED = 'SCHEDULED';
    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_CANCELLED = 'CANCELLED';

    public const STATUSES = [
        self::STATUS_SCHEDULED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    public const INSPECTION_PASS = 'PASS';
    public const INSPECTION_CONDITIONAL = 'CONDITIONAL';
    public const INSPECTION_FAIL = 'FAIL';

    public const INSPECTION_RESULTS = [
        self::INSPECTION_PASS,
        self::INSPECTION_CONDITIONAL,
        self::INSPECTION_FAIL,
    ];

    protected $table = 'resource.resource_maintenances';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'resource.resource_maintenances'
            : 'resource_maintenances';
    }

    protected $fillable = [
        'resource_asset_id',
        'administrative_unit_id',
        'maintenance_no',
        'record_type',
        'title',
        'description',
        'scheduled_date',
        'performed_date',
        'next_due_date',
        'reminder_days_before',
        'inspection_findings',
        'inspection_result',
        'repair_cost_bdt',
        'vendor_or_workshop',
        'status',
        'performed_by_name',
        'attachment_path',
        'attachment_original_name',
        'attachment_mime',
        'attachment_size_bytes',
        'attachment_sha256',
        'recorded_by',
        'completed_by',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'performed_date' => 'date',
        'next_due_date' => 'date',
        'reminder_days_before' => 'integer',
        'repair_cost_bdt' => 'decimal:2',
        'attachment_size_bytes' => 'integer',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(ResourceAsset::class, 'resource_asset_id');
    }

    public function administrativeUnit(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class, 'administrative_unit_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
