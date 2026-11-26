<?php

namespace App\Modules\Warehouse\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Warehouse\Models\StockReceipt;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Warehouse\Requests\StoreStockReceiptRequest;
use App\Modules\Warehouse\Resources\StockReceiptResource;
use App\Modules\Warehouse\Services\WarehouseManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockReceiptController extends Controller
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

        $query = StockReceipt::query()
            ->whereIn('warehouse_id', $warehouseIds)
            ->with(['warehouse', 'items.commodity', 'receiver', 'verifier'])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('receipt_no', 'like', "%{$search}%")
                        ->orWhere('supplier_name', 'like', "%{$search}%")
                        ->orWhere('purchase_order_no', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', $request->query('warehouse_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->orderByDesc('received_date')
            ->orderByDesc('created_at');

        return StockReceiptResource::collection($query->paginate($perPage));
    }

    public function store(StoreStockReceiptRequest $request): JsonResponse
    {
        $receipt = $this->warehouseService->createStockReceipt(
            $request->validated(),
            $request->user(),
            $request->file('attachment')
        );

        return response()->json([
            'success' => true,
            'message' => 'Goods receipt note recorded and pending verification.',
            'data' => new StockReceiptResource($receipt),
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $receipt = StockReceipt::with(['warehouse.administrativeUnit', 'items.commodity', 'receiver', 'verifier'])
            ->findOrFail($id);

        Gate::forUser($request->user())->authorize('viewReceipt', $receipt);

        return response()->json([
            'success' => true,
            'data' => new StockReceiptResource($receipt),
        ]);
    }

    public function verify(Request $request, string $id): JsonResponse
    {
        $receipt = StockReceipt::with(['warehouse.administrativeUnit', 'items'])->findOrFail($id);

        $validated = $request->validate([
            'verification_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $verified = $this->warehouseService->verifyStockReceipt(
            $receipt,
            $request->user(),
            $validated['verification_notes'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Goods receipt verified and stock added to inventory ledger.',
            'data' => new StockReceiptResource($verified),
        ]);
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $receipt = StockReceipt::with(['warehouse.administrativeUnit'])->findOrFail($id);

        $validated = $request->validate([
            'verification_notes' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $rejected = $this->warehouseService->rejectStockReceipt(
            $receipt,
            $request->user(),
            $validated['verification_notes']
        );

        return response()->json([
            'success' => true,
            'message' => 'Goods receipt rejected; no stock was added to inventory.',
            'data' => new StockReceiptResource($rejected),
        ]);
    }

    public function downloadAttachment(Request $request, string $id): StreamedResponse|JsonResponse
    {
        $receipt = StockReceipt::with('warehouse.administrativeUnit')->findOrFail($id);
        Gate::forUser($request->user())->authorize('viewReceipt', $receipt);

        if (empty($receipt->attachment_path) || ! Storage::disk('local')->exists($receipt->attachment_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Supporting attachment not found for this GRN.',
            ], 404);
        }

        return Storage::disk('local')->download(
            $receipt->attachment_path,
            $receipt->attachment_original_name ?: ($receipt->receipt_no . '.pdf')
        );
    }
}
