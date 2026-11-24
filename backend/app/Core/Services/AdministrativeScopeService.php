<?php

namespace App\Core\Services;

use App\Core\Models\User;

/**
 * Class AdministrativeScopeService
 *
 * Enforces the 7-Tier Sovereign Administrative Hierarchy Scoping Invariants.
 * Implements Downward Visibility (Subtree Inclusion) and Lateral Isolation.
 *
 * @package App\Core\Services
 */
class AdministrativeScopeService
{
    /**
     * Determine if an officer has authorization to access records within a target administrative unit.
     *
     * Invariants:
     * 1. National tier roles have universal read visibility across all units.
     * 2. An officer at tier N has visibility if target unit is within officer's subtree (target starts with officer path).
     * 3. Peer units at the same level have zero lateral access to each other's data.
     *
     * @param User $user
     * @param string $targetAncestryPath E.g. '/40/47/12/35/'
     * @return bool
     */
    public function canAccessScope(User $user, string $targetAncestryPath): bool
    {
        // Invariant 1: National roles bypass geographic filtering
        if ($user->isNationalScope()) {
            return true;
        }

        $scope = $user->getAdministrativeScope();
        $officerPath = $scope['ancestry_path'] ?? '/';

        // Root path covers all
        if ($officerPath === '/' || $officerPath === '') {
            return true;
        }

        // Normalize paths with trailing slashes
        $officerPathNormalized = rtrim($officerPath, '/') . '/';
        $targetPathNormalized = rtrim($targetAncestryPath, '/') . '/';

        // Invariant 2: Subtree inclusion (Target path must start with officer path)
        return str_starts_with($targetPathNormalized, $officerPathNormalized);
    }

    /**
     * Check if two administrative paths are peer nodes with lateral isolation.
     *
     * @param string $pathA
     * @param string $pathB
     * @return bool
     */
    public function arePeersIsolated(string $pathA, string $pathB): bool
    {
        $normA = rtrim($pathA, '/') . '/';
        $normB = rtrim($pathB, '/') . '/';

        if ($normA === $normB) {
            return false;
        }

        // Neither path is a parent of the other
        return !str_starts_with($normA, $normB) && !str_starts_with($normB, $normA);
    }
}
