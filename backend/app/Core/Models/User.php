<?php

namespace App\Core\Models;

use App\Modules\UserManagement\Models\Organization;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\UserAdministrativeScope;
use App\Modules\UserManagement\Models\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Class User
 *
 * Core sovereign user model for IDMS synchronized with Keycloak IAM.
 * Passwords are NEVER stored, validated, or hashed inside Laravel.
 *
 * @package App\Core\Models
 */
class User extends Authenticatable
{
    use HasFactory, HasUuids, Notifiable, SoftDeletes;

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_SUSPENDED = 'SUSPENDED';
    public const STATUS_DEACTIVATED = 'DEACTIVATED';
    public const STATUS_LOCKED = 'LOCKED';

    public const ACCOUNT_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_ACTIVE,
        self::STATUS_SUSPENDED,
        self::STATUS_DEACTIVATED,
        self::STATUS_LOCKED,
    ];

    public const IDP_SYNC_LINKED = 'LINKED';
    public const IDP_SYNC_PENDING = 'PENDING_SYNC';
    public const IDP_SYNC_FAILED = 'SYNC_FAILED';
    public const IDP_SYNC_LOCKED = 'LOCKED_BY_IDP';
    public const IDP_SYNC_NOT_LINKED = 'NOT_LINKED';

    public const IDP_SYNC_STATUSES = [
        self::IDP_SYNC_LINKED,
        self::IDP_SYNC_PENDING,
        self::IDP_SYNC_FAILED,
        self::IDP_SYNC_LOCKED,
        self::IDP_SYNC_NOT_LINKED,
    ];

    protected $table = 'core.users';

    /**
     * Get the table associated with the model.
     * Uses PostgreSQL 'core.users' schema or fallback 'users' for SQLite testing.
     */
    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql' ? 'core.users' : 'users';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'keycloak_sub',
        'keycloak_username',
        'idp_sync_status',
        'idp_synced_at',
        'idp_sync_error',
        'email',
        'employee_id',
        'mobile_number',
        'full_name_en',
        'full_name_bn',
        'role_code',
        'administrative_unit_id',
        'organization_id',
        'department_en',
        'department_bn',
        'designation',
        'designation_bn',
        'is_active',
        'account_status',
        'status_reason',
        'status_changed_at',
        'status_changed_by',
        'photo_path',
        'photo_mime',
        'photo_hash',
        'mfa_enforced',
        'mfa_verified',
        'mfa_enrolled_at',
        'last_login_at',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'remember_token',
        'deleted_at',
        'photo_path',
    ];

    /**
     * Dynamic claims hydrated from Keycloak JWT.
     *
     * @var array
     */
    protected array $jwtClaims = [];

    /**
     * Request-local memoized effective role codes to avoid N+1 DB queries.
     *
     * @var list<string>|null
     */
    protected ?array $memoizedEffectiveRoleCodes = null;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'mfa_enforced' => 'boolean',
            'mfa_verified' => 'boolean',
            'mfa_enrolled_at' => 'datetime',
            'idp_synced_at' => 'datetime',
            'status_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Override password handling: IDMS delegates all credentials to Keycloak.
     */
    public function getAuthPassword(): string
    {
        return '';
    }

    public function setJwtClaims(array $claims): self
    {
        $this->jwtClaims = $claims;
        $this->memoizedEffectiveRoleCodes = null;
        return $this;
    }

    public function clearRbacMemoization(): self
    {
        $this->memoizedEffectiveRoleCodes = null;
        return $this;
    }

    public function getJwtClaims(): array
    {
        return $this->jwtClaims;
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function administrativeUnit(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class, 'administrative_unit_id');
    }

    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class, 'user_id');
    }

    public function roles(): BelongsToMany
    {
        $pivotTable = $this->getConnection()->getDriverName() === 'pgsql'
            ? 'core.user_roles'
            : 'user_roles';

        return $this->belongsToMany(Role::class, $pivotTable, 'user_id', 'role_id')
            ->withPivot(['id', 'is_primary', 'assigned_by', 'assigned_at']);
    }

    public function administrativeScopes(): HasMany
    {
        return $this->hasMany(UserAdministrativeScope::class, 'user_id');
    }

    public function activeAdministrativeScopes(): HasMany
    {
        $now = now();

        return $this->hasMany(UserAdministrativeScope::class, 'user_id')
            ->where('is_active', true)
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', $now);
            })
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>=', $now);
            });
    }

    public function statusChangedByUser(): BelongsTo
    {
        return $this->belongsTo(self::class, 'status_changed_by');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(self::class, 'created_by');
    }

    /**
     * Whether the account is operationally active (not pending, suspended, deactivated, or locked).
     */
    public function isOperationalActive(): bool
    {
        $status = $this->account_status ?? ($this->is_active ? self::STATUS_ACTIVE : self::STATUS_DEACTIVATED);

        return (bool) $this->is_active && $status === self::STATUS_ACTIVE;
    }

    /**
     * Collect all effective role codes (primary column, JWT claims, and assigned core.user_roles).
     *
     * @return list<string>
     */
    public function getEffectiveRoleCodes(): array
    {
        if ($this->memoizedEffectiveRoleCodes !== null && !$this->relationLoaded('roles')) {
            return $this->memoizedEffectiveRoleCodes;
        }

        $codes = [];
        if (!empty($this->role_code)) {
            $codes[] = $this->role_code;
        }

        $jwtRoles = $this->jwtClaims['roles']
            ?? $this->jwtClaims['realm_access']['roles']
            ?? [];
        foreach ($jwtRoles as $r) {
            if (is_string($r) && str_starts_with($r, 'ROLE_')) {
                $codes[] = $r;
            }
        }

        if ($this->exists) {
            if ($this->relationLoaded('roles')) {
                foreach ($this->roles as $role) {
                    if ($role->is_active && !empty($role->code)) {
                        $codes[] = $role->code;
                    }
                }
            } else {
                try {
                    if ($this->rolesTableAvailable()) {
                        $dbCodes = $this->roles()->where('is_active', true)->pluck('code')->all();
                        $codes = array_merge($codes, $dbCodes);
                    }
                } catch (Throwable) {
                    // Ignore when schema is partially migrated during early unit tests
                }
            }
        }

        $resolved = array_values(array_unique($codes));
        $this->memoizedEffectiveRoleCodes = $resolved;

        return $resolved;
    }

    /**
     * Check if user possesses a given system role.
     */
    public function hasRole(string $role): bool
    {
        if ($this->role_code === $role) {
            return true;
        }

        $jwtRoles = $this->jwtClaims['roles']
            ?? $this->jwtClaims['realm_access']['roles']
            ?? [];
        if (in_array($role, $jwtRoles, true)) {
            return true;
        }

        return in_array($role, $this->getEffectiveRoleCodes(), true);
    }

    /**
     * Resolve effective granular permissions across JWT claims and DB role_permissions (cached).
     *
     * @return list<string>
     */
    public function getEffectivePermissions(): array
    {
        $permissions = [];
        if (!empty($this->jwtClaims['permissions']) && is_array($this->jwtClaims['permissions'])) {
            $permissions = $this->jwtClaims['permissions'];
        }

        try {
            if ($this->rolesTableAvailable()) {
                foreach ($this->getEffectiveRoleCodes() as $roleCode) {
                    $cached = Cache::remember(
                        "rbac:role_permissions:{$roleCode}",
                        3600,
                        function () use ($roleCode) {
                            $role = Role::where('code', $roleCode)->where('is_active', true)->first();
                            if (!$role) {
                                return [];
                            }
                            return $role->permissions()->pluck('code')->all();
                        }
                    );
                    $permissions = array_merge($permissions, $cached);
                }
            }
        } catch (Throwable) {
            // Fallback gracefully if RBAC tables are unavailable
        }

        return array_values(array_unique($permissions));
    }

    /**
     * Check if user possesses a given granular business permission.
     */
    public function hasPermission(string $permission): bool
    {
        // Super Admin possesses technical administration (operational blackouts remain enforced in domain policies)
        if ($this->hasRole('ROLE_SUPER_ADMIN')) {
            return true;
        }

        return in_array($permission, $this->getEffectivePermissions(), true);
    }

    /**
     * Check if the user's jurisdiction is at the National/Ministry level.
     */
    public function isNationalScope(): bool
    {
        foreach ([
            'ROLE_SUPER_ADMIN',
            'ROLE_MINISTRY_EXECUTIVE',
            'ROLE_NEOC_DIRECTOR',
            'ROLE_NATIONAL_AUDITOR',
            'ROLE_DDM_DIRECTOR_GENERAL',
            'ROLE_DDM_RELIEF_DIRECTOR',
            'ROLE_DDM_PLANNING_DIRECTOR',
        ] as $nationalRole) {
            if ($this->hasRole($nationalRole)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retrieve user's primary administrative scope.
     */
    public function getAdministrativeScope(): array
    {
        if (!empty($this->jwtClaims['administrative_profile'])) {
            return $this->jwtClaims['administrative_profile'];
        }

        $unit = $this->relationLoaded('administrativeUnit')
            ? $this->administrativeUnit
            : ($this->administrative_unit_id ? AdministrativeUnit::find($this->administrative_unit_id) : null);

        if ($unit) {
            return [
                'tier_level' => $unit->tier_level,
                'ancestry_path' => $unit->ancestry_path,
                'scope_id' => $unit->id,
                'name_en' => $unit->name_en,
                'name_bn' => $unit->name_bn,
                'designation' => $this->designation,
            ];
        }

        return [
            'tier_level' => 'NATIONAL',
            'ancestry_path' => '/',
            'scope_id' => $this->administrative_unit_id,
        ];
    }

    /**
     * Return all active ancestry paths (primary + valid temporary/additional scopes).
     *
     * @return list<string>
     */
    public function getAccessibleAncestryPaths(): array
    {
        $paths = [];
        $primary = $this->getAdministrativeScope();
        if (!empty($primary['ancestry_path'])) {
            $paths[] = rtrim($primary['ancestry_path'], '/') . '/';
        }

        if ($this->exists) {
            try {
                if ($this->scopesTableAvailable()) {
                    $scopes = $this->activeAdministrativeScopes()
                        ->where('assignment_type', '!=', 'PRIMARY_POSTING')
                        ->with('administrativeUnit')
                        ->get();
                    foreach ($scopes as $scope) {
                        $unitPath = $scope->administrativeUnit?->ancestry_path;
                        if (!empty($unitPath)) {
                            $paths[] = rtrim($unitPath, '/') . '/';
                        }
                    }
                }
            } catch (Throwable) {
                // Ignore if table not yet migrated
            }
        }

        $unique = array_values(array_unique(array_filter($paths)));

        return !empty($unique) ? $unique : ['/'];
    }

    /**
     * Scope user directory queries to the actor's hierarchical administrative jurisdiction.
     */
    public function scopeInJurisdiction(Builder $query, User $actor): Builder
    {
        if ($actor->isNationalScope()) {
            return $query;
        }

        $paths = $actor->getAccessibleAncestryPaths();
        if (in_array('/', $paths, true)) {
            return $query;
        }

        $adminTable = (new AdministrativeUnit())->getTable();
        $userTable = $this->getTable();
        $scopesTable = (new UserAdministrativeScope())->getTable();

        return $query->where(function (Builder $outer) use ($paths, $adminTable, $userTable, $scopesTable) {
            $outer->whereExists(function ($subQuery) use ($paths, $adminTable, $userTable) {
                $subQuery->selectRaw('1')
                    ->from($adminTable)
                    ->whereColumn("{$adminTable}.id", "{$userTable}.administrative_unit_id")
                    ->where(function ($pathQuery) use ($paths, $adminTable) {
                        foreach ($paths as $p) {
                            $pathQuery->orWhere("{$adminTable}.ancestry_path", 'like', $p . '%');
                        }
                    });
            });

            if ($this->scopesTableAvailable()) {
                $now = now();
                $outer->orWhereExists(function ($subQuery) use ($paths, $adminTable, $userTable, $scopesTable, $now) {
                    $subQuery->selectRaw('1')
                        ->from($scopesTable)
                        ->join($adminTable, "{$adminTable}.id", '=', "{$scopesTable}.administrative_unit_id")
                        ->whereColumn("{$scopesTable}.user_id", "{$userTable}.id")
                        ->where("{$scopesTable}.is_active", true)
                        ->where(function ($q) use ($scopesTable, $now) {
                            $q->whereNull("{$scopesTable}.valid_from")
                                ->orWhere("{$scopesTable}.valid_from", '<=', $now);
                        })
                        ->where(function ($q) use ($scopesTable, $now) {
                            $q->whereNull("{$scopesTable}.valid_until")
                                ->orWhere("{$scopesTable}.valid_until", '>=', $now);
                        })
                        ->where(function ($pathQuery) use ($paths, $adminTable) {
                            foreach ($paths as $p) {
                                $pathQuery->orWhere("{$adminTable}.ancestry_path", 'like', $p . '%');
                            }
                        });
                });
            }
        });
    }

    protected function rolesTableAvailable(): bool
    {
        $table = $this->getConnection()->getDriverName() === 'pgsql' ? 'core.roles' : 'roles';
        return Schema::connection($this->getConnectionName())->hasTable($table);
    }

    protected function scopesTableAvailable(): bool
    {
        $table = $this->getConnection()->getDriverName() === 'pgsql'
            ? 'core.user_administrative_scopes'
            : 'user_administrative_scopes';
        return Schema::connection($this->getConnectionName())->hasTable($table);
    }
}
