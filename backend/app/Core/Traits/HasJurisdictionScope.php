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

        $scope = $user->getAdministrativeScope();
        $officerPath = $scope['ancestry_path'] ?? '/';

        if ($officerPath === '/' || $officerPath === '') {
            return $query;
        }

        $normPath = rtrim($officerPath, '/') . '/';
        $adminTable = (new AdministrativeUnit())->getTable();
        $modelTable = $this->getTable();

        return $query->whereExists(function ($subQuery) use ($adminTable, $modelTable, $adminUnitColumn, $normPath) {
            $subQuery->selectRaw('1')
                ->from($adminTable)
                ->whereColumn("{$adminTable}.id", "{$modelTable}.{$adminUnitColumn}")
                ->where("{$adminTable}.ancestry_path", 'like', $normPath . '%');
        });
    }
}
