<?php

namespace App\Modules\Notification\Controllers\Api\V1;

use App\Modules\Notification\Models\Notification;
use App\Modules\Notification\Models\NotificationDispatch;
use App\Modules\Notification\Requests\SendNotificationRequest;
use App\Modules\Notification\Resources\NotificationDispatchResource;
use App\Modules\Notification\Resources\NotificationResource;
use App\Modules\Notification\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class NotificationDispatchController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewLogs', Notification::class);

        $paginator = $this->notificationService->listNotifications($request->user(), $request->all());

        return response()->json([
            'success' => true,
            'data' => NotificationResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function store(SendNotificationRequest $request): JsonResponse
    {
        Gate::authorize('sendBroadcast', Notification::class);

        $notification = $this->notificationService->sendNotification($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => new NotificationResource($notification),
            'message' => $notification->status === Notification::STATUS_SCHEDULED
                ? 'Notification scheduled successfully.'
                : 'Notification queued for delivery.',
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function show(Notification $notification): JsonResponse
    {
        Gate::authorize('viewNotification', $notification);

        $notification->load([
            'template',
            'administrativeUnit',
            'creator',
            'dispatches' => fn ($q) => $q->with(['recipient', 'administrativeUnit'])->orderByDesc('created_at')->limit(50),
        ]);

        return response()->json([
            'success' => true,
            'data' => new NotificationResource($notification),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function dispatches(Request $request): JsonResponse
    {
        Gate::authorize('viewLogs', Notification::class);

        $paginator = $this->notificationService->listDispatches($request->user(), $request->all());

        return response()->json([
            'success' => true,
            'data' => NotificationDispatchResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function retryDispatch(Request $request, NotificationDispatch $dispatch): JsonResponse
    {
        Gate::authorize('sendBroadcast', Notification::class);
        Gate::authorize('viewDispatch', $dispatch);

        $retried = $this->notificationService->retryDispatch($dispatch, $request->user());

        return response()->json([
            'success' => true,
            'data' => new NotificationDispatchResource($retried),
            'message' => 'Failed notification dispatch queued for retry.',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function processScheduled(): JsonResponse
    {
        Gate::authorize('sendBroadcast', Notification::class);

        $count = $this->notificationService->processScheduledNotifications();

        return response()->json([
            'success' => true,
            'data' => ['dispatched' => $count],
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
