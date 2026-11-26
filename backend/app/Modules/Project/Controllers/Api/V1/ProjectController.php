<?php

namespace App\Modules\Project\Controllers\Api\V1;

use App\Modules\Project\Models\Project;
use App\Modules\Project\Requests\StoreProjectBoundaryRequest;
use App\Modules\Project\Requests\StoreProjectRequest;
use App\Modules\Project\Requests\TransitionProjectLifecycleRequest;
use App\Modules\Project\Resources\ProjectResource;
use App\Modules\Project\Services\ProjectManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ProjectController extends Controller
{
    public function __construct(
        protected ProjectManagementService $projectService
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Project::class);

        $query = Project::inJurisdiction($request->user())
            ->with(['category', 'disasterEvent', 'administrativeUnit'])
            ->withCount(['milestones', 'disbursements', 'monitoringReports', 'risks']);

        if ($status = $request->query('status')) {
            $query->where('status', strtoupper((string) $status));
        }
        if ($categoryId = $request->query('category_id')) {
            $query->where('category_id', $categoryId);
        }
        if ($disasterId = $request->query('disaster_event_id')) {
            $query->where('disaster_event_id', $disasterId);
        }
        if ($request->has('is_delayed') && $request->query('is_delayed') !== '') {
            $query->where('is_delayed', filter_var($request->query('is_delayed'), FILTER_VALIDATE_BOOLEAN));
        }
        if ($search = trim((string) $request->query('search', ''))) {
            $likeOp = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $likeOp) {
                $q->where('project_code', $likeOp, "%{$search}%")
                    ->orWhere('reference_no', $likeOp, "%{$search}%")
                    ->orWhere('title_en', $likeOp, "%{$search}%")
                    ->orWhere('title_bn', $likeOp, "%{$search}%")
                    ->orWhere('implementing_organization', $likeOp, "%{$search}%")
                    ->orWhere('funding_source', $likeOp, "%{$search}%");
            });
        }

        $sortBy = in_array($request->query('sort_by'), ['created_at', 'approved_budget_bdt', 'physical_progress_pct', 'financial_progress_pct', 'start_date', 'expected_end_date'], true)
            ? $request->query('sort_by')
            : 'created_at';
        $sortDir = strtolower((string) $request->query('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $perPage = min(100, max(5, (int) $request->query('per_page', 15)));
        $projects = $query->orderBy($sortBy, $sortDir)->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => ProjectResource::collection($projects->items()),
            'pagination' => [
                'current_page' => $projects->currentPage(),
                'per_page' => $projects->perPage(),
                'total' => $projects->total(),
                'last_page' => $projects->lastPage(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = $this->projectService->createProject($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => new ProjectResource($project),
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function show(Project $project): JsonResponse
    {
        Gate::authorize('viewProject', $project);

        $project->load([
            'category',
            'disasterEvent',
            'administrativeUnit',
            'locations.administrativeUnit',
            'milestones.certifier',
            'disbursements.milestone',
            'disbursements.creator',
            'disbursements.approver',
            'monitoringReports.administrativeUnit',
            'monitoringReports.inspector',
            'risks',
        ]);

        return response()->json([
            'success' => true,
            'data' => new ProjectResource($project),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function update(StoreProjectRequest $request, Project $project): JsonResponse
    {
        $updated = $this->projectService->updateProject($project, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => new ProjectResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function destroy(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('updateProject', $project);

        $this->projectService->deleteProject($project, $request->user());

        return response()->json([
            'success' => true,
            'data' => ['id' => $project->id, 'deleted' => true],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function transitionLifecycle(TransitionProjectLifecycleRequest $request, Project $project): JsonResponse
    {
        $updated = $this->projectService->transitionLifecycle(
            $project,
            $request->validated('status'),
            $request->user(),
            $request->validated('notes'),
            $request->validated('delay_reason')
        );

        return response()->json([
            'success' => true,
            'data' => new ProjectResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function uploadBoundary(StoreProjectBoundaryRequest $request, Project $project): JsonResponse
    {
        $updated = $this->projectService->uploadBoundary(
            $project,
            $request->validated('boundary_geojson'),
            $request->validated('latitude') !== null ? (float) $request->validated('latitude') : null,
            $request->validated('longitude') !== null ? (float) $request->validated('longitude') : null,
            $request->user()
        );

        return response()->json([
            'success' => true,
            'data' => new ProjectResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
