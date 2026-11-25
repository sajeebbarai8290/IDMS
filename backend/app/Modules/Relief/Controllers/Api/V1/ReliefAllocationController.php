<?php

namespace App\Modules\Relief\Controllers\Api\V1;

use App\Modules\Relief\Models\ReliefAllocation;
use App\Modules\Relief\Requests\AssignAllocationBeneficiariesRequest;
use App\Modules\Relief\Requests\StoreReliefAllocationRequest;
use App\Modules\Relief\Resources\ReliefAllocationResource;
use App\Modules\Relief\Services\ReliefManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class ReliefAllocationController extends Controller
{
    public function __construct(
        protected ReliefManagementService $reliefService
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAnyAllocations', ReliefAllocation::class);

        $query = ReliefAllocation::inJurisdiction($request->user())
            ->with(['disasterEvent', 'administrativeUnit', 'warehouse', 'distributionPoint', 'items.commodity', 'requisition'])
            ->withCount('beneficiaries');

        if ($status = $request->query('status')) {
            $query->where('status', strtoupper((string) $status));
        }
        if ($disasterId = $request->query('disaster_event_id')) {
            $query->where('disaster_event_id', $disasterId);
        }
        if ($search = trim((string) $request->query('search', ''))) {
            $query->where('allocation_order_no', 'like', "%{$search}%");
        }

        $perPage = min(100, max(5, (int) $request->query('per_page', 15)));
        $allocations = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => ReliefAllocationResource::collection($allocations->items()),
            'pagination' => [
                'current_page' => $allocations->currentPage(),
                'per_page' => $allocations->perPage(),
                'total' => $allocations->total(),
                'last_page' => $allocations->lastPage(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function store(StoreReliefAllocationRequest $request): JsonResponse
    {
        Gate::authorize('createAllocation', ReliefAllocation::class);

        $allocation = $this->reliefService->createAllocation(
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'data' => new ReliefAllocationResource($allocation),
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function show(ReliefAllocation $allocation): JsonResponse
    {
        $allocation->loadMissing('administrativeUnit');
        Gate::authorize('viewAllocation', $allocation);

        $allocation->load([
            'requisition',
            'disasterEvent',
            'administrativeUnit',
            'distributionPoint',
            'warehouse',
            'items.commodity.reliefCategory',
            'beneficiaries.household',
        ]);

        return response()->json([
            'success' => true,
            'data' => new ReliefAllocationResource($allocation),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function assignBeneficiaries(AssignAllocationBeneficiariesRequest $request, ReliefAllocation $allocation): JsonResponse
    {
        $allocation->loadMissing('administrativeUnit');
        Gate::authorize('updateAllocation', $allocation);

        $updated = $this->reliefService->assignBeneficiaries(
            $allocation,
            $request->validated('household_ids'),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'data' => new ReliefAllocationResource($updated->load(['items.commodity', 'beneficiaries.household'])),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function approve(Request $request, ReliefAllocation $allocation): JsonResponse
    {
        $allocation->loadMissing('administrativeUnit');
        Gate::authorize('approveAllocation', $allocation);

        $sanctioned = $this->reliefService->approveAllocation($allocation, $request->user());

        return response()->json([
            'success' => true,
            'data' => new ReliefAllocationResource($sanctioned),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function dispatchOrder(Request $request, ReliefAllocation $allocation): JsonResponse
    {
        $allocation->loadMissing('administrativeUnit');
        Gate::authorize('dispatchAllocation', $allocation);

        $dispatched = $this->reliefService->dispatchAllocation($allocation, $request->user());

        return response()->json([
            'success' => true,
            'data' => new ReliefAllocationResource($dispatched),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function cancel(Request $request, ReliefAllocation $allocation): JsonResponse
    {
        $allocation->loadMissing('administrativeUnit');
        Gate::authorize('approveAllocation', $allocation);

        $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']]);
        $cancelled = $this->reliefService->cancelAllocation(
            $allocation,
            $request->user(),
            $request->string('reason')->toString()
        );

        return response()->json([
            'success' => true,
            'data' => new ReliefAllocationResource($cancelled),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
