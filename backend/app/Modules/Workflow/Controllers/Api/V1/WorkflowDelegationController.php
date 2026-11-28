<?php

namespace App\Modules\Workflow\Controllers\Api\V1;

use App\Modules\Workflow\Models\WorkflowDelegation;
use App\Modules\Workflow\Requests\StoreWorkflowDelegationRequest;
use App\Modules\Workflow\Resources\WorkflowDelegationResource;
use App\Modules\Workflow\Services\WorkflowDelegationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class WorkflowDelegationController extends Controller
{
    public function __construct(
        protected WorkflowDelegationService $delegationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', \App\Modules\Workflow\Models\WorkflowInstance::class);

        $delegations = $this->delegationService->listDelegations(
            $request->user(),
            $request->only(['active_only', 'per_page'])
        );

        return response()->json([
            'success' => true,
            'data' => WorkflowDelegationResource::collection($delegations->items()),
            'meta' => [
                'current_page' => $delegations->currentPage(),
                'last_page' => $delegations->lastPage(),
                'per_page' => $delegations->perPage(),
                'total' => $delegations->total(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function store(StoreWorkflowDelegationRequest $request): JsonResponse
    {
        $delegation = $this->delegationService->createDelegation($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => new WorkflowDelegationResource($delegation),
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function revoke(Request $request, WorkflowDelegation $delegation): JsonResponse
    {
        Gate::authorize('manageDelegation', $delegation);

        $updated = $this->delegationService->revokeDelegation($delegation, $request->user());

        return response()->json([
            'success' => true,
            'data' => new WorkflowDelegationResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
