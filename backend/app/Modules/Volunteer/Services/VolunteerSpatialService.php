<?php

namespace App\Modules\Volunteer\Services;

use App\Core\Models\User;
use App\Modules\Volunteer\Models\Volunteer;
use App\Modules\Volunteer\Models\VolunteerDeployment;
use App\Modules\Volunteer\Policies\VolunteerPolicy;
use Illuminate\Support\Facades\DB;

class VolunteerSpatialService
{
    public function __construct(
        protected VolunteerPolicy $policy
    ) {}

    public function syncVolunteerGeometry(Volunteer $volunteer): void
    {
        $this->syncPointGeometry($volunteer);
    }

    public function syncDeploymentGeometry(VolunteerDeployment $deployment): void
    {
        $this->syncPointGeometry($deployment);
    }

    private function syncPointGeometry(Volunteer|VolunteerDeployment $model): void
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

    public function buildGeoJson(User $user, array $filters = []): array
    {
        $canExact = $this->policy->canViewExactLocation($user);
        $isExternal = $this->policy->isExternalPartnerOnly($user);

        $features = [];

        $volQuery = Volunteer::inJurisdiction($user)
            ->with(['administrativeUnit', 'organization', 'primaryCategory'])
            ->where('location_consent_given', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        if (!empty($filters['organization_id'])) {
            $volQuery->where('organization_id', $filters['organization_id']);
        }
        if (!empty($filters['availability_status'])) {
            $volQuery->where('availability_status', strtoupper((string) $filters['availability_status']));
        }
        if (!empty($filters['verification_status'])) {
            $volQuery->where('overall_verification_status', strtoupper((string) $filters['verification_status']));
        }
        if ($isExternal) {
            $volQuery->where('overall_verification_status', Volunteer::VERIFICATION_STATUS_VERIFIED);
        }

        foreach ($volQuery->get() as $vol) {
            $lat = $canExact ? (float) $vol->latitude : round((float) $vol->latitude, 2);
            $lng = $canExact ? (float) $vol->longitude : round((float) $vol->longitude, 2);

            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [$lng, $lat],
                ],
                'properties' => [
                    'feature_type' => 'volunteer_base',
                    'id' => $vol->id,
                    'volunteer_code' => $vol->volunteer_code,
                    'full_name_en' => $isExternal ? 'Verified Volunteer' : $vol->full_name_en,
                    'full_name_bn' => $isExternal ? 'যাচাইকৃত স্বেচ্ছাসেবক' : $vol->full_name_bn,
                    'organization_name' => $vol->organization?->name_en,
                    'category_name_en' => $vol->primaryCategory?->name_en,
                    'category_name_bn' => $vol->primaryCategory?->name_bn,
                    'availability_status' => $vol->availability_status,
                    'overall_verification_status' => $vol->overall_verification_status,
                    'unit_name_en' => $vol->administrativeUnit?->name_en,
                    'unit_name_bn' => $vol->administrativeUnit?->name_bn,
                    'location_masked' => !$canExact,
                ],
            ];
        }

        $depQuery = VolunteerDeployment::inJurisdiction($user)
            ->with(['volunteer', 'disasterEvent', 'administrativeUnit'])
            ->whereIn('deployment_status', VolunteerDeployment::ACTIVE_STATUSES)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        if (!empty($filters['disaster_event_id'])) {
            $depQuery->where('disaster_event_id', $filters['disaster_event_id']);
        }

        foreach ($depQuery->get() as $dep) {
            $lat = $canExact ? (float) $dep->latitude : round((float) $dep->latitude, 2);
            $lng = $canExact ? (float) $dep->longitude : round((float) $dep->longitude, 2);

            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [$lng, $lat],
                ],
                'properties' => [
                    'feature_type' => 'active_deployment',
                    'id' => $dep->id,
                    'deployment_no' => $dep->deployment_no,
                    'volunteer_id' => $dep->volunteer_id,
                    'volunteer_code' => $dep->volunteer?->volunteer_code,
                    'assigned_role' => $dep->assigned_role,
                    'deployment_location' => $isExternal ? $dep->administrativeUnit?->name_en : $dep->deployment_location,
                    'disaster_code' => $dep->disasterEvent?->disaster_code,
                    'deployment_status' => $dep->deployment_status,
                    'unit_name_en' => $dep->administrativeUnit?->name_en,
                    'unit_name_bn' => $dep->administrativeUnit?->name_bn,
                    'location_masked' => !$canExact,
                ],
            ];
        }

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];
    }
}
