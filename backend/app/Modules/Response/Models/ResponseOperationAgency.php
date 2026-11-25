<?php

namespace App\Modules\Response\Models;

use App\Core\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResponseOperationAgency extends Model
{
    use HasUuids;

    public const AGENCY_TYPES = ['GOVERNMENT', 'ARMED_FORCES', 'FIRE_SERVICE', 'COAST_GUARD', 'NGO', 'UN_AGENCY', 'VOLUNTEER_ORG'];

    protected $table = 'response.response_operation_agencies';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'response.response_operation_agencies'
            : 'response_operation_agencies';
    }

    protected $fillable = [
        'response_operation_id',
        'agency_name',
        'agency_type',
        'role_summary',
        'focal_person_name',
        'focal_contact',
        'coordination_status',
        'added_by',
    ];

    public function operation(): BelongsTo
    {
        return $this->belongsTo(ResponseOperation::class, 'response_operation_id');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
