<?php

namespace App\Modules\Response\Controllers\Api\V1;

use App\Modules\Response\Models\ResponseOperation;
use App\Modules\Response\Models\ResponseResourceAllocation;
use App\Modules\Response\Models\ResponseTeam;
use App\Modules\Response\Requests\StoreResponseAgencyRequest;
use App\Modules\Response\Requests\StoreResponseResourceRequest;
use App\Modules\Response\Requests\StoreResponseTeamRequest;
use App\Modules\Response\Resources\ResponseTeamResource;
use App\Modules\Response\Services\ResponseManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ResponseCoordinationController extends Controller
{
    public function __construct(
        protected ResponseManagementService $responseService
    ) {}

    public function addAgency(StoreResponseAgencyRequest $request, ResponseOperation $operation): JsonResponse
    {
        $agency = $this->responseService->addAgency($operation, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => $agency,
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function addTeam(StoreResponseTeamRequest $request, ResponseOperation $operation): JsonResponse
    {
        $team = $this->responseService->addTeam($operation, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => new ResponseTeamResource($team),
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function updateTeamStatus(Request $request, ResponseTeam $team): JsonResponse
    {
        $team->loadMissing('operation');
        Gate::authorize('manageCoordination', $team->operation);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(ResponseTeam::STATUSES)],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $updated = $this->responseService->updateTeamStatus($team, $validated, $request->user());

        return response()->json([
            'success' => true,
            'data' => new ResponseTeamResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function addResource(StoreResponseResourceRequest $request, ResponseOperation $operation): JsonResponse
    {
        $allocation = $this->responseService->addResourceAllocation($operation, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => $allocation,
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function updateResourceStatus(Request $request, ResponseResourceAllocation $allocation): JsonResponse
    {
        $allocation->loadMissing('operation');
        Gate::authorize('manageCoordination', $allocation->operation);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(ResponseResourceAllocation::STATUSES)],
        ]);

        $updated = $this->responseService->updateResourceAllocationStatus(
            $allocation,
            $validated['status'],
            $request->user()
        );

        return response()->json([
            'success' => true,
            'data' => $updated,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
