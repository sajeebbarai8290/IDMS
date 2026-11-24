<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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

    protected $table = 'core.users';

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
        'email',
        'mobile_number',
        'full_name_en',
        'full_name_bn',
        'role_code',
        'administrative_unit_id',
        'designation',
        'is_active',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'remember_token',
        'deleted_at',
    ];

    /**
     * Dynamic claims hydrated from Keycloak JWT.
     *
     * @var array
     */
    protected array $jwtClaims = [];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Override password handling: IDMS delegates all credentials to Keycloak.
     *
     * @return string
     */
    public function getAuthPassword(): string
    {
        return '';
    }

    /**
     * Hydrate JWT claims for the current request context.
     *
     * @param array $claims
     * @return $this
     */
    public function setJwtClaims(array $claims): self
    {
        $this->jwtClaims = $claims;
        return $this;
    }

    /**
     * Retrieve hydrated JWT claims.
     *
     * @return array
     */
    public function getJwtClaims(): array
    {
        return $this->jwtClaims;
    }

    /**
     * Check if user possesses a given system role.
     *
     * @param string $role
     * @return bool
     */
    public function hasRole(string $role): bool
    {
        if ($this->role_code === $role) {
            return true;
        }

        $roles = $this->jwtClaims['roles'] ?? [];
        return in_array($role, $roles, true);
    }

    /**
     * Check if user possesses a given granular business permission.
     *
     * @param string $permission
     * @return bool
     */
    public function hasPermission(string $permission): bool
    {
        // Super Admin possesses technical administration
        if ($this->hasRole('ROLE_SUPER_ADMIN')) {
            return true;
        }

        $permissions = $this->jwtClaims['permissions'] ?? [];
        return in_array($permission, $permissions, true);
    }

    /**
     * Check if the user's jurisdiction is at the National/Ministry level.
     *
     * @return bool
     */
    public function isNationalScope(): bool
    {
        return in_array($this->role_code, [
            'ROLE_SUPER_ADMIN',
            'ROLE_MINISTRY_EXECUTIVE',
            'ROLE_NEOC_DIRECTOR',
            'ROLE_NATIONAL_AUDITOR',
            'ROLE_DDM_DIRECTOR_GENERAL',
        ], true);
    }

    /**
     * Retrieve user's active administrative scope.
     *
     * @return array
     */
    public function getAdministrativeScope(): array
    {
        return $this->jwtClaims['administrative_profile'] ?? [
            'tier_level' => 'NATIONAL',
            'ancestry_path' => '/',
            'scope_id' => $this->administrative_unit_id,
        ];
    }
}
