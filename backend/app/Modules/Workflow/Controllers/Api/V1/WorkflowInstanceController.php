<?php

namespace App\Modules\Workflow\Controllers\Api\V1;

use App\Modules\Workflow\Models\WorkflowAttachment;
use App\Modules\Workflow\Models\WorkflowInstance;
use App\Modules\Workflow\Models\WorkflowTransition;
use App\Modules\Workflow\Requests\StoreWorkflowInstanceRequest;
use App\Modules\Workflow\Requests\UploadWorkflowAttachmentRequest;
use App\Modules\Workflow\Requests\WorkflowActionRequest;
use App\Modules\Workflow\Resources\WorkflowInstanceResource;
use App\Modules\Workflow\Services\WorkflowEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkflowInstanceController extends Controller
{
    public function __construct(
        protected WorkflowEngineService $engineService
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', WorkflowInstance::class);

        $filters = $request->only([
            'queue',
            'module_code',
            'entity_type',
            'current_state',
            'priority',
            'administrative_unit_id',
            'workflow_definition_id',
            'date_from',
            'date_to',
            'search',
            'sort_by',
            'sort_dir',
            'per_page',
        ]);

        $paginator = $this->engineService->listInstances($request->user(), $filters);

        return response()->json([
            'success' => true,
            'data' => WorkflowInstanceResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function store(StoreWorkflowInstanceRequest $request): JsonResponse
    {
        $instance = $this->engineService->startWorkflow($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => new WorkflowInstanceResource($instance),
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function show(Request $request, WorkflowInstance $instance): JsonResponse
    {
        Gate::authorize('viewInstance', $instance);

        $detail = $this->engineService->getInstanceDetail($instance, $request->user());

        return response()->json([
            'success' => true,
            'data' => new WorkflowInstanceResource($detail['instance']),
            'available_actions' => $detail['available_actions'],
            'live_entity' => $detail['live_entity'],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function entityHistory(Request $request, string $entityType, string $entityId): JsonResponse
    {
        Gate::authorize('viewAny', WorkflowInstance::class);

        $instances = $this->engineService->getEntityHistory($entityType, $entityId, $request->user());

        return response()->json([
            'success' => true,
            'data' => WorkflowInstanceResource::collection($instances),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function approve(WorkflowActionRequest $request, WorkflowInstance $instance): JsonResponse
    {
        return $this->runAction($request, $instance, WorkflowTransition::ACTION_APPROVE);
    }

    public function reject(WorkflowActionRequest $request, WorkflowInstance $instance): JsonResponse
    {
        return $this->runAction($request, $instance, WorkflowTransition::ACTION_REJECT);
    }

    public function returnCorrection(WorkflowActionRequest $request, WorkflowInstance $instance): JsonResponse
    {
        return $this->runAction($request, $instance, WorkflowTransition::ACTION_RETURN_FOR_CORRECTION);
    }

    public function requestInfo(WorkflowActionRequest $request, WorkflowInstance $instance): JsonResponse
    {
        return $this->runAction($request, $instance, WorkflowTransition::ACTION_REQUEST_INFO);
    }

    public function provideInfo(WorkflowActionRequest $request, WorkflowInstance $instance): JsonResponse
    {
        return $this->runAction($request, $instance, WorkflowTransition::ACTION_PROVIDE_INFO);
    }

    public function resubmit(WorkflowActionRequest $request, WorkflowInstance $instance): JsonResponse
    {
        return $this->runAction($request, $instance, WorkflowTransition::ACTION_RESUBMIT);
    }

    public function forward(WorkflowActionRequest $request, WorkflowInstance $instance): JsonResponse
    {
        return $this->runAction($request, $instance, WorkflowTransition::ACTION_FORWARD);
    }

    public function cancel(WorkflowActionRequest $request, WorkflowInstance $instance): JsonResponse
    {
        return $this->runAction($request, $instance, WorkflowTransition::ACTION_CANCEL);
    }

    public function remind(Request $request, WorkflowInstance $instance): JsonResponse
    {
        if (Gate::denies('executeTransition', $instance) && Gate::denies('runEscalations', WorkflowInstance::class)) {
            Gate::authorize('executeTransition', $instance);
        } else {
            Gate::authorize('viewInstance', $instance);
        }

        $count = $this->engineService->sendPendingReminder($instance, $request->user());

        return response()->json([
            'success' => true,
            'reminded_tasks' => $count,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function uploadAttachment(UploadWorkflowAttachmentRequest $request, WorkflowInstance $instance): JsonResponse
    {
        Gate::authorize('executeTransition', $instance);

        $attachment = $this->engineService->uploadAttachment(
            $instance,
            $request->file('file'),
            $request->user(),
            $request->validated('title'),
            $request->validated('workflow_transition_id')
        );

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $attachment->id,
                'title' => $attachment->title,
                'original_name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'file_size_bytes' => $attachment->file_size_bytes,
                'sha256_hash' => $attachment->sha256_hash,
                'created_at' => $attachment->created_at?->toIso8601String(),
            ],
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function downloadAttachment(WorkflowInstance $instance, WorkflowAttachment $attachment): StreamedResponse
    {
        Gate::authorize('viewInstance', $instance);

        if ($attachment->workflow_instance_id !== $instance->id) {
            abort(404);
        }

        return $this->engineService->downloadAttachment($attachment);
    }

    protected function runAction(WorkflowActionRequest $request, WorkflowInstance $instance, string $action): JsonResponse
    {
        Gate::authorize('executeTransition', $instance);

        $validated = $request->validated();
        $comments = $validated['comments'] ?? $validated['remarks'] ?? $validated['reason'] ?? null;

        $updated = $this->engineService->executeAction(
            $instance,
            $action,
            $request->user(),
            array_merge($validated, ['comments' => $comments])
        );

        return response()->json([
            'success' => true,
            'data' => new WorkflowInstanceResource($updated),
            'available_actions' => $this->engineService->computeAvailableActions($updated, $request->user()),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
