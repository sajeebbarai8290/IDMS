<?php

namespace App\Modules\Warehouse\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Warehouse\Models\Commodity;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Warehouse\Models\WarehouseInventory;
use App\Modules\Warehouse\Requests\StoreCommodityRequest;
use App\Modules\Warehouse\Requests\UpdateCommodityRequest;
use App\Modules\Warehouse\Resources\CommodityResource;
use App\Modules\Warehouse\Services\WarehouseManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class WarehouseItemController extends Controller
{
    public function __construct(
        protected WarehouseManagementService $warehouseService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('viewAnyStock', Warehouse::class);

        $perPage = max(1, min(100, (int) $request->query('per_page', 20)));
        $search = trim((string) $request->query('search', ''));

        $scopedWarehouseIds = Warehouse::query()
            ->supplyingJurisdiction($request->user())
            ->pluck('id');

        $commodities = Commodity::query()
            ->with('reliefCategory')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('commodity_code', 'like', "%{$search}%")
                        ->orWhere('name_en', 'like', "%{$search}%")
                        ->orWhere('name_bn', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('category'), fn ($q) => $q->where('category', strtoupper((string) $request->query('category'))))
            ->when($request->has('is_active') && $request->query('is_active') !== '', function ($q) use ($request) {
                $q->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
            })
            ->orderBy('name_en')
            ->paginate($perPage);

        $totals = WarehouseInventory::query()
            ->whereIn('warehouse_id', $scopedWarehouseIds)
            ->whereIn('commodity_id', $commodities->pluck('id'))
            ->get()
            ->groupBy('commodity_id');

        foreach ($commodities as $commodity) {
            $lots = $totals->get($commodity->id, collect());
            $physical = round((float) $lots->sum('current_quantity'), 2);
            $reserved = round((float) $lots->sum('reserved_quantity'), 2);
            $commodity->total_current_quantity = $physical;
            $commodity->total_reserved_quantity = $reserved;
            $commodity->total_available_quantity = max(0.0, round($physical - $reserved, 2));
        }

        return CommodityResource::collection($commodities);
    }

    public function store(StoreCommodityRequest $request): JsonResponse
    {
        $commodity = $this->warehouseService->createCommodity($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Inventory item created successfully.',
            'data' => new CommodityResource($commodity),
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        Gate::forUser($request->user())->authorize('viewAnyStock', Warehouse::class);

        $commodity = Commodity::with('reliefCategory')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new CommodityResource($commodity),
        ]);
    }

    public function update(UpdateCommodityRequest $request, string $id): JsonResponse
    {
        $commodity = Commodity::findOrFail($id);
        Gate::forUser($request->user())->authorize('manageItems', $commodity);

        $updated = $this->warehouseService->updateCommodity($commodity, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Inventory item updated successfully.',
            'data' => new CommodityResource($updated),
        ]);
    }
}
