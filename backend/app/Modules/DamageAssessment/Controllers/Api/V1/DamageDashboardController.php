<?php

namespace App\Modules\DamageAssessment\Controllers\Api\V1;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\AuditLog;
use App\Modules\DamageAssessment\Models\DamageAssessment;
use App\Modules\DamageAssessment\Models\DamageCategory;
use App\Modules\DamageAssessment\Policies\DamageAssessmentPolicy;
use App\Modules\DamageAssessment\Requests\StoreDamageCategoryRequest;
use App\Modules\DamageAssessment\Resources\DamageCategoryResource;
use App\Modules\DamageAssessment\Services\DamageAssessmentService;
use App\Modules\DamageAssessment\Services\DamageSpatialService;
use App\Modules\Disaster\Models\DisasterEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DamageDashboardController extends Controller
{
    public function __construct(
        protected DamageAssessmentService $damageService,
        protected DamageSpatialService $spatialService,
        protected DamageAssessmentPolicy $policy
    ) {}

    public function summary(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', DamageAssessment::class);

        $filters = $request->only([
            'disaster_event_id',
            'administrative_unit_id',
            'verification_status',
            'assessment_type',
            'severity_level',
            'category_id',
            'date_from',
            'date_to',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->damageService->getDashboardSummary($request->user(), $filters),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function lookups(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', DamageAssessment::class);

        $categories = DamageCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->get();

        $disasters = DisasterEvent::orderByDesc('declaration_date')
            ->get(['id', 'disaster_code', 'name_en', 'name_bn', 'severity_level', 'lifecycle_phase']);

        $adminQuery = AdministrativeUnit::where('is_active', true)
            ->orderBy('ancestry_path')
            ->orderBy('name_en');

        $user = $request->user();
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
                'categories' => DamageCategoryResource::collection($categories),
                'disasters' => $disasters,
                'administrative_units' => $adminUnits,
                'assessment_types' => DamageAssessment::ASSESSMENT_TYPES,
                'verification_statuses' => DamageAssessment::VERIFICATION_STATUSES,
                'severity_levels' => DamageAssessment::SEVERITY_LEVELS,
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function categories(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', DamageAssessment::class);

        $query = DamageCategory::orderBy('sort_order')->orderBy('name_en');
        if ($request->boolean('active_only', false)) {
            $query->where('is_active', true);
        }

        return response()->json([
            'success' => true,
            'data' => DamageCategoryResource::collection($query->get()),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function storeCategory(StoreDamageCategoryRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $category = DamageCategory::create([
            'code' => strtoupper(trim((string) $validated['code'])),
            'name_en' => $validated['name_en'],
            'name_bn' => $validated['name_bn'],
            'description' => $validated['description'] ?? null,
            'default_unit' => $validated['default_unit'] ?? 'UNITS',
            'color_hex' => $validated['color_hex'] ?? '#0d6efd',
            'sort_order' => (int) ($validated['sort_order'] ?? 200),
            'is_system' => false,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        AuditLog::log(
            'DAMAGE_CATEGORY_CREATED',
            DamageCategory::class,
            $category->id,
            null,
            ['code' => $category->code, 'name_en' => $category->name_en],
            $request->user()
        );

        return response()->json([
            'success' => true,
            'data' => new DamageCategoryResource($category),
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function updateCategory(StoreDamageCategoryRequest $request, DamageCategory $category): JsonResponse
    {
        $validated = $request->validated();
        $old = ['name_en' => $category->name_en, 'is_active' => $category->is_active];

        $category->update(collect($validated)->only([
            'name_en',
            'name_bn',
            'description',
            'default_unit',
            'color_hex',
            'sort_order',
            'is_active',
        ])->toArray());

        AuditLog::log(
            'DAMAGE_CATEGORY_UPDATED',
            DamageCategory::class,
            $category->id,
            $old,
            ['name_en' => $category->name_en, 'is_active' => $category->is_active],
            $request->user()
        );

        return response()->json([
            'success' => true,
            'data' => new DamageCategoryResource($category),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function geojson(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', DamageAssessment::class);

        $query = $this->buildScopedSpatialQuery($request);
        $assessments = $query->get();

        return response()->json([
            'success' => true,
            'data' => $this->spatialService->toFeatureCollection($assessments),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function heatmap(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', DamageAssessment::class);

        $query = $this->buildScopedSpatialQuery($request);
        $assessments = $query->get();

        return response()->json([
            'success' => true,
            'data' => [
                'points' => $this->spatialService->buildHeatmapPoints($assessments),
                'area_severity' => $this->spatialService->aggregateByGeographicalArea($assessments),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function spatialAggregation(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', DamageAssessment::class);

        $query = $this->buildScopedSpatialQuery($request);
        $assessments = $query->get();

        return response()->json([
            'success' => true,
            'data' => [
                'areas' => $this->spatialService->aggregateByGeographicalArea($assessments),
                'total_assessments' => $assessments->count(),
                'total_estimated_loss_bdt' => round((float) $assessments->sum('total_estimated_loss_bdt'), 2),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function reports(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', DamageAssessment::class);

        $reportType = strtolower((string) $request->query('type', 'summary'));
        if (!in_array($reportType, ['summary', 'disaster', 'location', 'category', 'financial'], true)) {
            $reportType = 'summary';
        }

        $filters = $request->only([
            'disaster_event_id',
            'administrative_unit_id',
            'category_id',
            'severity_level',
            'verification_status',
            'date_from',
            'date_to',
            'include_unverified',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->damageService->getReports($request->user(), $reportType, $filters),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('exportAssessments', DamageAssessment::class);

        $filters = $request->only([
            'disaster_event_id',
            'administrative_unit_id',
            'verification_status',
            'assessment_type',
            'severity_level',
            'category_id',
            'date_from',
            'date_to',
            'search',
        ]);

        return $this->damageService->exportCsv($request->user(), $filters);
    }

    protected function buildScopedSpatialQuery(Request $request)
    {
        $user = $request->user();
        $query = DamageAssessment::inJurisdiction($user)
            ->with([
                'disasterEvent',
                'administrativeUnit',
                'locations.administrativeUnit',
                'sectorItems.category',
            ]);

        if ($this->policy->isExternalPartnerOnly($user)) {
            $query->whereIn('verification_status', DamageAssessment::REPORTABLE_STATUSES);
        }

        $filters = $request->only([
            'disaster_event_id',
            'administrative_unit_id',
            'verification_status',
            'assessment_type',
            'severity_level',
            'category_id',
            'date_from',
            'date_to',
        ]);
        $this->damageService->applyCommonFilters($query, $filters);

        if ($bbox = $request->query('bbox')) {
            $this->spatialService->applyBoundingBoxFilter($query, (string) $bbox);
        }

        return $query;
    }
}
