<?php

namespace App\Modules\Response\Controllers\Api\V1;

use App\Modules\Response\Models\ResponseOperation;
use App\Modules\Response\Requests\StoreResponseOperationRequest;
use App\Modules\Response\Requests\TransitionOperationLifecycleRequest;
use App\Modules\Response\Resources\ResponseOperationResource;
use App\Modules\Response\Services\ResponseManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class ResponseOperationController extends Controller
{
    public function __construct(
        protected ResponseManagementService $responseService
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ResponseOperation::class);

        $query = ResponseOperation::inJurisdiction($request->user())
            ->with(['disasterEvent', 'administrativeUnit', 'responseCenter'])
            ->withCount(['tasks', 'teams', 'situationReports']);

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
                $q->where('operation_no', 'like', "%{$search}%")
                    ->orWhere('title_en', 'like', "%{$search}%")
                    ->orWhere('title_bn', 'like', "%{$search}%")
                    ->orWhere('lead_organization', 'like', "%{$search}%");
            });
        }

        $perPage = min(100, max(5, (int) $request->query('per_page', 15)));
        $operations = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => ResponseOperationResource::collection($operations->items()),
            'pagination' => [
                'current_page' => $operations->currentPage(),
                'per_page' => $operations->perPage(),
                'total' => $operations->total(),
                'last_page' => $operations->lastPage(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function store(StoreResponseOperationRequest $request): JsonResponse
    {
        $operation = $this->responseService->createOperation($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => new ResponseOperationResource($operation),
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function show(ResponseOperation $operation): JsonResponse
    {
        Gate::authorize('viewOperation', $operation);

        $operation->load([
            'disasterEvent',
            'administrativeUnit',
            'responseCenter',
            'agencies',
            'teams.administrativeUnit',
            'tasks.activityType',
            'tasks.team',
            'tasks.attachments',
            'resourceAllocations.asset',
            'situationReports.administrativeUnit',
            'situationReports.reporter',
        ]);

        return response()->json([
            'success' => true,
            'data' => new ResponseOperationResource($operation),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function update(StoreResponseOperationRequest $request, ResponseOperation $operation): JsonResponse
    {
        $updated = $this->responseService->updateOperation($operation, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => new ResponseOperationResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function destroy(Request $request, ResponseOperation $operation): JsonResponse
    {
        Gate::authorize('updateOperation', $operation);

        if ($operation->status !== ResponseOperation::STATUS_PLANNED) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CANNOT_DELETE_ACTIVE_OPERATION',
                    'message' => 'Only PLANNED response operations can be deleted.',
                ],
                'timestamp' => now()->toIso8601String(),
            ], 422);
        }

        $operation->delete();

        \App\Core\Models\AuditLog::log(
            'RESPONSE_OPERATION_DELETED',
            ResponseOperation::class,
            $operation->id,
            ['status' => $operation->status],
            ['deleted' => true],
            $request->user(),
            $operation->administrative_unit_id
        );

        return response()->json([
            'success' => true,
            'data' => ['id' => $operation->id, 'deleted' => true],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function transitionLifecycle(TransitionOperationLifecycleRequest $request, ResponseOperation $operation): JsonResponse
    {
        $updated = $this->responseService->transitionLifecycle(
            $operation,
            $request->validated('status'),
            $request->user(),
            $request->validated('notes')
        );

        return response()->json([
            'success' => true,
            'data' => new ResponseOperationResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
