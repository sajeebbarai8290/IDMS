<?php

namespace App\Modules\Disaster\Services;

use App\Modules\Disaster\Models\DisasterEvent;
use App\Modules\Disaster\Models\DisasterLocation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Class DisasterSpatialService
 *
 * Handles GeoJSON normalization (for Shapefile/GeoJSON uploads), PostGIS spatial column
 * synchronization, bounding-box & radius spatial queries, and GeoJSON FeatureCollection output.
 */
class DisasterSpatialService
{
    /**
     * Normalize any Polygon, MultiPolygon, Feature, or FeatureCollection GeoJSON
     * into a single Polygon or MultiPolygon Geometry object compatible with PostGIS ST_GeomFromGeoJSON.
     */
    public function normalizeToPolygonGeometry(?array $geojson): ?array
    {
        if (empty($geojson) || !isset($geojson['type'])) {
            return null;
        }

        $polygons = [];
        $this->collectPolygons($geojson, $polygons);

        if (empty($polygons)) {
            return null;
        }

        if (count($polygons) === 1) {
            return [
                'type' => 'Polygon',
                'coordinates' => $polygons[0],
            ];
        }

        return [
            'type' => 'MultiPolygon',
            'coordinates' => $polygons,
        ];
    }

    protected function collectPolygons(array $node, array &$polygons): void
    {
        $type = $node['type'] ?? null;

        if ($type === 'Polygon' && !empty($node['coordinates']) && is_array($node['coordinates'])) {
            $polygons[] = $node['coordinates'];
        } elseif ($type === 'MultiPolygon' && !empty($node['coordinates']) && is_array($node['coordinates'])) {
            foreach ($node['coordinates'] as $polyCoords) {
                if (is_array($polyCoords) && !empty($polyCoords)) {
                    $polygons[] = $polyCoords;
                }
            }
        } elseif ($type === 'Feature' && !empty($node['geometry']) && is_array($node['geometry'])) {
            $this->collectPolygons($node['geometry'], $polygons);
        } elseif ($type === 'FeatureCollection' && !empty($node['features']) && is_array($node['features'])) {
            foreach ($node['features'] as $feature) {
                if (is_array($feature)) {
                    $this->collectPolygons($feature, $polygons);
                }
            }
        } elseif ($type === 'GeometryCollection' && !empty($node['geometries']) && is_array($node['geometries'])) {
            foreach ($node['geometries'] as $geom) {
                if (is_array($geom)) {
                    $this->collectPolygons($geom, $polygons);
                }
            }
        }
    }

    /**
     * Synchronize PostGIS geometry columns on PostgreSQL for a disaster event.
     */
    public function syncEventGeometry(DisasterEvent $event): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $table = $event->getTable();

        if ($event->epicenter_lat !== null && $event->epicenter_lng !== null) {
            DB::statement(
                "UPDATE {$table} SET location_geom = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?",
                [(float) $event->epicenter_lng, (float) $event->epicenter_lat, $event->id]
            );
        } else {
            DB::statement("UPDATE {$table} SET location_geom = NULL WHERE id = ?", [$event->id]);
        }

        $normalized = $this->normalizeToPolygonGeometry($event->affected_zone_geojson);
        if ($normalized) {
            $geojson = json_encode($normalized);
            DB::statement(
                "UPDATE {$table} SET affected_zone_geom = ST_Multi(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)) WHERE id = ?",
                [$geojson, $event->id]
            );
        } else {
            DB::statement("UPDATE {$table} SET affected_zone_geom = NULL WHERE id = ?", [$event->id]);
        }
    }

    /**
     * Synchronize PostGIS point geometry for a disaster location row.
     */
    public function syncLocationGeometry(DisasterLocation $location): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $table = $location->getTable();

        if ($location->latitude !== null && $location->longitude !== null) {
            DB::statement(
                "UPDATE {$table} SET location_geom = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?",
                [(float) $location->longitude, (float) $location->latitude, $location->id]
            );
        }
    }

    /**
     * Apply a bounding-box spatial filter (PostGIS ST_Intersects / ST_Within, with fallback).
     */
    public function applyBoundingBoxFilter(
        Builder $query,
        float $minLng,
        float $minLat,
        float $maxLng,
        float $maxLat
    ): Builder {
        if (DB::getDriverName() === 'pgsql') {
            return $query->where(function (Builder $q) use ($minLng, $minLat, $maxLng, $maxLat) {
                $q->whereRaw(
                    'affected_zone_geom IS NOT NULL AND ST_Intersects(affected_zone_geom, ST_MakeEnvelope(?, ?, ?, ?, 4326))',
                    [$minLng, $minLat, $maxLng, $maxLat]
                )->orWhereRaw(
                    'location_geom IS NOT NULL AND ST_Within(location_geom, ST_MakeEnvelope(?, ?, ?, ?, 4326))',
                    [$minLng, $minLat, $maxLng, $maxLat]
                )->orWhere(function (Builder $fallback) use ($minLng, $minLat, $maxLng, $maxLat) {
                    $fallback->whereBetween('epicenter_lat', [$minLat, $maxLat])
                        ->whereBetween('epicenter_lng', [$minLng, $maxLng]);
                });
            });
        }

        return $query->whereBetween('epicenter_lat', [$minLat, $maxLat])
            ->whereBetween('epicenter_lng', [$minLng, $maxLng]);
    }

    /**
     * Apply a circular radius spatial filter (km) around a coordinate.
     */
    public function applyRadiusFilter(Builder $query, float $lat, float $lng, float $radiusKm): Builder
    {
        $radiusMeters = max(100, $radiusKm * 1000);

        if (DB::getDriverName() === 'pgsql') {
            return $query->whereNotNull('location_geom')
                ->whereRaw(
                    'ST_DWithin(location_geom::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)',
                    [$lng, $lat, $radiusMeters]
                );
        }

        $latDelta = $radiusKm / 111.0;
        $lngDelta = $radiusKm / max(1.0, 111.0 * cos(deg2rad($lat)));

        return $query->whereNotNull('epicenter_lat')
            ->whereNotNull('epicenter_lng')
            ->whereBetween('epicenter_lat', [$lat - $latDelta, $lat + $latDelta])
            ->whereBetween('epicenter_lng', [$lng - $lngDelta, $lng + $lngDelta]);
    }

    /**
     * Convert a collection of DisasterEvent models to an RFC 7946 GeoJSON FeatureCollection.
     */
    public function toFeatureCollection(Collection $events): array
    {
        $features = [];

        foreach ($events as $event) {
            $properties = [
                'id' => $event->id,
                'disaster_code' => $event->disaster_code,
                'name_en' => $event->name_en,
                'name_bn' => $event->name_bn,
                'disaster_type' => $event->disaster_type,
                'subtype' => $event->subtype,
                'severity_level' => $event->severity_level,
                'lifecycle_phase' => $event->lifecycle_phase,
                'declaration_date' => $event->declaration_date?->toIso8601String(),
                'is_emergency_declared' => (bool) $event->is_emergency_declared,
                'affected_population' => (int) ($event->impact?->affected_population ?? 0),
                'affected_households' => (int) ($event->impact?->affected_households ?? 0),
                'deaths_count' => (int) ($event->impact?->deaths_count ?? 0),
                'affected_areas_summary' => $event->affected_areas_summary,
                'locations_count' => $event->locations?->count() ?? 0,
            ];

            $lat = $event->epicenter_lat;
            $lng = $event->epicenter_lng;

            if ($lat === null || $lng === null) {
                $firstLoc = $event->locations?->first(fn ($l) => $l->latitude !== null && $l->longitude !== null)
                    ?? $event->locations?->first(fn ($l) => $l->administrativeUnit?->latitude !== null);
                if ($firstLoc) {
                    $lat = $firstLoc->latitude ?? $firstLoc->administrativeUnit?->latitude;
                    $lng = $firstLoc->longitude ?? $firstLoc->administrativeUnit?->longitude;
                }
            }

            if ($lat !== null && $lng !== null) {
                $features[] = [
                    'type' => 'Feature',
                    'id' => $event->id . '-point',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [(float) $lng, (float) $lat],
                    ],
                    'properties' => array_merge($properties, ['feature_kind' => 'EPICENTER']),
                ];
            }

            if (!empty($event->affected_zone_geojson) && is_array($event->affected_zone_geojson)) {
                $geom = $this->normalizeToPolygonGeometry($event->affected_zone_geojson) ?? $event->affected_zone_geojson;
                $features[] = [
                    'type' => 'Feature',
                    'id' => $event->id . '-zone',
                    'geometry' => $geom,
                    'properties' => array_merge($properties, ['feature_kind' => 'AFFECTED_ZONE']),
                ];
            }
        }

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];
    }
}
