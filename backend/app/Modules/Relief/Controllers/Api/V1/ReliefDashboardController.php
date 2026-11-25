<?php

namespace App\Modules\Relief\Controllers\Api\V1;

use App\Modules\Beneficiary\Models\Household;
use App\Modules\Disaster\Models\DisasterEvent;
use App\Modules\Relief\Models\DistributionPoint;
use App\Modules\Relief\Models\ReliefRequisition;
use App\Modules\Relief\Services\ReliefManagementService;
use App\Modules\Warehouse\Models\Commodity;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class ReliefDashboardController extends Controller
{
    public function __construct(
        protected ReliefManagementService $reliefService
    ) {}

    public function summary(Request $request): JsonResponse
    {
        Gate::authorize('viewAnyRequisitions', ReliefRequisition::class);

        $data = $this->reliefService->getDashboardAnalytics(
            $request->user(),
            $request->only(['disaster_event_id'])
        );

        return response()->json([
            'success' => true,
            'data' => $data,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Scoped operational reference lookups (commodities, warehouses with live stock,
     * distribution centers, disasters, and verified eligible households).
     */
    public function lookups(Request $request): JsonResponse
    {
        if (
            Gate::denies('viewAnyRequisitions', ReliefRequisition::class)
            && Gate::denies('viewAnyAllocations', \App\Modules\Relief\Models\ReliefAllocation::class)
        ) {
            Gate::authorize('viewAnyAllocations', \App\Modules\Relief\Models\ReliefAllocation::class);
        }
        $user = $request->user();

        $commodities = Commodity::with('reliefCategory')
            ->where('is_active', true)
            ->orderBy('name_en')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'commodity_code' => $c->commodity_code,
                'name_en' => $c->name_en,
                'name_bn' => $c->name_bn,
                'category' => $c->category,
                'relief_category_id' => $c->relief_category_id,
                'unit_of_measure' => $c->unit_of_measure,
                'is_perishable' => (bool) $c->is_perishable,
            ]);

        $warehouses = Warehouse::supplyingJurisdiction($user)
            ->with('inventories')
            ->where('is_active', true)
            ->orderBy('warehouse_name')
            ->get()
            ->map(function (Warehouse $wh) {
                $stockByCommodity = $wh->inventories
                    ->groupBy('commodity_id')
                    ->map(fn ($lots) => [
                        'physical_quantity' => round((float) $lots->sum('current_quantity'), 2),
                        'reserved_quantity' => round((float) $lots->sum('reserved_quantity'), 2),
                        'unreserved_quantity' => round(
                            (float) $lots->sum(fn ($l) => max(0.0, (float) $l->current_quantity - (float) $l->reserved_quantity)),
                            2
                        ),
                    ]);

                return [
                    'id' => $wh->id,
                    'warehouse_code' => $wh->warehouse_code,
                    'warehouse_name' => $wh->warehouse_name,
                    'warehouse_name_bn' => $wh->warehouse_name_bn,
                    'tier' => $wh->tier,
                    'administrative_unit_id' => $wh->administrative_unit_id,
                    'stock_by_commodity' => $stockByCommodity,
                ];
            });

        $distributionPoints = DistributionPoint::inJurisdiction($user)
            ->with('administrativeUnit')
            ->where('is_active', true)
            ->orderBy('point_name')
            ->get()
            ->map(fn (DistributionPoint $dp) => [
                'id' => $dp->id,
                'point_code' => $dp->point_code,
                'point_name' => $dp->point_name,
                'point_name_bn' => $dp->point_name_bn,
                'administrative_unit_id' => $dp->administrative_unit_id,
                'unit_name_en' => $dp->administrativeUnit?->name_en,
                'unit_name_bn' => $dp->administrativeUnit?->name_bn,
                'latitude' => $dp->latitude,
                'longitude' => $dp->longitude,
            ]);

        $disasters = DisasterEvent::orderByDesc('declaration_date')
            ->limit(50)
            ->get(['id', 'disaster_code', 'name_en', 'name_bn', 'severity_level', 'lifecycle_phase']);

        return response()->json([
            'success' => true,
            'data' => [
                'commodities' => $commodities,
                'warehouses' => $warehouses,
                'distribution_points' => $distributionPoints,
                'disasters' => $disasters,
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Search eligible verified households in jurisdiction for allocation targeting & handoff.
     */
    public function eligibleHouseholds(Request $request): JsonResponse
    {
        Gate::authorize('viewAnyAllocations', \App\Modules\Relief\Models\ReliefAllocation::class);
        $user = $request->user();

        $query = Household::inJurisdiction($user)
            ->with('administrativeUnit')
            ->whereNotIn('verification_status', ['DRAFT', 'REJECTED']);

        if ($unitId = $request->query('administrative_unit_id')) {
            $unit = \App\Core\Models\AdministrativeUnit::find($unitId);
            if ($unit) {
                $norm = rtrim($unit->ancestry_path, '/') . '/';
                $query->whereHas('administrativeUnit', fn ($q) => $q->where('ancestry_path', 'like', $norm . '%'));
            }
        }

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('household_code', 'like', "%{$search}%")
                    ->orWhere('hoh_name_en', 'like', "%{$search}%")
                    ->orWhere('hoh_name_bn', 'like', "%{$search}%");
            });
        }

        $households = $query->orderByDesc('vulnerability_score')
            ->limit(100)
            ->get();

        $isExternal = $user->hasRole('ROLE_EXTERNAL_PARTNER');

        return response()->json([
            'success' => true,
            'data' => $isExternal ? [] : $households->map(fn (Household $h) => [
                'id' => $h->id,
                'household_code' => $h->household_code,
                'hoh_name_en' => $h->hoh_name_en,
                'hoh_name_bn' => $h->hoh_name_bn,
                'hoh_nid_masked' => $h->hoh_nid_masked,
                'vulnerability_score' => (float) $h->vulnerability_score,
                'verification_status' => $h->verification_status,
                'administrative_unit_id' => $h->administrative_unit_id,
                'unit_name_en' => $h->administrativeUnit?->name_en,
                'unit_name_bn' => $h->administrativeUnit?->name_bn,
            ]),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
