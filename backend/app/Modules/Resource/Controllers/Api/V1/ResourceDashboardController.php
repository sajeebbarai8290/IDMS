<?php

namespace App\Modules\Resource\Controllers\Api\V1;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\AuditLog;
use App\Core\Models\User;
use App\Modules\Disaster\Models\DisasterEvent;
use App\Modules\Resource\Models\ResourceAsset;
use App\Modules\Resource\Models\ResourceCategory;
use App\Modules\Resource\Requests\StoreResourceCategoryRequest;
use App\Modules\Resource\Resources\ResourceCategoryResource;
use App\Modules\Resource\Services\ResourceManagementService;
use App\Modules\Resource\Services\ResourceSpatialService;
use App\Modules\Response\Models\ResponseOperation;
use App\Modules\Response\Models\ResponseTeam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResourceDashboardController extends Controller
{
    public function __construct(
        protected ResourceManagementService $resourceService,
        protected ResourceSpatialService $spatialService
    ) {}

    public function summary(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ResourceAsset::class);

        $filters = $request->only(['category_id', 'disaster_event_id']);

        return response()->json([
            'success' => true,
            'data' => $this->resourceService->getDashboardAnalytics($request->user(), $filters),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function lookups(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ResourceAsset::class);
        $user = $request->user();
        $isExternal = $user->hasRole('ROLE_EXTERNAL_PARTNER');

        $categories = ResourceCategory::where('is_active', true)->orderBy('name_en')->get();

        $disasters = DisasterEvent::orderByDesc('declaration_date')
            ->limit(50)
            ->get(['id', 'disaster_code', 'name_en', 'name_bn', 'severity_level', 'lifecycle_phase']);

        $operations = ResponseOperation::inJurisdiction($user)
            ->where('status', '!=', ResponseOperation::STATUS_CLOSED)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'operation_no', 'title_en', 'title_bn', 'disaster_event_id', 'administrative_unit_id', 'status']);

        $teams = ResponseTeam::inJurisdiction($user)
            ->orderBy('team_name')
            ->limit(60)
            ->get(['id', 'team_code', 'team_name', 'agency_name', 'team_type', 'administrative_unit_id', 'status']);

        $adminQuery = AdministrativeUnit::where('is_active', true)->orderBy('ancestry_path')->orderBy('name_en');
        if (!$user->isNationalScope()) {
            $path = rtrim($user->getAdministrativeScope()['ancestry_path'] ?? '/', '/') . '/';
            if ($path !== '/') {
                $adminQuery->where('ancestry_path', 'like', $path . '%');
            }
        }
        $adminUnits = $adminQuery->get(['id', 'parent_id', 'code', 'name_en', 'name_bn', 'tier_level', 'ancestry_path', 'latitude', 'longitude']);

        $personnel = collect();
        if (!$isExternal) {
            $personnelQuery = User::where('is_active', true)->orderBy('full_name_en');
            if (!$user->isNationalScope()) {
                $scopedUnitIds = $adminUnits->pluck('id')->all();
                if (!empty($scopedUnitIds)) {
                    $personnelQuery->whereIn('administrative_unit_id', $scopedUnitIds);
                }
            }
            $personnel = $personnelQuery
                ->limit(80)
                ->get(['id', 'full_name_en', 'full_name_bn', 'designation', 'role_code', 'administrative_unit_id']);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'categories' => ResourceCategoryResource::collection($categories),
                'disasters' => $disasters,
                'response_operations' => $operations,
                'response_teams' => $teams,
                'administrative_units' => $adminUnits,
                'personnel_users' => $personnel,
                'statuses' => ResourceAsset::PRIMARY_STATUSES,
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function geojson(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ResourceAsset::class);

        $filters = $request->only(['category_id', 'status', 'disaster_event_id', 'bbox']);

        return response()->json([
            'success' => true,
            'data' => $this->spatialService->buildGeoJson($request->user(), $filters),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function reminders(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ResourceAsset::class);

        $horizon = (int) $request->query('days', 30);

        return response()->json([
            'success' => true,
            'data' => $this->resourceService->getMaintenanceReminders($request->user(), $horizon),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('exportResources', ResourceAsset::class);

        return $this->resourceService->exportCsv(
            $request->user(),
            $request->only(['category_id', 'status'])
        );
    }

    public function categories(): JsonResponse
    {
        Gate::authorize('viewAny', ResourceAsset::class);

        $categories = ResourceCategory::where('is_active', true)->orderBy('name_en')->get();

        return response()->json([
            'success' => true,
            'data' => ResourceCategoryResource::collection($categories),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function storeCategory(StoreResourceCategoryRequest $request): JsonResponse
    {
        $category = ResourceCategory::create(array_merge(
            $request->validated(),
            [
                'code' => strtoupper($request->validated('code')),
                'is_human_resource' => (bool) ($request->validated('is_human_resource') ?? false),
                'is_system' => false,
                'is_active' => true,
            ]
        ));

        AuditLog::log(
            'RESOURCE_CATEGORY_CREATED',
            ResourceCategory::class,
            $category->id,
            null,
            ['code' => $category->code],
            $request->user()
        );

        return response()->json([
            'success' => true,
            'data' => new ResourceCategoryResource($category),
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }
}
