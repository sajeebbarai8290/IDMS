<?php

namespace App\Modules\DamageAssessment\Services;

use App\Modules\DamageAssessment\Models\DamageAssessment;
use App\Modules\DamageAssessment\Models\DamageLocation;
use App\Modules\DamageAssessment\Models\DamageSectorItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DamageSpatialService
{
    /**
     * Normalize Polygon, MultiPolygon, Feature, FeatureCollection, or GeometryCollection
     * into a clean GeoJSON Polygon or MultiPolygon object.
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
     * Sync PostGIS Point and MultiPolygon columns on PostgreSQL for an assessment.
     */
    public function syncAssessmentGeometry(DamageAssessment $assessment): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $table = $assessment->getTable();

        if ($assessment->latitude !== null && $assessment->longitude !== null) {
            DB::statement(
                "UPDATE {$table} SET location_geom = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?",
                [(float) $assessment->longitude, (float) $assessment->latitude, $assessment->id]
            );
        } else {
            DB::statement("UPDATE {$table} SET location_geom = NULL WHERE id = ?", [$assessment->id]);
        }

        $normalized = $this->normalizeToPolygonGeometry($assessment->boundary_geojson);
        if ($normalized) {
            DB::statement(
                "UPDATE {$table} SET damage_area_geom = ST_Multi(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)) WHERE id = ?",
                [json_encode($normalized), $assessment->id]
            );
        } else {
            DB::statement("UPDATE {$table} SET damage_area_geom = NULL WHERE id = ?", [$assessment->id]);
        }
    }

    /**
     * Sync PostGIS location_geom for a damage coverage location.
     */
    public function syncLocationGeometry(DamageLocation $location): void
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
     * Sync PostGIS location_geom for a sectoral damage item.
     */
    public function syncSectorItemGeometry(DamageSectorItem $item): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $table = $item->getTable();

        if ($item->latitude !== null && $item->longitude !== null) {
            DB::statement(
                "UPDATE {$table} SET location_geom = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?",
                [(float) $item->longitude, (float) $item->latitude, $item->id]
            );
        } else {
            DB::statement("UPDATE {$table} SET location_geom = NULL WHERE id = ?", [$item->id]);
        }
    }

    /**
     * Apply spatial bounding box filter (?bbox=minLng,minLat,maxLng,maxLat).
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
                'location_geom IS NOT NULL AND ST_Within(location_geom, ST_MakeEnvelope(?, ?, ?, ?, 4326))',
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
     * Build RFC 7946 GeoJSON FeatureCollection for damaged infrastructure, assessment areas,
     * and geo-tagged sector items.
     */
    public function toFeatureCollection(Collection $assessments): array
    {
        $features = [];

        foreach ($assessments as $assessment) {
            $baseProps = [
                'id' => $assessment->id,
                'd_form_code' => $assessment->d_form_code,
                'assessment_type' => $assessment->assessment_type,
                'assessment_date' => $assessment->assessment_date?->toDateString(),
                'verification_status' => $assessment->verification_status,
                'severity_level' => $assessment->severity_level,
                'severity_color' => $this->severityColor($assessment->severity_level),
                'disaster_code' => $assessment->disasterEvent?->disaster_code,
                'disaster_name_en' => $assessment->disasterEvent?->name_en,
                'disaster_name_bn' => $assessment->disasterEvent?->name_bn,
                'administrative_unit_id' => $assessment->administrative_unit_id,
                'administrative_unit' => $assessment->administrativeUnit?->name_en,
                'administrative_unit_bn' => $assessment->administrativeUnit?->name_bn,
                'affected_households' => (int) $assessment->affected_households,
                'affected_population' => (int) $assessment->affected_population,
                'total_fully_damaged' => (int) $assessment->total_fully_damaged,
                'total_partially_damaged' => (int) $assessment->total_partially_damaged,
                'total_minor_damaged' => (int) $assessment->total_minor_damaged,
                'total_estimated_loss_bdt' => (float) $assessment->total_estimated_loss_bdt,
            ];

            if (!empty($assessment->boundary_geojson) && is_array($assessment->boundary_geojson)) {
                $geom = $this->normalizeToPolygonGeometry($assessment->boundary_geojson);
                if ($geom) {
                    $features[] = [
                        'type' => 'Feature',
                        'geometry' => $geom,
                        'properties' => array_merge($baseProps, [
                            'feature_type' => 'damage_area',
                        ]),
                    ];
                }
            }

            if ($assessment->latitude !== null && $assessment->longitude !== null) {
                $features[] = [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [(float) $assessment->longitude, (float) $assessment->latitude],
                    ],
                    'properties' => array_merge($baseProps, [
                        'feature_type' => 'damage_assessment_point',
                        'location_summary' => $assessment->location_summary,
                    ]),
                ];
            }

            foreach ($assessment->locations ?? [] as $loc) {
                if ($loc->latitude !== null && $loc->longitude !== null) {
                    $features[] = [
                        'type' => 'Feature',
                        'geometry' => [
                            'type' => 'Point',
                            'coordinates' => [(float) $loc->longitude, (float) $loc->latitude],
                        ],
                        'properties' => array_merge($baseProps, [
                            'feature_type' => 'damage_coverage_location',
                            'location_id' => $loc->id,
                            'locality_name_en' => $loc->locality_name_en,
                            'locality_name_bn' => $loc->locality_name_bn,
                            'ward_no' => $loc->ward_no,
                            'location_unit' => $loc->administrativeUnit?->name_en,
                            'location_unit_bn' => $loc->administrativeUnit?->name_bn,
                            'severity_level' => $loc->severity_level,
                            'severity_color' => $this->severityColor($loc->severity_level),
                            'affected_households' => (int) $loc->affected_households,
                            'affected_population' => (int) $loc->affected_population,
                        ]),
                    ];
                }
            }

            foreach ($assessment->sectorItems ?? [] as $item) {
                if ($item->latitude !== null && $item->longitude !== null) {
                    $features[] = [
                        'type' => 'Feature',
                        'geometry' => [
                            'type' => 'Point',
                            'coordinates' => [(float) $item->longitude, (float) $item->latitude],
                        ],
                        'properties' => array_merge($baseProps, [
                            'feature_type' => 'damaged_infrastructure_item',
                            'sector_item_id' => $item->id,
                            'sector_code' => $item->sector_code,
                            'category_name_en' => $item->category?->name_en,
                            'category_name_bn' => $item->category?->name_bn,
                            'item_title' => $item->item_title,
                            'fully_damaged_count' => (int) $item->fully_damaged_count,
                            'partially_damaged_count' => (int) $item->partially_damaged_count,
                            'minor_damaged_count' => (int) $item->minor_damaged_count,
                            'estimated_loss_bdt' => (float) $item->estimated_loss_bdt,
                            'category_color' => $item->category?->color_hex ?? '#dc3545',
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

    /**
     * Build weighted damage heatmap intensity points from assessments, locations, and infrastructure items.
     */
    public function buildHeatmapPoints(Collection $assessments): array
    {
        $maxLoss = max(1.0, (float) $assessments->max('total_estimated_loss_bdt'));
        $points = [];

        foreach ($assessments as $assessment) {
            $baseWeight = $this->computeIntensityWeight(
                $assessment->severity_level,
                (float) $assessment->total_estimated_loss_bdt,
                (int) $assessment->affected_households,
                $maxLoss
            );

            $lat = $assessment->latitude !== null
                ? (float) $assessment->latitude
                : ($assessment->administrativeUnit?->latitude !== null ? (float) $assessment->administrativeUnit->latitude : null);
            $lng = $assessment->longitude !== null
                ? (float) $assessment->longitude
                : ($assessment->administrativeUnit?->longitude !== null ? (float) $assessment->administrativeUnit->longitude : null);

            if ($lat !== null && $lng !== null) {
                $points[] = [
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'intensity' => $baseWeight,
                    'severity_level' => $assessment->severity_level,
                    'd_form_code' => $assessment->d_form_code,
                    'administrative_unit' => $assessment->administrativeUnit?->name_en,
                    'estimated_loss_bdt' => (float) $assessment->total_estimated_loss_bdt,
                    'affected_households' => (int) $assessment->affected_households,
                ];
            }

            foreach ($assessment->locations ?? [] as $loc) {
                $locLat = $loc->latitude !== null
                    ? (float) $loc->latitude
                    : ($loc->administrativeUnit?->latitude !== null ? (float) $loc->administrativeUnit->latitude : null);
                $locLng = $loc->longitude !== null
                    ? (float) $loc->longitude
                    : ($loc->administrativeUnit?->longitude !== null ? (float) $loc->administrativeUnit->longitude : null);

                if ($locLat !== null && $locLng !== null) {
                    $points[] = [
                        'latitude' => $locLat,
                        'longitude' => $locLng,
                        'intensity' => $this->computeIntensityWeight(
                            $loc->severity_level ?: $assessment->severity_level,
                            (float) $assessment->total_estimated_loss_bdt * 0.5,
                            (int) $loc->affected_households,
                            $maxLoss
                        ),
                        'severity_level' => $loc->severity_level ?: $assessment->severity_level,
                        'd_form_code' => $assessment->d_form_code,
                        'administrative_unit' => $loc->administrativeUnit?->name_en ?? $assessment->administrativeUnit?->name_en,
                        'estimated_loss_bdt' => (float) $assessment->total_estimated_loss_bdt,
                        'affected_households' => (int) $loc->affected_households,
                    ];
                }
            }
        }

        return $points;
    }

    /**
     * Aggregate damage severity, affected households, and spatial footprint by administrative area.
     */
    public function aggregateByGeographicalArea(Collection $assessments): array
    {
        $areas = [];
        $severityRank = [
            DamageAssessment::SEVERITY_LOW => 1,
            DamageAssessment::SEVERITY_MODERATE => 2,
            DamageAssessment::SEVERITY_SEVERE => 3,
            DamageAssessment::SEVERITY_CRITICAL => 4,
        ];

        foreach ($assessments as $assessment) {
            $unit = $assessment->administrativeUnit;
            if (!$unit) {
                continue;
            }

            $key = $unit->id;
            if (!isset($areas[$key])) {
                $areas[$key] = [
                    'administrative_unit_id' => $unit->id,
                    'code' => $unit->code,
                    'name_en' => $unit->name_en,
                    'name_bn' => $unit->name_bn,
                    'tier_level' => $unit->tier_level,
                    'ancestry_path' => $unit->ancestry_path,
                    'latitude' => $unit->latitude !== null ? (float) $unit->latitude : ($assessment->latitude !== null ? (float) $assessment->latitude : null),
                    'longitude' => $unit->longitude !== null ? (float) $unit->longitude : ($assessment->longitude !== null ? (float) $assessment->longitude : null),
                    'assessment_count' => 0,
                    'verified_count' => 0,
                    'affected_households' => 0,
                    'affected_population' => 0,
                    'total_fully_damaged' => 0,
                    'total_partially_damaged' => 0,
                    'total_minor_damaged' => 0,
                    'total_estimated_loss_bdt' => 0.0,
                    'max_severity' => DamageAssessment::SEVERITY_LOW,
                    '_rank' => 1,
                ];
            }

            $areas[$key]['assessment_count']++;
            if (in_array($assessment->verification_status, DamageAssessment::REPORTABLE_STATUSES, true)) {
                $areas[$key]['verified_count']++;
            }
            $areas[$key]['affected_households'] += (int) $assessment->affected_households;
            $areas[$key]['affected_population'] += (int) $assessment->affected_population;
            $areas[$key]['total_fully_damaged'] += (int) $assessment->total_fully_damaged;
            $areas[$key]['total_partially_damaged'] += (int) $assessment->total_partially_damaged;
            $areas[$key]['total_minor_damaged'] += (int) $assessment->total_minor_damaged;
            $areas[$key]['total_estimated_loss_bdt'] = round(
                $areas[$key]['total_estimated_loss_bdt'] + (float) $assessment->total_estimated_loss_bdt,
                2
            );

            $rank = $severityRank[$assessment->severity_level] ?? 1;
            if ($rank > $areas[$key]['_rank']) {
                $areas[$key]['_rank'] = $rank;
                $areas[$key]['max_severity'] = $assessment->severity_level;
            }
        }

        $result = array_values(array_map(function ($row) {
            unset($row['_rank']);
            $row['severity_color'] = $this->severityColor($row['max_severity']);
            return $row;
        }, $areas));

        usort($result, fn ($a, $b) => $b['total_estimated_loss_bdt'] <=> $a['total_estimated_loss_bdt']);

        return $result;
    }

    public function computeIntensityWeight(
        string $severity,
        float $lossBdt,
        int $affectedHouseholds,
        float $maxLossBdt = 1000000.0
    ): float {
        $severityBase = match (strtoupper($severity)) {
            DamageAssessment::SEVERITY_CRITICAL => 0.85,
            DamageAssessment::SEVERITY_SEVERE => 0.65,
            DamageAssessment::SEVERITY_MODERATE => 0.45,
            default => 0.25,
        };

        $lossRatio = min(1.0, max(0.0, $lossBdt / max(1.0, $maxLossBdt)));
        $householdBoost = min(0.15, ($affectedHouseholds / 1000.0) * 0.15);

        return round(min(1.0, max(0.15, ($severityBase * 0.65) + ($lossRatio * 0.25) + $householdBoost)), 3);
    }

    public function severityColor(?string $severity): string
    {
        return match (strtoupper((string) $severity)) {
            DamageAssessment::SEVERITY_CRITICAL => '#b02a37',
            DamageAssessment::SEVERITY_SEVERE => '#dc3545',
            DamageAssessment::SEVERITY_MODERATE => '#fd7e14',
            default => '#198754',
        };
    }
}
