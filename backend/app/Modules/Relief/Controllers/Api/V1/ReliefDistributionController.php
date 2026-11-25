<?php

namespace App\Modules\Relief\Controllers\Api\V1;

use App\Core\Services\AdministrativeScopeService;
use App\Modules\Beneficiary\Models\Household;
use App\Modules\Relief\Models\DistributionVoucher;
use App\Modules\Relief\Models\ReliefAllocationItem;
use App\Modules\Relief\Requests\RecordDistributionRequest;
use App\Modules\Relief\Requests\VoidDistributionVoucherRequest;
use App\Modules\Relief\Resources\DistributionVoucherResource;
use App\Modules\Relief\Services\ReliefDuplicateService;
use App\Modules\Relief\Services\ReliefManagementService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReliefDistributionController extends Controller
{
    public function __construct(
        protected ReliefManagementService $reliefService,
        protected ReliefDuplicateService $duplicateService,
        protected AdministrativeScopeService $scopeService
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAnyDistributions', DistributionVoucher::class);

        $query = DistributionVoucher::inJurisdiction($request->user())
            ->with([
                'allocationItem.commodity',
                'allocationItem.allocation.disasterEvent',
                'household',
                'distributionPoint',
                'distributor',
                'verifyingTagOfficer',
            ]);

        if ($allocId = $request->query('relief_allocation_id')) {
            $query->whereHas('allocationItem', fn ($q) => $q->where('relief_allocation_id', $allocId));
        }
        if ($disasterId = $request->query('disaster_event_id')) {
            $query->whereHas('allocationItem.allocation', fn ($q) => $q->where('disaster_event_id', $disasterId));
        }
        if ($request->has('is_voided')) {
            $query->where('is_voided', filter_var($request->query('is_voided'), FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->boolean('duplicate_override_only', false)) {
            $query->where('duplicate_override', true);
        }
        if ($search = trim((string) $request->query('search', ''))) {
            $isExternal = $request->user()?->hasRole('ROLE_EXTERNAL_PARTNER') ?? false;
            $query->where(function ($q) use ($search, $isExternal) {
                $q->where('voucher_no', 'like', "%{$search}%");
                if (!$isExternal) {
                    $q->orWhereHas('household', fn ($hq) => $hq->where('household_code', 'like', "%{$search}%"));
                }
            });
        }

        $perPage = min(100, max(5, (int) $request->query('per_page', 15)));
        $vouchers = $query->orderByDesc('distributed_at')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => DistributionVoucherResource::collection($vouchers->items()),
            'pagination' => [
                'current_page' => $vouchers->currentPage(),
                'per_page' => $vouchers->perPage(),
                'total' => $vouchers->total(),
                'last_page' => $vouchers->lastPage(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Pre-flight duplicate-assistance check for the distribution UI.
     */
    public function checkDuplicate(Request $request): JsonResponse
    {
        Gate::authorize('viewAnyDistributions', DistributionVoucher::class);

        $allocItemTable = (new ReliefAllocationItem())->getTable();
        $householdTable = (new Household())->getTable();

        $validated = $request->validate([
            'relief_allocation_item_id' => ['required', 'uuid', "exists:{$allocItemTable},id"],
            'household_id' => ['required', 'uuid', "exists:{$householdTable},id"],
        ]);

        $item = ReliefAllocationItem::with('allocation.administrativeUnit')
            ->findOrFail($validated['relief_allocation_item_id']);
        Gate::authorize('viewAllocation', $item->allocation);

        $household = Household::with('administrativeUnit')->findOrFail($validated['household_id']);
        if (!$this->scopeService->canAccessScope($request->user(), $household->administrativeUnit?->ancestry_path ?? '/')) {
            throw new AuthorizationException('Jurisdiction access denied for the requested household.');
        }

        return response()->json([
            'success' => true,
            'data' => $this->duplicateService->checkDuplicateAssistance($item, $household),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function store(RecordDistributionRequest $request): JsonResponse
    {
        $item = ReliefAllocationItem::with('allocation.administrativeUnit')
            ->findOrFail($request->validated('relief_allocation_item_id'));

        Gate::authorize('recordDistribution', [DistributionVoucher::class, $item->allocation]);

        $result = $this->reliefService->recordDistribution(
            $request->validated(),
            $request->user(),
            $request->file('photo_evidence')
        );

        return response()->json([
            'success' => true,
            'idempotent_replay' => $result['idempotent_replay'],
            'data' => new DistributionVoucherResource($result['voucher']),
            'timestamp' => now()->toIso8601String(),
        ], $result['idempotent_replay'] ? 200 : 201);
    }

    public function verify(Request $request, DistributionVoucher $voucher): JsonResponse
    {
        Gate::authorize('verifyDistribution', $voucher);

        $verified = $this->reliefService->verifyVoucher($voucher, $request->user());

        return response()->json([
            'success' => true,
            'data' => new DistributionVoucherResource($verified),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function void(VoidDistributionVoucherRequest $request, DistributionVoucher $voucher): JsonResponse
    {
        Gate::authorize('voidDistribution', $voucher);

        $voided = $this->reliefService->voidVoucher(
            $voucher,
            $request->user(),
            $request->validated('void_reason')
        );

        return response()->json([
            'success' => true,
            'data' => new DistributionVoucherResource($voided),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function downloadPhotoEvidence(DistributionVoucher $voucher): StreamedResponse|JsonResponse
    {
        $voucher->loadMissing('distributionPoint.administrativeUnit');
        Gate::authorize('viewDistribution', $voucher);

        if (!$voucher->photo_evidence_path || !Storage::disk('local')->exists($voucher->photo_evidence_path)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'PHOTO_NOT_FOUND', 'message' => 'Handoff photo evidence not found.'],
                'timestamp' => now()->toIso8601String(),
            ], 404);
        }

        return Storage::disk('local')->download(
            $voucher->photo_evidence_path,
            $voucher->voucher_no . '-evidence'
        );
    }

    public function exportMuster(Request $request): StreamedResponse
    {
        Gate::authorize('exportMuster', DistributionVoucher::class);

        $vouchers = DistributionVoucher::inJurisdiction($request->user())
            ->with(['allocationItem.commodity', 'allocationItem.allocation', 'household', 'distributionPoint', 'distributor', 'verifyingTagOfficer'])
            ->when(
                $request->query('relief_allocation_id'),
                fn ($q, $id) => $q->whereHas('allocationItem', fn ($aq) => $aq->where('relief_allocation_id', $id))
            )
            ->orderBy('distributed_at')
            ->get();

        $sanitize = static function ($value): string {
            $str = (string) ($value ?? '');
            if ($str !== '' && preg_match('/^[=+\-@]/', $str)) {
                return "'" . $str;
            }
            return $str;
        };

        return response()->streamDownload(function () use ($vouchers, $sanitize) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'Voucher No',
                'Allocation Order No',
                'Household Code',
                'Head of Household',
                'NID Masked',
                'Commodity',
                'Quantity Disbursed',
                'Unit',
                'Verification Method',
                'Distribution Point',
                'Distributor',
                'Tag Officer Witness',
                'Duplicate Override',
                'Distributed At',
                'Voided',
            ]);

            foreach ($vouchers as $v) {
                fputcsv($out, [
                    $sanitize($v->voucher_no),
                    $sanitize($v->allocationItem?->allocation?->allocation_order_no),
                    $sanitize($v->household?->household_code),
                    $sanitize($v->household?->hoh_name_en),
                    $sanitize($v->household?->hoh_nid_masked),
                    $sanitize($v->allocationItem?->commodity?->name_en),
                    $v->quantity_disbursed,
                    $sanitize($v->allocationItem?->commodity?->unit_of_measure),
                    $sanitize($v->verification_method),
                    $sanitize($v->distributionPoint?->point_name),
                    $sanitize($v->distributor?->full_name_en),
                    $sanitize($v->verifyingTagOfficer?->full_name_en),
                    $v->duplicate_override ? 'YES' : 'NO',
                    $v->distributed_at?->toIso8601String(),
                    $v->is_voided ? 'YES' : 'NO',
                ]);
            }
            fclose($out);
        }, 'relief-muster-roll-' . now()->format('Ymd-His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
