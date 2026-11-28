<?php

namespace App\Modules\Notification\Controllers\Api\V1;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Modules\Notification\Models\Alert;
use App\Modules\Notification\Models\AlertRule;
use App\Modules\Notification\Models\Notification;
use App\Modules\Notification\Models\NotificationDevice;
use App\Modules\Notification\Models\NotificationDispatch;
use App\Modules\Notification\Models\NotificationPreference;
use App\Modules\Notification\Models\NotificationTemplate;
use App\Modules\Notification\Requests\RegisterDeviceRequest;
use App\Modules\Notification\Requests\UpdatePreferencesRequest;
use App\Modules\Notification\Resources\AlertResource;
use App\Modules\Notification\Resources\NotificationDeviceResource;
use App\Modules\Notification\Resources\NotificationDispatchResource;
use App\Modules\Notification\Resources\NotificationPreferenceResource;
use App\Modules\Notification\Resources\NotificationResource;
use App\Modules\Notification\Resources\NotificationTemplateResource;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\UserManagement\Models\Organization;
use App\Modules\UserManagement\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class NotificationDashboardController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    public function summary(Request $request): JsonResponse
    {
        Gate::authorize('viewLogs', Notification::class);

        $summary = $this->notificationService->getDashboardSummary($request->user());
        $summary['recent_notifications'] = NotificationResource::collection($summary['recent_notifications']);
        $summary['recent_alerts'] = AlertResource::collection($summary['recent_alerts']);

        return response()->json([
            'success' => true,
            'data' => $summary,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function lookups(Request $request): JsonResponse
    {
        $actor = $request->user();

        $templates = NotificationTemplate::where('is_active', true)
            ->orderBy('category')
            ->orderBy('template_code')
            ->get();

        $canInspectDirectory = Gate::forUser($actor)->allows('viewLogs', Notification::class)
            || Gate::forUser($actor)->allows('sendBroadcast', Notification::class)
            || Gate::forUser($actor)->allows('manageAlertRules', Notification::class);

        $rules = $canInspectDirectory
            ? AlertRule::where('is_active', true)
                ->inJurisdiction($actor)
                ->orderBy('rule_code')
                ->get(['id', 'rule_code', 'name_bn', 'name_en', 'category', 'event_type', 'severity'])
            : collect();

        $adminQuery = AdministrativeUnit::where('is_active', true)
            ->orderBy('ancestry_path')
            ->orderBy('name_en');

        if (!$actor->isNationalScope()) {
            $paths = $actor->getAccessibleAncestryPaths();
            if (!in_array('/', $paths, true)) {
                $adminQuery->where(function ($q) use ($paths) {
                    foreach ($paths as $p) {
                        $q->orWhere('ancestry_path', 'like', rtrim($p, '/') . '/%');
                    }
                });
            }
        }

        $roles = $canInspectDirectory
            ? Role::where('is_active', true)
                ->orderBy('hierarchy_rank')
                ->get(['id', 'code', 'name_en', 'name_bn', 'tier_level'])
            : collect();

        $organizations = $canInspectDirectory
            ? Organization::where('is_active', true)
                ->inJurisdiction($actor)
                ->orderBy('name_en')
                ->get(['id', 'org_code', 'name_en', 'name_bn', 'tier_level'])
            : collect();

        $users = $canInspectDirectory
            ? User::query()
                ->where('is_active', true)
                ->inJurisdiction($actor)
                ->orderBy('full_name_en')
                ->limit(100)
                ->get(['id', 'full_name_en', 'full_name_bn', 'role_code', 'administrative_unit_id'])
            : collect();

        return response()->json([
            'success' => true,
            'data' => [
                'categories' => NotificationTemplate::CATEGORIES,
                'channels' => Notification::CHANNELS,
                'template_channels' => NotificationTemplate::CHANNELS,
                'severities' => Notification::SEVERITIES,
                'priorities' => Notification::PRIORITIES,
                'target_types' => Notification::TARGET_TYPES,
                'notification_statuses' => Notification::STATUSES,
                'dispatch_statuses' => NotificationDispatch::STATUSES,
                'alert_statuses' => Alert::STATUSES,
                'event_types' => AlertRule::EVENT_TYPES,
                'platforms' => NotificationDevice::PLATFORMS,
                'push_providers' => NotificationDevice::PUSH_PROVIDERS,
                'locked_emergency_categories' => NotificationPreference::CRITICAL_LOCKED_CATEGORIES,
                'templates' => NotificationTemplateResource::collection($templates),
                'alert_rules' => $rules,
                'roles' => $roles,
                'organizations' => $organizations,
                'administrative_units' => $adminQuery->get(['id', 'code', 'name_en', 'name_bn', 'tier_level', 'ancestry_path']),
                'users' => $users,
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function inbox(Request $request): JsonResponse
    {
        $paginator = $this->notificationService->listInbox($request->user(), $request->all());

        $unreadCount = NotificationDispatch::where('recipient_user_id', $request->user()->id)
            ->where('channel', 'IN_APP')
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'success' => true,
            'data' => NotificationDispatchResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'unread_count' => $unreadCount,
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function markRead(Request $request, NotificationDispatch $dispatch): JsonResponse
    {
        $updated = $this->notificationService->markInboxRead($dispatch, $request->user());

        return response()->json([
            'success' => true,
            'data' => new NotificationDispatchResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $count = $this->notificationService->markAllInboxRead($request->user());

        return response()->json([
            'success' => true,
            'data' => ['marked_read' => $count],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function preferences(Request $request): JsonResponse
    {
        $targetUser = null;
        if ($userId = $request->query('user_id')) {
            $targetUser = User::with('administrativeUnit')->findOrFail($userId);
        }

        $prefs = $this->notificationService->getPreferences($request->user(), $targetUser);

        return response()->json([
            'success' => true,
            'data' => NotificationPreferenceResource::collection($prefs),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function updatePreferences(UpdatePreferencesRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $targetUser = !empty($validated['user_id'])
            ? User::with('administrativeUnit')->findOrFail($validated['user_id'])
            : null;

        $prefs = $this->notificationService->updatePreferences(
            $request->user(),
            $validated['preferences'],
            $targetUser
        );

        return response()->json([
            'success' => true,
            'data' => NotificationPreferenceResource::collection($prefs),
            'message' => 'Notification preferences updated successfully.',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function devices(Request $request): JsonResponse
    {
        $devices = $this->notificationService->listDevices($request->user());

        return response()->json([
            'success' => true,
            'data' => NotificationDeviceResource::collection($devices),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function registerDevice(RegisterDeviceRequest $request): JsonResponse
    {
        $device = $this->notificationService->registerDevice($request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'data' => new NotificationDeviceResource($device),
            'message' => 'Mobile push device registered securely.',
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function deactivateDevice(Request $request, NotificationDevice $device): JsonResponse
    {
        $this->notificationService->deactivateDevice($request->user(), $device);

        return response()->json([
            'success' => true,
            'message' => 'Device registration deactivated.',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
