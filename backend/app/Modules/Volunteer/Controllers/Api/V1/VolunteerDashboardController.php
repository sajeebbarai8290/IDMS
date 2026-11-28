<?php

namespace App\Modules\Volunteer\Controllers\Api\V1;

use App\Core\Models\AdministrativeUnit;
use App\Modules\Disaster\Models\DisasterEvent;
use App\Modules\Response\Models\ResponseOperation;
use App\Modules\Volunteer\Models\SkillCatalog;
use App\Modules\Volunteer\Models\Volunteer;
use App\Modules\Volunteer\Models\VolunteerCategory;
use App\Modules\Volunteer\Models\VolunteerOrganization;
use App\Modules\Volunteer\Policies\VolunteerPolicy;
use App\Modules\Volunteer\Resources\SkillCatalogResource;
use App\Modules\Volunteer\Resources\VolunteerCategoryResource;
use App\Modules\Volunteer\Services\VolunteerManagementService;
use App\Modules\Volunteer\Services\VolunteerSpatialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VolunteerDashboardController extends Controller
{
    public function __construct(
        protected VolunteerManagementService $volunteerService,
        protected VolunteerSpatialService $spatialService,
        protected VolunteerPolicy $policy
    ) {}

    public function summary(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Volunteer::class);

        $filters = $request->only([
            'administrative_unit_id',
            'organization_id',
            'primary_category_id',
            'skill_id',
            'overall_verification_status',
            'availability_status',
            'disaster_event_id',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->volunteerService->getDashboardSummary($request->user(), $filters),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function lookups(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Volunteer::class);
        $user = $request->user();

        $categories = VolunteerCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->get();

        $skills = SkillCatalog::with('category')
            ->where('is_active', true)
            ->orderBy('name_en')
            ->get();

        $organizations = VolunteerOrganization::inJurisdiction($user)
            ->where('is_active', true)
            ->orderBy('name_en')
            ->get(['id', 'org_code', 'name_en', 'name_bn', 'org_type', 'verification_status', 'administrative_unit_id']);

        $disasters = DisasterEvent::orderByDesc('declaration_date')
            ->limit(50)
            ->get(['id', 'disaster_code', 'name_en', 'name_bn', 'severity_level', 'lifecycle_phase']);

        $operations = ResponseOperation::inJurisdiction($user)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'operation_no', 'title_en', 'title_bn', 'status', 'disaster_event_id', 'administrative_unit_id']);

        $adminQuery = AdministrativeUnit::where('is_active', true)
            ->orderBy('ancestry_path')
            ->orderBy('name_en');

        if (!$user->isNationalScope()) {
            $path = rtrim($user->getAdministrativeScope()['ancestry_path'] ?? '/', '/') . '/';
            if ($path !== '/') {
                $adminQuery->where('ancestry_path', 'like', $path . '%');
            }
        }

        $adminUnits = $adminQuery->get([
            'id',
            'parent_id',
            'code',
            'name_en',
            'name_bn',
            'tier_level',
            'ancestry_path',
            'latitude',
            'longitude',
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'categories' => VolunteerCategoryResource::collection($categories),
                'skills' => SkillCatalogResource::collection($skills),
                'organizations' => $organizations,
                'disasters' => $disasters,
                'response_operations' => $operations,
                'administrative_units' => $adminUnits,
                'org_types' => VolunteerOrganization::ORG_TYPES,
                'genders' => Volunteer::GENDERS,
                'availability_statuses' => Volunteer::AVAILABILITY_STATUSES,
                'verification_statuses' => Volunteer::OVERALL_VERIFICATION_STATUSES,
                'pillar_statuses' => Volunteer::PILLAR_STATUSES,
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function geojson(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Volunteer::class);

        $filters = $request->only([
            'organization_id',
            'availability_status',
            'verification_status',
            'disaster_event_id',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->spatialService->buildGeoJson($request->user(), $filters),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function reports(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Volunteer::class);

        $filters = $request->only([
            'administrative_unit_id',
            'organization_id',
            'primary_category_id',
            'overall_verification_status',
            'availability_status',
            'disaster_event_id',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->volunteerService->getReports($request->user(), $filters),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('export', Volunteer::class);

        $reportType = strtolower((string) $request->query('report_type', 'register'));
        $filters = $request->only([
            'administrative_unit_id',
            'organization_id',
            'primary_category_id',
            'overall_verification_status',
            'availability_status',
            'disaster_event_id',
        ]);

        return $this->volunteerService->exportCsv($request->user(), $reportType, $filters);
    }
}
