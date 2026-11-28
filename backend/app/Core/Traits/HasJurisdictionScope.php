<?php

namespace App\Core\Traits;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait HasJurisdictionScope
{
    /**
     * Filter records to the user's administrative hierarchy jurisdiction (subtree).
     */
    public function scopeInJurisdiction(
        Builder $query,
        User $user,
        string $adminUnitColumn = 'administrative_unit_id'
    ): Builder {
        if ($user->isNationalScope()) {
            return $query;
        }

        $paths = $user->getAccessibleAncestryPaths();
        if (in_array('/', $paths, true)) {
            return $query;
        }

        $adminTable = (new AdministrativeUnit())->getTable();
        $modelTable = $this->getTable();

        return $query->whereExists(function ($subQuery) use ($adminTable, $modelTable, $adminUnitColumn, $paths) {
            $subQuery->selectRaw('1')
                ->from($adminTable)
                ->whereColumn("{$adminTable}.id", "{$modelTable}.{$adminUnitColumn}")
                ->where(function ($pathQuery) use ($paths, $adminTable) {
                    foreach ($paths as $p) {
                        $normPath = rtrim($p, '/') . '/';
                        $pathQuery->orWhere("{$adminTable}.ancestry_path", 'like', $normPath . '%');
                    }
                });
        });
    }
}
