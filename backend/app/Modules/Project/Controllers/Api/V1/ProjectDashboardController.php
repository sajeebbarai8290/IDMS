<?php

namespace App\Modules\Project\Controllers\Api\V1;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\AuditLog;
use App\Modules\Disaster\Models\DisasterEvent;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectCategory;
use App\Modules\Project\Requests\StoreProjectCategoryRequest;
use App\Modules\Project\Resources\ProjectCategoryResource;
use App\Modules\Project\Services\ProjectManagementService;
use App\Modules\Project\Services\ProjectSpatialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectDashboardController extends Controller
{
    public function __construct(
        protected ProjectManagementService $projectService,
        protected ProjectSpatialService $spatialService
    ) {}

    public function summary(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Project::class);

        $filters = $request->only(['category_id', 'disaster_event_id', 'status']);
        $data = $this->projectService->getDashboardSummary($request->user(), $filters);

        return response()->json([
            'success' => true,
            'data' => $data,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function lookups(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Project::class);

        $categories = ProjectCategory::where('is_active', true)->orderBy('name_en')->get();
        $disasters = DisasterEvent::orderByDesc('declaration_date')
            ->get(['id', 'disaster_code', 'name_en', 'name_bn', 'severity_level', 'lifecycle_phase']);

        $adminQuery = AdministrativeUnit::where('is_active', true)->orderBy('ancestry_path')->orderBy('name_en');
        $user = $request->user();
        if (!$user->isNationalScope()) {
            $path = rtrim($user->getAdministrativeScope()['ancestry_path'] ?? '/', '/') . '/';
            if ($path !== '/') {
                $adminQuery->where('ancestry_path', 'like', $path . '%');
            }
        }
        $adminUnits = $adminQuery->get(['id', 'parent_id', 'code', 'name_en', 'name_bn', 'tier_level', 'ancestry_path', 'latitude', 'longitude']);

        return response()->json([
            'success' => true,
            'data' => [
                'categories' => ProjectCategoryResource::collection($categories),
                'disasters' => $disasters,
                'administrative_units' => $adminUnits,
                'lifecycle_statuses' => Project::LIFECYCLE_STATUSES,
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function geojson(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Project::class);

        $query = Project::inJurisdiction($request->user())
            ->with(['category', 'administrativeUnit', 'locations.administrativeUnit']);

        if ($status = $request->query('status')) {
            $query->where('status', strtoupper((string) $status));
        }
        if ($categoryId = $request->query('category_id')) {
            $query->where('category_id', $categoryId);
        }
        if ($bbox = $request->query('bbox')) {
            $this->spatialService->applyBoundingBoxFilter($query, (string) $bbox);
        }

        $projects = $query->get();

        return response()->json([
            'success' => true,
            'data' => $this->spatialService->toFeatureCollection($projects),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function reports(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Project::class);

        $reportType = strtolower((string) $request->query('type', 'summary'));
        if (!in_array($reportType, ['summary', 'financial', 'physical', 'milestones', 'monitoring'], true)) {
            $reportType = 'summary';
        }

        $filters = $request->only(['category_id', 'status', 'is_delayed']);
        $data = $this->projectService->getReports($request->user(), $reportType, $filters);

        return response()->json([
            'success' => true,
            'data' => $data,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('exportProjects', Project::class);

        $filters = $request->only(['status', 'category_id']);
        return $this->projectService->exportCsv($request->user(), $filters);
    }

    public function storeCategory(StoreProjectCategoryRequest $request): JsonResponse
    {
        $category = ProjectCategory::create(array_merge(
            $request->validated(),
            [
                'code' => strtoupper($request->validated('code')),
                'is_system' => false,
                'is_active' => true,
            ]
        ));

        AuditLog::log(
            'PROJECT_CATEGORY_CREATED',
            ProjectCategory::class,
            $category->id,
            null,
            ['code' => $category->code],
            $request->user()
        );

        return response()->json([
            'success' => true,
            'data' => new ProjectCategoryResource($category),
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }
}
