<?php

namespace App\Modules\Notification\Controllers\Api\V1;

use App\Modules\Notification\Models\Alert;
use App\Modules\Notification\Models\AlertRule;
use App\Modules\Notification\Models\Notification;
use App\Modules\Notification\Requests\AlertActionRequest;
use App\Modules\Notification\Requests\StoreAlertRuleRequest;
use App\Modules\Notification\Requests\TriggerAlertRequest;
use App\Modules\Notification\Requests\UpdateAlertRuleRequest;
use App\Modules\Notification\Resources\AlertResource;
use App\Modules\Notification\Resources\AlertRuleResource;
use App\Modules\Notification\Services\AlertEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class AlertController extends Controller
{
    public function __construct(
        protected AlertEngineService $alertEngine
    ) {}

    public function rules(Request $request): JsonResponse
    {
        Gate::authorize('viewLogs', Notification::class);

        $paginator = $this->alertEngine->listRules($request->user(), $request->all());

        return response()->json([
            'success' => true,
            'data' => AlertRuleResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function storeRule(StoreAlertRuleRequest $request): JsonResponse
    {
        Gate::authorize('manageAlertRules', Notification::class);

        $rule = $this->alertEngine->createRule($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => new AlertRuleResource($rule),
            'message' => 'Alert rule configured successfully.',
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function updateRule(UpdateAlertRuleRequest $request, AlertRule $rule): JsonResponse
    {
        Gate::authorize('manageAlertRules', Notification::class);
        Gate::authorize('viewRule', $rule);

        $updated = $this->alertEngine->updateRule($rule, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => new AlertRuleResource($updated),
            'message' => 'Alert rule updated successfully.',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function destroyRule(Request $request, AlertRule $rule): JsonResponse
    {
        Gate::authorize('manageAlertRules', Notification::class);
        Gate::authorize('viewRule', $rule);

        $this->alertEngine->deleteRule($rule, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Alert rule archived.',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function alerts(Request $request): JsonResponse
    {
        Gate::authorize('viewLogs', Notification::class);

        $paginator = $this->alertEngine->listAlerts($request->user(), $request->all());

        return response()->json([
            'success' => true,
            'data' => AlertResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function showAlert(Alert $alert): JsonResponse
    {
        Gate::authorize('viewAlert', $alert);

        $alert->load(['rule', 'administrativeUnit', 'acknowledgedByUser', 'resolvedByUser']);

        return response()->json([
            'success' => true,
            'data' => new AlertResource($alert),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function trigger(TriggerAlertRequest $request): JsonResponse
    {
        Gate::authorize('triggerAlert', Notification::class);

        $result = $this->alertEngine->triggerAlert($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => [
                'triggered' => $result['triggered'],
                'deduplicated' => $result['deduplicated'] ?? false,
                'reason' => $result['reason'] ?? null,
                'alert' => $result['alert'] ? new AlertResource($result['alert']) : null,
            ],
            'timestamp' => now()->toIso8601String(),
        ], $result['triggered'] ? 201 : 200);
    }

    public function acknowledge(AlertActionRequest $request, Alert $alert): JsonResponse
    {
        Gate::authorize('acknowledgeAlert', $alert);

        $updated = $this->alertEngine->acknowledgeAlert(
            $alert,
            $request->user(),
            $request->validated()['notes'] ?? null
        );

        return response()->json([
            'success' => true,
            'data' => new AlertResource($updated),
            'message' => 'Alert acknowledged successfully.',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function resolve(AlertActionRequest $request, Alert $alert): JsonResponse
    {
        Gate::authorize('acknowledgeAlert', $alert);

        $updated = $this->alertEngine->resolveAlert(
            $alert,
            $request->user(),
            $request->validated()['notes'] ?? null
        );

        return response()->json([
            'success' => true,
            'data' => new AlertResource($updated),
            'message' => 'Alert resolved successfully.',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function runEscalations(Request $request): JsonResponse
    {
        Gate::authorize('manageAlertRules', Notification::class);

        $result = $this->alertEngine->processPendingEscalations($request->user());

        return response()->json([
            'success' => true,
            'data' => $result,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
