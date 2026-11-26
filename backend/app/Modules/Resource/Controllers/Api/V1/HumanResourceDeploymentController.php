<?php

namespace App\Modules\Resource\Controllers\Api\V1;

use App\Modules\Resource\Models\HumanResourceDeployment;
use App\Modules\Resource\Models\ResourceAsset;
use App\Modules\Resource\Requests\StoreHumanResourceDeploymentRequest;
use App\Modules\Resource\Resources\HumanResourceDeploymentResource;
use App\Modules\Resource\Services\ResourceManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class HumanResourceDeploymentController extends Controller
{
    public function __construct(
        protected ResourceManagementService $resourceService
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ResourceAsset::class);

        $query = HumanResourceDeployment::inJurisdiction($request->user())
            ->with(['asset', 'team', 'responseOperation', 'disasterEvent', 'administrativeUnit']);

        if ($status = $request->query('availability_status')) {
            $query->where('availability_status', strtoupper((string) $status));
        }
        if ($opId = $request->query('response_operation_id')) {
            $query->where('response_operation_id', $opId);
        }
        if ($disasterId = $request->query('disaster_event_id')) {
            $query->where('disaster_event_id', $disasterId);
        }
        if ($search = trim((string) $request->query('search', ''))) {
            $likeOp = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $likeOp) {
                $q->where('deployment_no', $likeOp, "%{$search}%")
                    ->orWhere('personnel_name', $likeOp, "%{$search}%")
                    ->orWhere('assigned_duty', $likeOp, "%{$search}%")
                    ->orWhere('deployment_location', $likeOp, "%{$search}%");
            });
        }

        $perPage = min(100, max(5, (int) $request->query('per_page', 15)));
        $deployments = $query->orderByDesc('deployment_start_date')->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => HumanResourceDeploymentResource::collection($deployments->items()),
            'pagination' => [
                'current_page' => $deployments->currentPage(),
                'per_page' => $deployments->perPage(),
                'total' => $deployments->total(),
                'last_page' => $deployments->lastPage(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function store(StoreHumanResourceDeploymentRequest $request): JsonResponse
    {
        $deployment = $this->resourceService->assignHumanResource($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => new HumanResourceDeploymentResource($deployment),
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function release(Request $request, HumanResourceDeployment $deployment): JsonResponse
    {
        Gate::authorize('manageHumanDeployment', $deployment);

        $validated = $request->validate([
            'actual_return_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1500'],
            'cancel' => ['sometimes', 'boolean'],
        ]);

        $updated = $this->resourceService->releaseHumanResource($deployment, $validated, $request->user());

        return response()->json([
            'success' => true,
            'data' => new HumanResourceDeploymentResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
