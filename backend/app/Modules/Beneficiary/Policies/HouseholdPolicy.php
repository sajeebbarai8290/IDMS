<?php

namespace App\Modules\Beneficiary\Policies;

use App\Core\Models\User;
use App\Core\Services\AdministrativeScopeService;
use App\Modules\Beneficiary\Models\Household;

/**
 * Class HouseholdPolicy
 *
 * Enforces 7-tier hierarchical data scoping and gated PII decryption rules.
 *
 * @package App\Modules\Beneficiary\Policies
 */
class HouseholdPolicy
{
    public function __construct(
        protected AdministrativeScopeService $scopeService
    ) {}

    /**
     * Determine whether the user can view any households.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view a specific household.
     */
    public function view(User $user, Household $household): bool
    {
        if ($user->isNationalScope()) {
            return true;
        }

        $ancestryPath = $household->administrativeUnit?->ancestry_path ?? '/';
        return $this->scopeService->canAccessScope($user, $ancestryPath);
    }

    /**
     * Determine whether the user can create households.
     */
    public function create(User $user): bool
    {
        return in_array($user->role_code, [
            'ROLE_SUPER_ADMIN',
            'ROLE_MINISTRY_EXECUTIVE',
            'ROLE_DDM_DIRECTOR_GENERAL',
            'ROLE_DRRO',
            'ROLE_UNO',
            'ROLE_PIO',
            'ROLE_UP_CHAIRMAN',
            'ROLE_UP_SECRETARY',
            'ROLE_FIELD_ENUMERATOR',
        ], true) || $user->hasPermission('beneficiary.household.create');
    }

    /**
     * Determine whether the user can update the household.
     */
    public function update(User $user, Household $household): bool
    {
        if (!$this->view($user, $household)) {
            return false;
        }

        return in_array($user->role_code, [
            'ROLE_SUPER_ADMIN',
            'ROLE_DRRO',
            'ROLE_PIO',
            'ROLE_UP_CHAIRMAN',
            'ROLE_UP_SECRETARY',
            'ROLE_FIELD_ENUMERATOR',
        ], true) || $user->hasPermission('beneficiary.household.update');
    }

    /**
     * Determine whether the user can delete the household.
     */
    public function delete(User $user, Household $household): bool
    {
        if (!$this->view($user, $household)) {
            return false;
        }

        // Only PIO, DRRO, and Super Admin can soft-delete master records
        return in_array($user->role_code, [
            'ROLE_SUPER_ADMIN',
            'ROLE_DRRO',
            'ROLE_PIO',
        ], true) || $user->hasPermission('beneficiary.household.delete');
    }

    /**
     * Determine whether the user can decrypt and view cleartext citizen PII.
     */
    public function decryptPii(User $user, Household $household): bool
    {
        if (!$this->view($user, $household)) {
            return false;
        }

        // PII decryption is gated by explicit permission or authorized executive roles
        return in_array($user->role_code, [
            'ROLE_SUPER_ADMIN',
            'ROLE_MINISTRY_EXECUTIVE',
            'ROLE_DDM_DIRECTOR_GENERAL',
            'ROLE_DRRO',
            'ROLE_UNO',
            'ROLE_PIO',
            'ROLE_NATIONAL_AUDITOR',
        ], true) || $user->hasPermission('beneficiary.pii.decrypt');
    }

    /**
     * Determine whether the user can override duplicate suspect flags.
     */
    public function overrideDuplicate(User $user, Household $household): bool
    {
        if (!$this->view($user, $household)) {
            return false;
        }

        return in_array($user->role_code, [
            'ROLE_SUPER_ADMIN',
            'ROLE_DRRO',
            'ROLE_PIO',
        ], true) || $user->hasPermission('beneficiary.dedup.override');
    }
}
