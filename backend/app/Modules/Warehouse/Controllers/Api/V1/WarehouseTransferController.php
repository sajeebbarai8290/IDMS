<?php

namespace App\Modules\Warehouse\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Warehouse\Models\WarehouseTransfer;
use App\Modules\Warehouse\Requests\StoreWarehouseTransferRequest;
use App\Modules\Warehouse\Resources\WarehouseTransferResource;
use App\Modules\Warehouse\Services\WarehouseManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class WarehouseTransferController extends Controller
{
    public function __construct(
        protected WarehouseManagementService $warehouseService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('viewAnyStock', Warehouse::class);

        $perPage = max(1, min(100, (int) $request->query('per_page', 15)));
        $search = trim((string) $request->query('search', ''));

        $warehouseIds = Warehouse::query()
            ->supplyingJurisdiction($request->user())
            ->pluck('id');

        $query = WarehouseTransfer::query()
            ->where(function ($q) use ($warehouseIds) {
                $q->whereIn('source_warehouse_id', $warehouseIds)
                    ->orWhereIn('destination_warehouse_id', $warehouseIds);
            })
            ->with([
                'sourceWarehouse',
                'destinationWarehouse',
                'items.commodity',
                'requester',
                'approver',
                'dispatcher',
                'receiver',
            ])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('transfer_no', 'like', "%{$search}%")
                        ->orWhere('vehicle_info', 'like', "%{$search}%")
                        ->orWhere('dispatch_reference', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('source_warehouse_id'), fn ($q) => $q->where('source_warehouse_id', $request->query('source_warehouse_id')))
            ->when($request->filled('destination_warehouse_id'), fn ($q) => $q->where('destination_warehouse_id', $request->query('destination_warehouse_id')))
            ->orderByDesc('requested_date')
            ->orderByDesc('created_at');

        return WarehouseTransferResource::collection($query->paginate($perPage));
    }

    public function store(StoreWarehouseTransferRequest $request): JsonResponse
    {
        $transfer = $this->warehouseService->requestTransfer($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Inter-warehouse transfer request submitted successfully.',
            'data' => new WarehouseTransferResource($transfer),
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $transfer = WarehouseTransfer::with([
            'sourceWarehouse.administrativeUnit',
            'destinationWarehouse.administrativeUnit',
            'items.commodity',
            'requester',
            'approver',
            'dispatcher',
            'receiver',
        ])->findOrFail($id);

        Gate::forUser($request->user())->authorize('viewTransfer', $transfer);

        return response()->json([
            'success' => true,
            'data' => new WarehouseTransferResource($transfer),
        ]);
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $transfer = WarehouseTransfer::with(['sourceWarehouse.administrativeUnit', 'destinationWarehouse.administrativeUnit'])->findOrFail($id);

        $approved = $this->warehouseService->approveTransfer($transfer, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Transfer request approved for dispatch.',
            'data' => new WarehouseTransferResource($approved),
        ]);
    }

    public function dispatchOrder(Request $request, string $id): JsonResponse
    {
        $transfer = WarehouseTransfer::with(['sourceWarehouse.administrativeUnit', 'items'])->findOrFail($id);

        $validated = $request->validate([
            'dispatch_reference' => ['nullable', 'string', 'max:100'],
            'vehicle_info' => ['nullable', 'string', 'max:180'],
        ]);

        $dispatched = $this->warehouseService->dispatchTransfer(
            $transfer,
            $request->user(),
            $validated['dispatch_reference'] ?? null,
            $validated['vehicle_info'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Transfer dispatched and stock deducted from source warehouse.',
            'data' => new WarehouseTransferResource($dispatched),
        ]);
    }

    public function receiveOrder(Request $request, string $id): JsonResponse
    {
        $transfer = WarehouseTransfer::with(['destinationWarehouse.administrativeUnit', 'items'])->findOrFail($id);

        $validated = $request->validate([
            'received_quantities' => ['nullable', 'array'],
            'received_quantities.*' => ['numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $received = $this->warehouseService->receiveTransfer(
            $transfer,
            $request->user(),
            $validated['received_quantities'] ?? [],
            $validated['notes'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Transfer receipt confirmed and stock credited to destination warehouse.',
            'data' => new WarehouseTransferResource($received),
        ]);
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $transfer = WarehouseTransfer::with(['sourceWarehouse.administrativeUnit', 'destinationWarehouse.administrativeUnit'])->findOrFail($id);

        $validated = $request->validate([
            'cancellation_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $cancelled = $this->warehouseService->cancelTransfer($transfer, $request->user(), $validated['cancellation_reason']);

        return response()->json([
            'success' => true,
            'message' => 'Transfer order cancelled.',
            'data' => new WarehouseTransferResource($cancelled),
        ]);
    }
}
