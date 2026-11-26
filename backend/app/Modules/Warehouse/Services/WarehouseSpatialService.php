<?php

namespace App\Modules\Warehouse\Services;

use App\Core\Models\User;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class WarehouseSpatialService
{
    public function syncLocationGeometry(Warehouse $warehouse): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        if ($warehouse->latitude === null || $warehouse->longitude === null) {
            DB::statement(
                'UPDATE logistics.warehouses SET location_geom = NULL WHERE id = ?',
                [$warehouse->id]
            );

            return;
        }

        DB::statement(
            'UPDATE logistics.warehouses SET location_geom = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?',
            [(float) $warehouse->longitude, (float) $warehouse->latitude, $warehouse->id]
        );
    }

    /**
     * Build GeoJSON FeatureCollection of warehouses in user jurisdiction with stock metrics.
     */
    public function buildGeoJson(User $user, array $filters = []): array
    {
        $query = Warehouse::query()
            ->supplyingJurisdiction($user)
            ->with(['administrativeUnit', 'inventories'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        if (! empty($filters['tier'])) {
            $query->where('tier', $filters['tier']);
        }

        if (! empty($filters['warehouse_type'])) {
            $query->where('warehouse_type', $filters['warehouse_type']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        $warehouses = $query->orderBy('warehouse_name')->get();

        $features = $warehouses->map(function (Warehouse $wh) {
            $currentTotal = round((float) $wh->inventories->sum('current_quantity'), 2);
            $reservedTotal = round((float) $wh->inventories->sum('reserved_quantity'), 2);
            $availableTotal = max(0.0, round($currentTotal - $reservedTotal, 2));

            return [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [
                        (float) $wh->longitude,
                        (float) $wh->latitude,
                    ],
                ],
                'properties' => [
                    'id' => $wh->id,
                    'warehouse_code' => $wh->warehouse_code,
                    'warehouse_name' => $wh->warehouse_name,
                    'warehouse_name_bn' => $wh->warehouse_name_bn,
                    'tier' => $wh->tier,
                    'warehouse_type' => $wh->warehouse_type,
                    'owning_organization' => $wh->owning_organization,
                    'capacity_mt' => (float) $wh->capacity_mt,
                    'is_active' => (bool) $wh->is_active,
                    'administrative_unit_name_en' => $wh->administrativeUnit?->name_en,
                    'administrative_unit_name_bn' => $wh->administrativeUnit?->name_bn,
                    'total_current_quantity' => $currentTotal,
                    'total_reserved_quantity' => $reservedTotal,
                    'total_available_quantity' => $availableTotal,
                    'active_lot_count' => $wh->inventories->where('current_quantity', '>', 0)->count(),
                ],
            ];
        })->values()->all();

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];
    }
}
