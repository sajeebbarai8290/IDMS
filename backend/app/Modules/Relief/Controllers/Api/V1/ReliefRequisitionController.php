<?php

namespace App\Modules\Relief\Controllers\Api\V1;

use App\Modules\Relief\Models\ReliefRequisition;
use App\Modules\Relief\Requests\ApproveReliefRequisitionRequest;
use App\Modules\Relief\Requests\RejectReliefRequisitionRequest;
use App\Modules\Relief\Requests\StoreReliefRequisitionRequest;
use App\Modules\Relief\Resources\ReliefRequisitionResource;
use App\Modules\Relief\Services\ReliefManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReliefRequisitionController extends Controller
{
    public function __construct(
        protected ReliefManagementService $reliefService
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAnyRequisitions', ReliefRequisition::class);

        $query = ReliefRequisition::inJurisdiction($request->user())
            ->with(['disasterEvent', 'administrativeUnit', 'items.commodity', 'creator', 'approver'])
            ->withCount('allocations');

        if ($status = $request->query('status')) {
            $query->where('status', strtoupper((string) $status));
        }
        if ($priority = $request->query('priority')) {
            $query->where('priority', strtoupper((string) $priority));
        }
        if ($disasterId = $request->query('disaster_event_id')) {
            $query->where('disaster_event_id', $disasterId);
        }
        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('requisition_no', 'like', "%{$search}%")
                    ->orWhere('requesting_organization', 'like', "%{$search}%");
            });
        }

        $perPage = min(100, max(5, (int) $request->query('per_page', 15)));
        $requisitions = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => ReliefRequisitionResource::collection($requisitions->items()),
            'pagination' => [
                'current_page' => $requisitions->currentPage(),
                'per_page' => $requisitions->perPage(),
                'total' => $requisitions->total(),
                'last_page' => $requisitions->lastPage(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function store(StoreReliefRequisitionRequest $request): JsonResponse
    {
        Gate::authorize('createRequisition', ReliefRequisition::class);

        $requisition = $this->reliefService->createRequisition(
            $request->validated(),
            $request->user(),
            $request->file('supporting_doc')
        );

        return response()->json([
            'success' => true,
            'data' => new ReliefRequisitionResource($requisition),
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function show(ReliefRequisition $requisition): JsonResponse
    {
        $requisition->loadMissing('administrativeUnit');
        Gate::authorize('viewRequisition', $requisition);

        $requisition->load(['disasterEvent', 'administrativeUnit', 'items.commodity.reliefCategory', 'creator', 'submitter', 'reviewer', 'approver', 'allocations']);

        return response()->json([
            'success' => true,
            'data' => new ReliefRequisitionResource($requisition),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function update(StoreReliefRequisitionRequest $request, ReliefRequisition $requisition): JsonResponse
    {
        $requisition->loadMissing('administrativeUnit');
        Gate::authorize('updateRequisition', $requisition);

        $updated = $this->reliefService->updateRequisition(
            $requisition,
            $request->validated(),
            $request->user(),
            $request->file('supporting_doc')
        );

        return response()->json([
            'success' => true,
            'data' => new ReliefRequisitionResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function destroy(ReliefRequisition $requisition): JsonResponse
    {
        $requisition->loadMissing('administrativeUnit');
        Gate::authorize('deleteRequisition', $requisition);

        if ($requisition->status !== ReliefRequisition::STATUS_DRAFT) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CANNOT_DELETE_SUBMITTED',
                    'message' => 'Only DRAFT requisitions can be deleted.',
                ],
                'timestamp' => now()->toIso8601String(),
            ], 422);
        }

        $requisition->delete();

        \App\Core\Models\AuditLog::log(
            'RELIEF_REQUISITION_DELETED',
            ReliefRequisition::class,
            $requisition->id,
            ['status' => $requisition->status],
            ['deleted' => true],
            request()->user(),
            $requisition->administrative_unit_id
        );

        return response()->json([
            'success' => true,
            'data' => ['id' => $requisition->id, 'deleted' => true],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function submit(Request $request, ReliefRequisition $requisition): JsonResponse
    {
        $requisition->loadMissing('administrativeUnit');
        Gate::authorize('updateRequisition', $requisition);

        $submitted = $this->reliefService->submitRequisition($requisition, $request->user());

        return response()->json([
            'success' => true,
            'data' => new ReliefRequisitionResource($submitted),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function review(Request $request, ReliefRequisition $requisition): JsonResponse
    {
        $requisition->loadMissing('administrativeUnit');
        Gate::authorize('approveRequisition', $requisition);

        $reviewed = $this->reliefService->reviewRequisition(
            $requisition,
            $request->user(),
            $request->input('review_notes')
        );

        return response()->json([
            'success' => true,
            'data' => new ReliefRequisitionResource($reviewed),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function approve(ApproveReliefRequisitionRequest $request, ReliefRequisition $requisition): JsonResponse
    {
        $requisition->loadMissing('administrativeUnit');
        Gate::authorize('approveRequisition', $requisition);

        $approved = $this->reliefService->approveRequisition(
            $requisition,
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'data' => new ReliefRequisitionResource($approved),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function reject(RejectReliefRequisitionRequest $request, ReliefRequisition $requisition): JsonResponse
    {
        $requisition->loadMissing('administrativeUnit');
        Gate::authorize('approveRequisition', $requisition);

        $rejected = $this->reliefService->rejectRequisition(
            $requisition,
            $request->user(),
            $request->validated('review_notes')
        );

        return response()->json([
            'success' => true,
            'data' => new ReliefRequisitionResource($rejected),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function downloadSupportingDoc(ReliefRequisition $requisition): StreamedResponse|JsonResponse
    {
        $requisition->loadMissing('administrativeUnit');
        Gate::authorize('viewRequisition', $requisition);

        if (!$requisition->supporting_doc_path || !Storage::disk('local')->exists($requisition->supporting_doc_path)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'DOCUMENT_NOT_FOUND', 'message' => 'Supporting document not found.'],
                'timestamp' => now()->toIso8601String(),
            ], 404);
        }

        return Storage::disk('local')->download(
            $requisition->supporting_doc_path,
            $requisition->supporting_doc_name ?: 'supporting-document'
        );
    }
}
