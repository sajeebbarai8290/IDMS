<?php

namespace App\Modules\Resource\Services;

use App\Core\Models\User;
use App\Modules\Resource\Models\ResourceAllocation;
use App\Modules\Resource\Models\ResourceAsset;
use Illuminate\Support\Facades\DB;

class ResourceSpatialService
{
    public function syncAssetGeometry(ResourceAsset $asset): void
    {
        $this->syncPointGeometry($asset);
    }

    public function syncAllocationGeometry(ResourceAllocation $allocation): void
    {
        $this->syncPointGeometry($allocation);
    }

    private function syncPointGeometry(ResourceAsset|ResourceAllocation $model): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $table = $model->getTable();
        if ($model->latitude !== null && $model->longitude !== null) {
            DB::statement(
                "UPDATE {$table} SET location_geom = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?",
                [(float) $model->longitude, (float) $model->latitude, $model->id]
            );
        } else {
            DB::statement("UPDATE {$table} SET location_geom = NULL WHERE id = ?", [$model->id]);
        }
    }

    /**
     * Build scoped RFC 7946 GeoJSON FeatureCollection for resources and active allocations.
     */
    public function buildGeoJson(User $user, array $filters = []): array
    {
        $isExternal = $user->hasRole('ROLE_EXTERNAL_PARTNER');

        $assetsQuery = ResourceAsset::inJurisdiction($user)
            ->with(['category', 'administrativeUnit'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        if (!empty($filters['category_id'])) {
            $assetsQuery->where('category_id', $filters['category_id']);
        }
        if (!empty($filters['status'])) {
            $assetsQuery->whereIn('status', ResourceAsset::statusesForFilter((string) $filters['status']));
        }
        if (!empty($filters['bbox'])) {
            $this->applyBboxFilter($assetsQuery, (string) $filters['bbox']);
        }

        $features = [];
        foreach ($assetsQuery->get() as $asset) {
            $lat = $isExternal ? round((float) $asset->latitude, 2) : (float) $asset->latitude;
            $lng = $isExternal ? round((float) $asset->longitude, 2) : (float) $asset->longitude;

            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [$lng, $lat],
                ],
                'properties' => [
                    'feature_type' => 'resource_asset',
                    'id' => $asset->id,
                    'asset_code' => $asset->asset_code,
                    'name_en' => $asset->name_en,
                    'name_bn' => $asset->name_bn,
                    'category_code' => $asset->category?->code,
                    'category_name_en' => $asset->category?->name_en,
                    'category_name_bn' => $asset->category?->name_bn,
                    'status' => $asset->status,
                    'owning_organization' => $asset->owning_organization,
                    'current_location' => $asset->current_location,
                    'unit_name_en' => $asset->administrativeUnit?->name_en,
                    'unit_name_bn' => $asset->administrativeUnit?->name_bn,
                    'location_masked' => $isExternal,
                ],
            ];
        }

        $allocQuery = ResourceAllocation::inJurisdiction($user)
            ->with(['asset', 'disasterEvent', 'administrativeUnit'])
            ->whereIn('status', ResourceAllocation::ACTIVE_STATUSES)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        if (!empty($filters['disaster_event_id'])) {
            $allocQuery->where('disaster_event_id', $filters['disaster_event_id']);
        }

        foreach ($allocQuery->get() as $alloc) {
            $lat = $isExternal ? round((float) $alloc->latitude, 2) : (float) $alloc->latitude;
            $lng = $isExternal ? round((float) $alloc->longitude, 2) : (float) $alloc->longitude;

            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [$lng, $lat],
                ],
                'properties' => [
                    'feature_type' => 'active_allocation',
                    'id' => $alloc->id,
                    'allocation_no' => $alloc->allocation_no,
                    'resource_asset_id' => $alloc->resource_asset_id,
                    'asset_code' => $alloc->asset?->asset_code,
                    'asset_name_en' => $alloc->asset?->name_en,
                    'asset_name_bn' => $alloc->asset?->name_bn,
                    'allocation_location' => $alloc->allocation_location,
                    'disaster_code' => $alloc->disasterEvent?->disaster_code,
                    'status' => $alloc->status,
                    'location_masked' => $isExternal,
                ],
            ];
        }

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];
    }

    protected function applyBboxFilter($query, string $bbox): void
    {
        $parts = array_map('trim', explode(',', $bbox));
        if (count($parts) !== 4) {
            return;
        }
        [$minLng, $minLat, $maxLng, $maxLat] = array_map('floatval', $parts);
        $query->whereBetween('longitude', [$minLng, $maxLng])
            ->whereBetween('latitude', [$minLat, $maxLat]);
    }
}
