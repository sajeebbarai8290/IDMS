<?php

namespace App\Modules\Warehouse\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Relief\Models\ReliefAllocation;
use App\Modules\Relief\Resources\ReliefAllocationResource;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Warehouse\Requests\IssueReliefAllocationStockRequest;
use App\Modules\Warehouse\Services\WarehouseManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class WarehouseReliefIntegrationController extends Controller
{
    public function __construct(
        protected WarehouseManagementService $warehouseService
    ) {}

    public function allocations(Request $request): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('viewAnyStock', Warehouse::class);

        $perPage = max(1, min(100, (int) $request->query('per_page', 15)));
        $warehouseIds = Warehouse::query()
            ->supplyingJurisdiction($request->user())
            ->pluck('id');

        $query = ReliefAllocation::query()
            ->whereIn('warehouse_id', $warehouseIds)
            ->whereIn('status', [
                ReliefAllocation::STATUS_SANCTIONED,
                ReliefAllocation::STATUS_DISPATCHED,
                ReliefAllocation::STATUS_COMPLETED,
            ])
            ->with(['disasterEvent', 'administrativeUnit', 'warehouse', 'items.commodity'])
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', $request->query('warehouse_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->latest('updated_at');

        return ReliefAllocationResource::collection($query->paginate($perPage));
    }

    public function issueStock(IssueReliefAllocationStockRequest $request, string $allocationId): JsonResponse
    {
        $allocation = ReliefAllocation::with(['warehouse.administrativeUnit', 'items.commodity'])->findOrFail($allocationId);

        $updated = $this->warehouseService->issueAgainstReliefAllocation(
            $allocation,
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Stock issued against approved relief allocation and recorded in warehouse ledger.',
            'data' => new ReliefAllocationResource($updated),
        ]);
    }
}
