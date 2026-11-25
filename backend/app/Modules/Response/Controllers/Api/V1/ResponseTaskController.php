<?php

namespace App\Modules\Response\Controllers\Api\V1;

use App\Modules\Response\Models\ResponseOperation;
use App\Modules\Response\Models\ResponseTask;
use App\Modules\Response\Models\ResponseTaskAttachment;
use App\Modules\Response\Requests\StoreResponseTaskRequest;
use App\Modules\Response\Requests\UpdateResponseTaskRequest;
use App\Modules\Response\Resources\ResponseTaskResource;
use App\Modules\Response\Services\ResponseManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResponseTaskController extends Controller
{
    public function __construct(
        protected ResponseManagementService $responseService
    ) {}

    public function store(StoreResponseTaskRequest $request, ResponseOperation $operation): JsonResponse
    {
        $task = $this->responseService->createTask($operation, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => new ResponseTaskResource($task),
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function updateProgress(UpdateResponseTaskRequest $request, ResponseTask $task): JsonResponse
    {
        $updated = $this->responseService->updateTaskProgress($task, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => new ResponseTaskResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function uploadAttachment(Request $request, ResponseTask $task): JsonResponse
    {
        $task->loadMissing('operation');
        Gate::authorize('manageTasks', $task->operation);

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx', 'max:5120'],
            'title' => ['nullable', 'string', 'max:160'],
        ]);

        $attachment = $this->responseService->attachTaskEvidence(
            $task,
            $request->file('file'),
            $request->user(),
            $validated['title'] ?? null
        );

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $attachment->id,
                'title' => $attachment->title,
                'original_name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'file_size_bytes' => $attachment->file_size_bytes,
                'created_at' => $attachment->created_at?->toIso8601String(),
            ],
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function downloadAttachment(ResponseTask $task, ResponseTaskAttachment $attachment): StreamedResponse|JsonResponse
    {
        $task->loadMissing('operation');
        Gate::authorize('viewOperation', $task->operation);

        if ($attachment->response_task_id !== $task->id || !Storage::disk('local')->exists($attachment->storage_path)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ATTACHMENT_NOT_FOUND', 'message' => 'Task evidence attachment not found.'],
                'timestamp' => now()->toIso8601String(),
            ], 404);
        }

        return Storage::disk('local')->download($attachment->storage_path, $attachment->original_name);
    }
}
