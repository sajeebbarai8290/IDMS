<?php

namespace App\Modules\Project\Services;

use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectLocation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProjectSpatialService
{
    /**
     * Normalize Polygon, MultiPolygon, Feature, FeatureCollection, or GeometryCollection
     * into a single Polygon or MultiPolygon GeoJSON geometry object.
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
     * Synchronize PostGIS spatial columns on PostgreSQL for a project.
     */
    public function syncProjectGeometry(Project $project): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $table = $project->getTable();

        if ($project->latitude !== null && $project->longitude !== null) {
            DB::statement(
                "UPDATE {$table} SET site_geom = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?",
                [(float) $project->longitude, (float) $project->latitude, $project->id]
            );
        } else {
            DB::statement("UPDATE {$table} SET site_geom = NULL WHERE id = ?", [$project->id]);
        }

        $normalized = $this->normalizeToPolygonGeometry($project->boundary_geojson);
        if ($normalized) {
            DB::statement(
                "UPDATE {$table} SET boundary_geom = ST_Multi(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)) WHERE id = ?",
                [json_encode($normalized), $project->id]
            );
        } else {
            DB::statement("UPDATE {$table} SET boundary_geom = NULL WHERE id = ?", [$project->id]);
        }
    }

    /**
     * Synchronize PostGIS location_geom for a project location point.
     */
    public function syncLocationGeometry(ProjectLocation $location): void
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
        } else {
            DB::statement("UPDATE {$table} SET location_geom = NULL WHERE id = ?", [$location->id]);
        }
    }

    /**
     * Apply bounding box filter (?bbox=minLng,minLat,maxLng,maxLat).
     */
    public function applyBoundingBoxFilter(Builder $query, string $bbox): Builder
    {
        $parts = array_map('trim', explode(',', $bbox));
        if (count($parts) !== 4) {
            return $query;
        }

        [$minLng, $minLat, $maxLng, $maxLat] = array_map('floatval', $parts);

        if (DB::getDriverName() === 'pgsql') {
            return $query->whereRaw(
                'site_geom IS NOT NULL AND ST_Within(site_geom, ST_MakeEnvelope(?, ?, ?, ?, 4326))',
                [$minLng, $minLat, $maxLng, $maxLat]
            );
        }

        return $query
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('longitude', [$minLng, $maxLng])
            ->whereBetween('latitude', [$minLat, $maxLat]);
    }

    /**
     * Build RFC 7946 GeoJSON FeatureCollection for projects and project locations.
     */
    public function toFeatureCollection(Collection $projects): array
    {
        $features = [];

        foreach ($projects as $project) {
            $baseProps = [
                'id' => $project->id,
                'project_code' => $project->project_code,
                'reference_no' => $project->reference_no,
                'title_en' => $project->title_en,
                'title_bn' => $project->title_bn,
                'category_code' => $project->category?->code,
                'category_name_en' => $project->category?->name_en,
                'category_name_bn' => $project->category?->name_bn,
                'color_hex' => $project->category?->color_hex ?? '#0d6efd',
                'status' => $project->status,
                'is_delayed' => (bool) $project->is_delayed,
                'physical_progress_pct' => (float) $project->physical_progress_pct,
                'financial_progress_pct' => (float) $project->financial_progress_pct,
                'approved_budget_bdt' => (float) $project->approved_budget_bdt,
                'administrative_unit' => $project->administrativeUnit?->name_en,
                'administrative_unit_bn' => $project->administrativeUnit?->name_bn,
            ];

            if (!empty($project->boundary_geojson) && is_array($project->boundary_geojson)) {
                $geom = $this->normalizeToPolygonGeometry($project->boundary_geojson);
                if ($geom) {
                    $features[] = [
                        'type' => 'Feature',
                        'geometry' => $geom,
                        'properties' => array_merge($baseProps, [
                            'feature_type' => 'project_boundary',
                        ]),
                    ];
                }
            }

            if ($project->latitude !== null && $project->longitude !== null) {
                $features[] = [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [(float) $project->longitude, (float) $project->latitude],
                    ],
                    'properties' => array_merge($baseProps, [
                        'feature_type' => 'project_site',
                    ]),
                ];
            }

            foreach ($project->locations ?? [] as $loc) {
                if ($loc->latitude !== null && $loc->longitude !== null) {
                    $features[] = [
                        'type' => 'Feature',
                        'geometry' => [
                            'type' => 'Point',
                            'coordinates' => [(float) $loc->longitude, (float) $loc->latitude],
                        ],
                        'properties' => array_merge($baseProps, [
                            'feature_type' => 'project_location',
                            'location_id' => $loc->id,
                            'site_name_en' => $loc->site_name_en,
                            'site_name_bn' => $loc->site_name_bn,
                            'ward_no' => $loc->ward_no,
                            'location_unit' => $loc->administrativeUnit?->name_en,
                        ]),
                    ];
                }
            }
        }

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];
    }
}
