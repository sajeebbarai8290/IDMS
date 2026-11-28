<?php

namespace App\Modules\Volunteer\Models;

use App\Core\Models\AdministrativeUnit;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VolunteerOrganizationCoverage extends Model
{
    use HasUuids;

    protected $table = 'volunteer.volunteer_organization_coverages';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'volunteer.volunteer_organization_coverages'
            : 'volunteer_organization_coverages';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'organization_id',
        'administrative_unit_id',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(VolunteerOrganization::class, 'organization_id');
    }

    public function administrativeUnit(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class, 'administrative_unit_id');
    }
}
