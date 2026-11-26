<?php

namespace App\Modules\Resource\Controllers\Api\V1;

use App\Modules\Resource\Models\ResourceAllocation;
use App\Modules\Resource\Models\ResourceAsset;
use App\Modules\Resource\Requests\StoreResourceAllocationRequest;
use App\Modules\Resource\Resources\ResourceAllocationResource;
use App\Modules\Resource\Services\ResourceManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ResourceAllocationController extends Controller
{
    public function __construct(
        protected ResourceManagementService $resourceService
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ResourceAsset::class);

        $query = ResourceAllocation::inJurisdiction($request->user())
            ->with(['asset.category', 'disasterEvent', 'responseOperation', 'administrativeUnit']);

        if ($status = $request->query('status')) {
            $query->where('status', strtoupper((string) $status));
        }
        if ($disasterId = $request->query('disaster_event_id')) {
            $query->where('disaster_event_id', $disasterId);
        }
        if ($opId = $request->query('response_operation_id')) {
            $query->where('response_operation_id', $opId);
        }
        if ($assetId = $request->query('resource_asset_id')) {
            $query->where('resource_asset_id', $assetId);
        }
        if ($search = trim((string) $request->query('search', ''))) {
            $likeOp = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $likeOp) {
                $q->where('allocation_no', $likeOp, "%{$search}%")
                    ->orWhere('allocation_location', $likeOp, "%{$search}%")
                    ->orWhere('responsible_officer_name', $likeOp, "%{$search}%");
            });
        }

        $perPage = min(100, max(5, (int) $request->query('per_page', 15)));
        $allocations = $query->orderByDesc('assignment_date')->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => ResourceAllocationResource::collection($allocations->items()),
            'pagination' => [
                'current_page' => $allocations->currentPage(),
                'per_page' => $allocations->perPage(),
                'total' => $allocations->total(),
                'last_page' => $allocations->lastPage(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function store(StoreResourceAllocationRequest $request): JsonResponse
    {
        $allocation = $this->resourceService->allocateResource($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => new ResourceAllocationResource($allocation),
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function markInUse(Request $request, ResourceAllocation $allocation): JsonResponse
    {
        Gate::authorize('manageAllocation', $allocation);

        $validated = $request->validate([
            'utilization_notes' => ['nullable', 'string', 'max:1500'],
        ]);

        $updated = $this->resourceService->markAllocationInUse(
            $allocation,
            $request->user(),
            $validated['utilization_notes'] ?? null
        );

        return response()->json([
            'success' => true,
            'data' => new ResourceAllocationResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function release(Request $request, ResourceAllocation $allocation): JsonResponse
    {
        Gate::authorize('manageAllocation', $allocation);

        $validated = $request->validate([
            'actual_return_date' => ['nullable', 'date'],
            'return_condition' => ['nullable', 'string', Rule::in(['GOOD', 'MAINTENANCE_REQUIRED', 'DAMAGED'])],
            'return_condition_notes' => ['nullable', 'string', 'max:1500'],
            'return_location' => ['nullable', 'string', 'max:255'],
            'cancel' => ['sometimes', 'boolean'],
        ]);

        $updated = $this->resourceService->releaseResource($allocation, $validated, $request->user());

        return response()->json([
            'success' => true,
            'data' => new ResourceAllocationResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
