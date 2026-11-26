<?php

namespace App\Modules\Warehouse\Controllers\Api\V1;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Http\Controllers\Controller;
use App\Modules\Relief\Models\ReliefCategory;
use App\Modules\Warehouse\Models\Commodity;
use App\Modules\Warehouse\Models\StockReceipt;
use App\Modules\Warehouse\Models\StockTransaction;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Warehouse\Models\WarehouseTransfer;
use App\Modules\Warehouse\Policies\WarehousePolicy;
use App\Modules\Warehouse\Services\WarehouseManagementService;
use App\Modules\Warehouse\Services\WarehouseSpatialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class WarehouseDashboardController extends Controller
{
    public function __construct(
        protected WarehouseManagementService $warehouseService,
        protected WarehouseSpatialService $spatialService
    ) {}

    public function summary(Request $request): JsonResponse
    {
        Gate::forUser($request->user())->authorize('viewAnyStock', Warehouse::class);

        $data = $this->warehouseService->getDashboardAnalytics($request->user());

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function lookups(Request $request): JsonResponse
    {
        Gate::forUser($request->user())->authorize('viewAnyStock', Warehouse::class);

        $user = $request->user();

        $warehouses = Warehouse::query()
            ->supplyingJurisdiction($user)
            ->with('administrativeUnit:id,code,name_en,name_bn,tier_level,ancestry_path')
            ->orderBy('warehouse_name')
            ->get([
                'id',
                'administrative_unit_id',
                'warehouse_code',
                'warehouse_name',
                'warehouse_name_bn',
                'tier',
                'warehouse_type',
                'capacity_mt',
                'is_active',
            ]);

        $commodities = Commodity::query()
            ->with('reliefCategory:id,code,name_en,name_bn')
            ->where('is_active', true)
            ->orderBy('name_en')
            ->get([
                'id',
                'relief_category_id',
                'commodity_code',
                'name_en',
                'name_bn',
                'category',
                'unit_of_measure',
                'minimum_stock_level',
                'is_perishable',
            ]);

        $reliefCategories = ReliefCategory::query()
            ->where('is_active', true)
            ->orderBy('name_en')
            ->get(['id', 'code', 'name_en', 'name_bn']);

        $adminQuery = AdministrativeUnit::query()
            ->where('is_active', true)
            ->orderBy('ancestry_path')
            ->orderBy('name_en');

        if (! $user->isNationalScope()) {
            $path = rtrim($user->getAdministrativeScope()['ancestry_path'] ?? '/', '/') . '/';
            if ($path !== '/') {
                $adminQuery->where('ancestry_path', 'like', $path . '%');
            }
        }

        $adminUnits = $adminQuery->get(['id', 'code', 'name_en', 'name_bn', 'tier_level', 'ancestry_path']);

        $coAuthorizers = $user->hasRole('ROLE_EXTERNAL_PARTNER')
            ? collect()
            : User::query()
                ->where('id', '!=', $user->id)
                ->where('is_active', true)
                ->whereIn('role_code', WarehousePolicy::STOCK_WRITEOFF_ROLES)
                ->orderBy('full_name_en')
                ->get(['id', 'full_name_en', 'full_name_bn', 'role_code', 'designation', 'administrative_unit_id']);

        return response()->json([
            'success' => true,
            'data' => [
                'tiers' => [
                    Warehouse::TIER_CENTRAL_CSD,
                    Warehouse::TIER_REGIONAL_LSD,
                    Warehouse::TIER_DISTRICT_GODOWN,
                    Warehouse::TIER_UPAZILA_STORE,
                ],
                'warehouse_types' => [
                    Warehouse::TYPE_RELIEF_GODOWN,
                    Warehouse::TYPE_COLD_STORAGE,
                    Warehouse::TYPE_FOOD_SILO,
                    Warehouse::TYPE_EMERGENCY_HUB,
                    Warehouse::TYPE_MEDICAL_DEPOT,
                ],
                'transaction_types' => [
                    StockTransaction::TYPE_OPENING_STOCK,
                    StockTransaction::TYPE_STOCK_IN_GRN,
                    StockTransaction::TYPE_INTER_WAREHOUSE_TRANSFER,
                    StockTransaction::TYPE_STOCK_ISSUE,
                    StockTransaction::TYPE_DISTRIBUTION_ISSUE,
                    StockTransaction::TYPE_RETURN_IN,
                    StockTransaction::TYPE_ALLOCATION_RETURN,
                    StockTransaction::TYPE_STOCK_ADJUSTMENT,
                    StockTransaction::TYPE_WRITE_OFF_DAMAGED,
                    StockTransaction::TYPE_EXPIRED_STOCK,
                ],
                'receipt_statuses' => [
                    StockReceipt::STATUS_PENDING_VERIFICATION,
                    StockReceipt::STATUS_VERIFIED,
                    StockReceipt::STATUS_REJECTED,
                ],
                'transfer_statuses' => [
                    WarehouseTransfer::STATUS_REQUESTED,
                    WarehouseTransfer::STATUS_APPROVED,
                    WarehouseTransfer::STATUS_DISPATCHED,
                    WarehouseTransfer::STATUS_RECEIVED,
                    WarehouseTransfer::STATUS_CANCELLED,
                ],
                'warehouses' => $warehouses,
                'commodities' => $commodities,
                'relief_categories' => $reliefCategories,
                'administrative_units' => $adminUnits,
                'co_authorizers' => $coAuthorizers,
            ],
        ]);
    }

    public function geojson(Request $request): JsonResponse
    {
        Gate::forUser($request->user())->authorize('viewAnyFacilities', Warehouse::class);

        $geojson = $this->spatialService->buildGeoJson(
            $request->user(),
            $request->only(['tier', 'warehouse_type', 'is_active'])
        );

        return response()->json([
            'success' => true,
            'data' => $geojson,
        ]);
    }

    public function alerts(Request $request): JsonResponse
    {
        Gate::forUser($request->user())->authorize('viewAnyStock', Warehouse::class);

        $days = max(1, min(365, (int) $request->query('days', 30)));
        $alerts = $this->warehouseService->getStockAlerts($request->user(), $days);

        return response()->json([
            'success' => true,
            'data' => $alerts,
        ]);
    }

    public function reports(Request $request): JsonResponse
    {
        Gate::forUser($request->user())->authorize('viewAnyStock', Warehouse::class);

        $reportType = (string) $request->query('type', 'STOCK_BALANCE');
        $rows = $this->warehouseService->generateReport(
            $reportType,
            $request->user(),
            $request->only(['warehouse_id', 'commodity_id', 'transaction_type', 'date_from', 'date_to', 'days'])
        );

        return response()->json([
            'success' => true,
            'report_type' => strtoupper($reportType),
            'data' => $rows,
        ]);
    }

    public function export(Request $request): Response
    {
        Gate::forUser($request->user())->authorize('exportLedger', Warehouse::class);

        $csv = $this->warehouseService->generateLedgerCsv(
            $request->user(),
            $request->only(['warehouse_id', 'commodity_id', 'transaction_type', 'date_from', 'date_to'])
        );

        $filename = 'idms-warehouse-ledger-' . now()->format('Ymd-His') . '.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
