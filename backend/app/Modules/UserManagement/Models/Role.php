<?php

namespace App\Modules\UserManagement\Models;

use App\Core\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public const TIER_LEVELS = [
        'NATIONAL',
        'DIRECTORATE',
        'DIVISION',
        'DISTRICT',
        'UPAZILA',
        'UNION',
        'FIELD',
    ];

    public const TIER_RANKS = [
        'NATIONAL' => 1,
        'DIRECTORATE' => 2,
        'DIVISION' => 3,
        'DISTRICT' => 4,
        'UPAZILA' => 5,
        'UNION' => 6,
        'FIELD' => 7,
    ];

    protected $table = 'core.roles';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'core.roles'
            : 'roles';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'code',
        'name_en',
        'name_bn',
        'tier_level',
        'hierarchy_rank',
        'description',
        'is_system',
        'is_active',
        'mfa_required',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'hierarchy_rank' => 'integer',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'mfa_required' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function permissions(): BelongsToMany
    {
        $pivotTable = $this->getConnection()->getDriverName() === 'pgsql'
            ? 'core.role_permissions'
            : 'role_permissions';

        return $this->belongsToMany(Permission::class, $pivotTable, 'role_id', 'permission_id')
            ->withPivot('created_at');
    }

    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class, 'role_id');
    }

    public function users(): BelongsToMany
    {
        $pivotTable = $this->getConnection()->getDriverName() === 'pgsql'
            ? 'core.user_roles'
            : 'user_roles';

        return $this->belongsToMany(User::class, $pivotTable, 'role_id', 'user_id')
            ->withPivot(['id', 'is_primary', 'assigned_by', 'assigned_at']);
    }
}
