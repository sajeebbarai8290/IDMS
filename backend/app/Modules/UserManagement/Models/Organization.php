<?php

namespace App\Modules\UserManagement\Models;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Core\Traits\HasJurisdictionScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasFactory, HasUuids, HasJurisdictionScope, SoftDeletes;

    public const ORG_TYPES = [
        'MINISTRY',
        'DIRECTORATE',
        'DIVISION_ADMIN',
        'DISTRICT_ADMIN',
        'UPAZILA_ADMIN',
        'UNION_PARISHAD',
        'DEFENSE_RESCUE',
        'HUMANITARIAN_PARTNER',
        'VOLUNTEER_AGENCY',
        'OTHER',
    ];

    protected $table = 'core.organizations';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'core.organizations'
            : 'organizations';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'org_code',
        'name_en',
        'name_bn',
        'org_type',
        'tier_level',
        'administrative_unit_id',
        'parent_organization_id',
        'email',
        'phone',
        'address',
        'description',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function administrativeUnit(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class, 'administrative_unit_id');
    }

    public function parentOrganization(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_organization_id');
    }

    public function childOrganizations(): HasMany
    {
        return $this->hasMany(self::class, 'parent_organization_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'organization_id');
    }
}
