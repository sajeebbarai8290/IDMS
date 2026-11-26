<?php

namespace App\Modules\Warehouse\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Warehouse\Requests\StoreWarehouseRequest;
use App\Modules\Warehouse\Requests\UpdateWarehouseRequest;
use App\Modules\Warehouse\Resources\WarehouseResource;
use App\Modules\Warehouse\Services\WarehouseManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class WarehouseFacilityController extends Controller
{
    public function __construct(
        protected WarehouseManagementService $warehouseService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('viewAnyFacilities', Warehouse::class);

        $perPage = max(1, min(100, (int) $request->query('per_page', 15)));
        $search = trim((string) $request->query('search', ''));

        $query = Warehouse::query()
            ->supplyingJurisdiction($request->user())
            ->with(['administrativeUnit', 'inventories'])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('warehouse_code', 'like', "%{$search}%")
                        ->orWhere('warehouse_name', 'like', "%{$search}%")
                        ->orWhere('warehouse_name_bn', 'like', "%{$search}%")
                        ->orWhere('owning_organization', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('tier'), fn ($q) => $q->where('tier', $request->query('tier')))
            ->when($request->filled('warehouse_type'), fn ($q) => $q->where('warehouse_type', $request->query('warehouse_type')))
            ->when($request->has('is_active') && $request->query('is_active') !== '', function ($q) use ($request) {
                $q->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
            })
            ->orderBy('warehouse_name');

        return WarehouseResource::collection($query->paginate($perPage));
    }

    public function store(StoreWarehouseRequest $request): JsonResponse
    {
        $warehouse = $this->warehouseService->createWarehouse($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Warehouse facility registered successfully.',
            'data' => new WarehouseResource($warehouse),
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $warehouse = Warehouse::with(['administrativeUnit', 'inventories.commodity', 'responsibleUser'])
            ->findOrFail($id);

        Gate::forUser($request->user())->authorize('viewFacility', $warehouse);

        return response()->json([
            'success' => true,
            'data' => new WarehouseResource($warehouse),
        ]);
    }

    public function update(UpdateWarehouseRequest $request, string $id): JsonResponse
    {
        $warehouse = Warehouse::with('administrativeUnit')->findOrFail($id);
        Gate::forUser($request->user())->authorize('updateFacility', $warehouse);

        $updated = $this->warehouseService->updateWarehouse($warehouse, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Warehouse facility updated successfully.',
            'data' => new WarehouseResource($updated),
        ]);
    }
}
