<?php

namespace App\Modules\Response\Models;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Core\Traits\HasJurisdictionScope;
use App\Modules\Disaster\Models\DisasterEvent;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResponseSituationReport extends Model
{
    use HasUuids, SoftDeletes, HasJurisdictionScope;

    public const REPORT_TYPES = ['INCIDENT', 'FIELD_UPDATE', 'SITREP', 'SOS'];
    public const PRIVACY_PUBLIC_AREA = 'PUBLIC_AREA';
    public const PRIVACY_RESTRICTED_EXACT = 'RESTRICTED_EXACT';
    public const PRIVACY_LEVELS = [self::PRIVACY_PUBLIC_AREA, self::PRIVACY_RESTRICTED_EXACT];

    protected $table = 'response.response_situation_reports';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'response.response_situation_reports'
            : 'response_situation_reports';
    }

    protected $fillable = [
        'response_operation_id',
        'disaster_event_id',
        'administrative_unit_id',
        'report_no',
        'report_type',
        'severity_level',
        'title_en',
        'title_bn',
        'summary',
        'casualties_injured',
        'casualties_missing',
        'casualties_fatalities',
        'rescued_count',
        'evacuated_count',
        'emergency_requirements',
        'location_description',
        'latitude',
        'longitude',
        'privacy_level',
        'reported_by',
        'reported_at',
    ];

    protected $casts = [
        'casualties_injured' => 'integer',
        'casualties_missing' => 'integer',
        'casualties_fatalities' => 'integer',
        'rescued_count' => 'integer',
        'evacuated_count' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'reported_at' => 'datetime',
    ];

    public function operation(): BelongsTo
    {
        return $this->belongsTo(ResponseOperation::class, 'response_operation_id');
    }

    public function disasterEvent(): BelongsTo
    {
        return $this->belongsTo(DisasterEvent::class, 'disaster_event_id');
    }

    public function administrativeUnit(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class, 'administrative_unit_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
