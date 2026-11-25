<?php

namespace App\Modules\Response\Services;

use App\Core\Models\User;
use App\Modules\Disaster\Models\DisasterEvent;
use App\Modules\Response\Models\ResponseCenter;
use App\Modules\Response\Models\ResponseOperation;
use App\Modules\Response\Models\ResponseSituationReport;
use App\Modules\Response\Models\ResponseTeam;

class ResponseSpatialService
{
    protected const EXACT_GPS_ROLES = [
        'ROLE_SUPER_ADMIN',
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_NEOC_DIRECTOR',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DDM_RELIEF_DIRECTOR',
        'ROLE_DIVISIONAL_COMMISSIONER',
        'ROLE_DEPUTY_COMMISSIONER',
        'ROLE_DRRO',
        'ROLE_UNO',
        'ROLE_PIO',
        'ROLE_UP_CHAIRMAN',
        'ROLE_UP_SECRETARY',
        'ROLE_FIELD_ENUMERATOR',
        'ROLE_TAG_OFFICER',
        'ROLE_SHELTER_MANAGER',
        'ROLE_CPP_TEAM_LEADER',
        'ROLE_VOLUNTEER',
    ];

    protected const RESTRICTED_EXACT_COMMANDER_ROLES = [
        'ROLE_MINISTRY_EXECUTIVE',
        'ROLE_NEOC_DIRECTOR',
        'ROLE_DDM_DIRECTOR_GENERAL',
        'ROLE_DDM_RELIEF_DIRECTOR',
        'ROLE_DIVISIONAL_COMMISSIONER',
        'ROLE_DEPUTY_COMMISSIONER',
        'ROLE_DRRO',
        'ROLE_UNO',
        'ROLE_PIO',
    ];

    /**
     * Determine whether the authenticated user can view exact unmasked incident/team coordinates.
     */
    public function canViewExactCoordinates(User $user, string $privacyLevel = ResponseSituationReport::PRIVACY_PUBLIC_AREA): bool
    {
        if ($user->hasRole('ROLE_EXTERNAL_PARTNER')) {
            return false;
        }

        if ($privacyLevel === ResponseSituationReport::PRIVACY_RESTRICTED_EXACT) {
            foreach (self::RESTRICTED_EXACT_COMMANDER_ROLES as $role) {
                if ($user->hasRole($role)) {
                    return true;
                }
            }
            return false;
        }

        if ($user->hasPermission('response.gis.view_exact')) {
            return true;
        }

        foreach (self::EXACT_GPS_ROLES as $role) {
            if ($user->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generalize coordinates (~1.1 km / 2 decimals) when location privacy applies.
     */
    public function maskCoordinatesIfNeeded(
        ?float $lat,
        ?float $lng,
        User $user,
        string $privacyLevel = ResponseSituationReport::PRIVACY_PUBLIC_AREA
    ): array {
        if ($lat === null || $lng === null) {
            return ['latitude' => null, 'longitude' => null, 'location_masked' => false];
        }

        if ($this->canViewExactCoordinates($user, $privacyLevel)) {
            return [
                'latitude' => round($lat, 7),
                'longitude' => round($lng, 7),
                'location_masked' => false,
            ];
        }

        return [
            'latitude' => round($lat, 2),
            'longitude' => round($lng, 2),
            'location_masked' => true,
        ];
    }

    /**
     * Build scoped RFC 7946 GeoJSON FeatureCollection covering disaster affected areas,
     * response centers (EOCs), deployed teams, and situation/incident reports.
     */
    public function buildResponseGeoJson(User $user, array $filters = []): array
    {
        $operationId = $filters['response_operation_id'] ?? null;
        $disasterId = $filters['disaster_event_id'] ?? null;
        $features = [];

        // 1. Linked Disaster affected zones & epicenters
        $operations = ResponseOperation::inJurisdiction($user)
            ->with(['disasterEvent', 'administrativeUnit', 'responseCenter'])
            ->when($operationId, fn ($q) => $q->where('id', $operationId))
            ->when($disasterId, fn ($q) => $q->where('disaster_event_id', $disasterId))
            ->get();

        $seenDisasters = [];
        foreach ($operations as $op) {
            $event = $op->disasterEvent;
            if ($event && !isset($seenDisasters[$event->id])) {
                $seenDisasters[$event->id] = true;
                if ($event->affected_zone_geojson) {
                    $features[] = [
                        'type' => 'Feature',
                        'geometry' => $event->affected_zone_geojson,
                        'properties' => [
                            'feature_type' => 'affected_area',
                            'disaster_event_id' => $event->id,
                            'title_en' => $event->name_en,
                            'title_bn' => $event->name_bn,
                            'severity_level' => $event->severity_level,
                        ],
                    ];
                } elseif ($event->epicenter_lat && $event->epicenter_lng) {
                    $features[] = [
                        'type' => 'Feature',
                        'geometry' => [
                            'type' => 'Point',
                            'coordinates' => [(float) $event->epicenter_lng, (float) $event->epicenter_lat],
                        ],
                        'properties' => [
                            'feature_type' => 'affected_area',
                            'disaster_event_id' => $event->id,
                            'title_en' => $event->name_en,
                            'title_bn' => $event->name_bn,
                            'severity_level' => $event->severity_level,
                        ],
                    ];
                }
            }
        }

        // 2. Active Response Centers (EOCs) - operational public facility locations
        $centers = ResponseCenter::inJurisdiction($user)
            ->where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();

        foreach ($centers as $center) {
            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [(float) $center->longitude, (float) $center->latitude],
                ],
                'properties' => [
                    'feature_type' => 'response_center',
                    'id' => $center->id,
                    'code' => $center->center_code,
                    'center_type' => $center->center_type,
                    'title_en' => $center->name_en,
                    'title_bn' => $center->name_bn,
                    'contact_phone' => $user->hasRole('ROLE_EXTERNAL_PARTNER') ? null : $center->contact_phone,
                    'location_masked' => false,
                ],
            ];
        }

        // 3. Participating Response Teams (privacy-aware)
        $teams = ResponseTeam::inJurisdiction($user)
            ->with('operation')
            ->when($operationId, fn ($q) => $q->where('response_operation_id', $operationId))
            ->when($disasterId, fn ($q) => $q->whereHas('operation', fn ($oq) => $oq->where('disaster_event_id', $disasterId)))
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();

        foreach ($teams as $team) {
            $coords = $this->maskCoordinatesIfNeeded(
                (float) $team->latitude,
                (float) $team->longitude,
                $user,
                ResponseSituationReport::PRIVACY_PUBLIC_AREA
            );

            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [$coords['longitude'], $coords['latitude']],
                ],
                'properties' => [
                    'feature_type' => 'response_team',
                    'id' => $team->id,
                    'code' => $team->team_code,
                    'title_en' => $team->team_name,
                    'title_bn' => $team->team_name,
                    'team_type' => $team->team_type,
                    'agency_name' => $team->agency_name,
                    'status' => $team->status,
                    'member_count' => $team->member_count,
                    'operation_no' => $team->operation?->operation_no,
                    'location_masked' => $coords['location_masked'],
                ],
            ];
        }

        // 4. Incident & Situation Reports (privacy-aware)
        $reports = ResponseSituationReport::inJurisdiction($user)
            ->with('operation')
            ->when($operationId, fn ($q) => $q->where('response_operation_id', $operationId))
            ->when($disasterId, fn ($q) => $q->where('disaster_event_id', $disasterId))
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();

        foreach ($reports as $rep) {
            $coords = $this->maskCoordinatesIfNeeded(
                (float) $rep->latitude,
                (float) $rep->longitude,
                $user,
                $rep->privacy_level ?? ResponseSituationReport::PRIVACY_PUBLIC_AREA
            );

            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [$coords['longitude'], $coords['latitude']],
                ],
                'properties' => [
                    'feature_type' => 'situation_report',
                    'id' => $rep->id,
                    'code' => $rep->report_no,
                    'report_type' => $rep->report_type,
                    'severity_level' => $rep->severity_level,
                    'title_en' => $rep->title_en,
                    'title_bn' => $rep->title_bn ?: $rep->title_en,
                    'rescued_count' => $rep->rescued_count,
                    'casualties_injured' => $rep->casualties_injured,
                    'location_description' => $coords['location_masked']
                        ? 'Generalized operational area (location privacy active)'
                        : $rep->location_description,
                    'location_masked' => $coords['location_masked'],
                    'reported_at' => $rep->reported_at?->toIso8601String(),
                ],
            ];
        }

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
            'metadata' => [
                'exact_gps_authorized' => $this->canViewExactCoordinates($user),
                'total_features' => count($features),
            ],
        ];
    }
}
