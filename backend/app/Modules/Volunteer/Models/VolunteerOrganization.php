<?php

namespace App\Modules\Volunteer\Models;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Core\Traits\HasJurisdictionScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class VolunteerOrganization extends Model
{
    use HasUuids, SoftDeletes, HasJurisdictionScope;

    public const ORG_TYPES = [
        'CPP',
        'BDRCS',
        'SCOUT',
        'BNCC',
        'NGO',
        'INGO',
        'COMMUNITY',
        'GOVERNMENT',
        'OTHER',
    ];

    public const VERIFICATION_STATUSES = [
        'PENDING',
        'VERIFIED',
        'REJECTED',
    ];

    protected $table = 'volunteer.volunteer_organizations';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'volunteer.volunteer_organizations'
            : 'volunteer_organizations';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'org_code',
        'name_en',
        'name_bn',
        'org_type',
        'registration_no',
        'administrative_unit_id',
        'address',
        'contact_person_name',
        'contact_person_designation',
        'contact_person_phone',
        'contact_person_email',
        'description',
        'verification_status',
        'verified_by',
        'verified_at',
        'verification_remarks',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $hidden = ['deleted_at'];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function administrativeUnit(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class, 'administrative_unit_id');
    }

    public function coverages(): HasMany
    {
        return $this->hasMany(VolunteerOrganizationCoverage::class, 'organization_id');
    }

    public function volunteers(): HasMany
    {
        return $this->hasMany(Volunteer::class, 'organization_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
