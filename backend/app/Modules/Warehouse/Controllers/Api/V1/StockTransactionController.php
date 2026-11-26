<?php

namespace App\Modules\Warehouse\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Warehouse\Models\StockTransaction;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Warehouse\Models\WarehouseInventory;
use App\Modules\Warehouse\Requests\StoreStockAdjustmentRequest;
use App\Modules\Warehouse\Resources\StockTransactionResource;
use App\Modules\Warehouse\Resources\WarehouseInventoryResource;
use App\Modules\Warehouse\Services\WarehouseManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class StockTransactionController extends Controller
{
    public function __construct(
        protected WarehouseManagementService $warehouseService
    ) {}

    public function inventories(Request $request): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('viewAnyStock', Warehouse::class);

        $perPage = max(1, min(100, (int) $request->query('per_page', 20)));
        $search = trim((string) $request->query('search', ''));

        $warehouseIds = Warehouse::query()
            ->supplyingJurisdiction($request->user())
            ->pluck('id');

        $query = WarehouseInventory::query()
            ->whereIn('warehouse_id', $warehouseIds)
            ->with(['warehouse', 'commodity'])
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', $request->query('warehouse_id')))
            ->when($request->filled('commodity_id'), fn ($q) => $q->where('commodity_id', $request->query('commodity_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('batch_number', 'like', "%{$search}%")
                        ->orWhereHas('commodity', fn ($cq) => $cq->where('name_en', 'like', "%{$search}%")->orWhere('commodity_code', 'like', "%{$search}%"))
                        ->orWhereHas('warehouse', fn ($wq) => $wq->where('warehouse_name', 'like', "%{$search}%"));
                });
            })
            ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END ASC')
            ->orderBy('expiry_date', 'asc')
            ->orderByDesc('updated_at');

        return WarehouseInventoryResource::collection($query->paginate($perPage));
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('viewAnyStock', Warehouse::class);

        $perPage = max(1, min(100, (int) $request->query('per_page', 20)));
        $search = trim((string) $request->query('search', ''));

        $warehouseIds = Warehouse::query()
            ->supplyingJurisdiction($request->user())
            ->pluck('id');

        $query = StockTransaction::query()
            ->whereHas('warehouseInventory', fn ($q) => $q->whereIn('warehouse_id', $warehouseIds))
            ->with([
                'warehouseInventory.warehouse',
                'warehouseInventory.commodity',
                'warehouse',
                'commodity',
                'authorizer',
                'approver',
            ])
            ->when($request->filled('warehouse_id'), fn ($q) => $q->whereHas('warehouseInventory', fn ($iq) => $iq->where('warehouse_id', $request->query('warehouse_id'))))
            ->when($request->filled('commodity_id'), fn ($q) => $q->whereHas('warehouseInventory', fn ($iq) => $iq->where('commodity_id', $request->query('commodity_id'))))
            ->when($request->filled('transaction_type'), fn ($q) => $q->where('transaction_type', $request->query('transaction_type')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->query('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->query('date_to')))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('reference_voucher_no', 'like', "%{$search}%")
                        ->orWhere('reason_code', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%");
                });
            })
            ->latest('created_at');

        return StockTransactionResource::collection($query->paginate($perPage));
    }

    public function storeAdjustment(StoreStockAdjustmentRequest $request): JsonResponse
    {
        $tx = $this->warehouseService->recordManualStockMovement($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Immutable stock ledger entry recorded.',
            'data' => new StockTransactionResource($tx),
        ], 201);
    }
}
