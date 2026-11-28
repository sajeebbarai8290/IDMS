<?php

namespace App\Modules\Notification\Controllers\Api\V1;

use App\Modules\Notification\Models\Notification;
use App\Modules\Notification\Models\NotificationTemplate;
use App\Modules\Notification\Requests\StoreNotificationTemplateRequest;
use App\Modules\Notification\Requests\UpdateNotificationTemplateRequest;
use App\Modules\Notification\Resources\NotificationTemplateResource;
use App\Modules\Notification\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class NotificationTemplateController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewLogs', Notification::class);

        $paginator = $this->notificationService->listTemplates($request->all());

        return response()->json([
            'success' => true,
            'data' => NotificationTemplateResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function store(StoreNotificationTemplateRequest $request): JsonResponse
    {
        Gate::authorize('manageTemplates', Notification::class);

        $template = $this->notificationService->createTemplate($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => new NotificationTemplateResource($template),
            'message' => 'Notification template created successfully.',
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function show(NotificationTemplate $template): JsonResponse
    {
        Gate::authorize('viewLogs', Notification::class);

        return response()->json([
            'success' => true,
            'data' => new NotificationTemplateResource($template),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function update(UpdateNotificationTemplateRequest $request, NotificationTemplate $template): JsonResponse
    {
        Gate::authorize('manageTemplates', Notification::class);

        $updated = $this->notificationService->updateTemplate($template, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => new NotificationTemplateResource($updated),
            'message' => 'Notification template updated successfully.',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function destroy(Request $request, NotificationTemplate $template): JsonResponse
    {
        Gate::authorize('manageTemplates', Notification::class);

        $this->notificationService->deleteTemplate($template, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Notification template archived.',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
