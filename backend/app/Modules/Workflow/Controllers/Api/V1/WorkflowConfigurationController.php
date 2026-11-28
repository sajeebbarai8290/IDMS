<?php

namespace App\Modules\Workflow\Controllers\Api\V1;

use App\Modules\Workflow\Models\WorkflowDefinition;
use App\Modules\Workflow\Requests\StoreWorkflowDefinitionRequest;
use App\Modules\Workflow\Requests\UpdateWorkflowDefinitionRequest;
use App\Modules\Workflow\Resources\WorkflowDefinitionResource;
use App\Modules\Workflow\Services\WorkflowConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class WorkflowConfigurationController extends Controller
{
    public function __construct(
        protected WorkflowConfigurationService $configService
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', \App\Modules\Workflow\Models\WorkflowInstance::class);

        $definitions = $this->configService->listDefinitions(
            $request->only(['module_code', 'entity_type', 'is_active', 'search'])
        );

        return response()->json([
            'success' => true,
            'data' => WorkflowDefinitionResource::collection($definitions),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function store(StoreWorkflowDefinitionRequest $request): JsonResponse
    {
        $definition = $this->configService->createDefinition($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => new WorkflowDefinitionResource($definition),
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function show(WorkflowDefinition $definition): JsonResponse
    {
        Gate::authorize('viewAny', \App\Modules\Workflow\Models\WorkflowInstance::class);

        $definition->load(['stages.assignments.user', 'stages.assignments.administrativeUnit'])
            ->loadCount('instances');

        return response()->json([
            'success' => true,
            'data' => new WorkflowDefinitionResource($definition),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function update(UpdateWorkflowDefinitionRequest $request, WorkflowDefinition $definition): JsonResponse
    {
        $updated = $this->configService->updateDefinition($definition, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => new WorkflowDefinitionResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function toggleStatus(Request $request, WorkflowDefinition $definition): JsonResponse
    {
        Gate::authorize('manageConfiguration', WorkflowDefinition::class);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $updated = $this->configService->toggleStatus($definition, (bool) $validated['is_active'], $request->user());

        return response()->json([
            'success' => true,
            'data' => new WorkflowDefinitionResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function destroy(Request $request, WorkflowDefinition $definition): JsonResponse
    {
        Gate::authorize('manageConfiguration', WorkflowDefinition::class);

        $this->configService->deleteDefinition($definition, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Workflow definition deleted successfully.',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
