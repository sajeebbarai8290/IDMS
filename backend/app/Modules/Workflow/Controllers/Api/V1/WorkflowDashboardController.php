<?php

namespace App\Modules\Workflow\Controllers\Api\V1;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Core\Services\AdministrativeScopeService;
use App\Modules\Workflow\Models\WorkflowDefinition;
use App\Modules\Workflow\Models\WorkflowInstance;
use App\Modules\Workflow\Models\WorkflowNotification;
use App\Modules\Workflow\Models\WorkflowStage;
use App\Modules\Workflow\Models\WorkflowStageAssignment;
use App\Modules\Workflow\Models\WorkflowTransition;
use App\Modules\Workflow\Policies\WorkflowPolicy;
use App\Modules\Workflow\Resources\WorkflowDefinitionResource;
use App\Modules\Workflow\Resources\WorkflowNotificationResource;
use App\Modules\Workflow\Services\WorkflowEngineService;
use App\Modules\Workflow\Services\WorkflowEntityIntegrationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class WorkflowDashboardController extends Controller
{
    public function __construct(
        protected WorkflowEngineService $engineService,
        protected WorkflowEntityIntegrationService $integrationService,
        protected AdministrativeScopeService $scopeService
    ) {}

    public function summary(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', WorkflowInstance::class);

        $filters = $request->only(['module_code']);

        return response()->json([
            'success' => true,
            'data' => $this->engineService->getDashboardSummary($request->user(), $filters),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function lookups(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', WorkflowInstance::class);

        $user = $request->user();

        $definitions = WorkflowDefinition::with('stages.assignments')
            ->where('is_active', true)
            ->orderBy('module_code')
            ->orderBy('name_en')
            ->get();

        $adminQuery = AdministrativeUnit::where('is_active', true)
            ->orderBy('ancestry_path')
            ->orderBy('name_en');

        if (!$user->isNationalScope()) {
            $path = rtrim($user->getAdministrativeScope()['ancestry_path'] ?? '/', '/') . '/';
            if ($path !== '/') {
                $adminQuery->where('ancestry_path', 'like', $path . '%');
            }
        }

        $usersQuery = User::where('is_active', true)->orderBy('full_name_en');
        if (!$user->isNationalScope()) {
            $path = rtrim($user->getAdministrativeScope()['ancestry_path'] ?? '/', '/') . '/';
            $unitIds = AdministrativeUnit::where('ancestry_path', 'like', $path . '%')->pluck('id');
            $usersQuery->whereIn('administrative_unit_id', $unitIds);
        }

        $roles = array_values(array_unique(array_merge(
            WorkflowPolicy::VIEW_ROLES,
            WorkflowPolicy::TRANSITION_ROLES
        )));

        return response()->json([
            'success' => true,
            'data' => [
                'definitions' => WorkflowDefinitionResource::collection($definitions),
                'modules' => WorkflowDefinition::MODULES,
                'entity_types' => WorkflowDefinition::ENTITY_TYPES,
                'approval_levels' => WorkflowStage::APPROVAL_LEVELS,
                'execution_modes' => WorkflowStage::EXECUTION_MODES,
                'assignee_types' => WorkflowStageAssignment::ASSIGNEE_TYPES,
                'authority_types' => WorkflowStageAssignment::AUTHORITY_TYPES,
                'states' => WorkflowInstance::STATES,
                'priorities' => WorkflowInstance::PRIORITIES,
                'actions' => WorkflowTransition::ACTIONS,
                'roles' => $roles,
                'administrative_units' => $adminQuery->get([
                    'id',
                    'parent_id',
                    'code',
                    'name_en',
                    'name_bn',
                    'tier_level',
                    'ancestry_path',
                ]),
                'users' => $usersQuery->get([
                    'id',
                    'full_name_en',
                    'full_name_bn',
                    'role_code',
                    'designation',
                    'administrative_unit_id',
                ]),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function targetEntities(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', WorkflowInstance::class);

        $request->validate([
            'entity_type' => ['required', 'string'],
            'search' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $items = $this->integrationService->searchTargetEntities(
            (string) $request->query('entity_type'),
            $request->user(),
            $request->query('search'),
            (int) $request->query('limit', 25)
        );

        return response()->json([
            'success' => true,
            'data' => $items,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function notifications(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', WorkflowInstance::class);

        $user = $request->user();
        $roles = $this->userRoles($user);

        $query = WorkflowNotification::with('instance:id,instance_no,title_en,title_bn,current_state')
            ->inJurisdiction($user)
            ->where(function ($q) use ($user, $roles) {
                $q->where('recipient_user_id', $user->id);
                if (!empty($roles)) {
                    $q->orWhereIn('recipient_role_code', $roles);
                }
            });

        if ($request->boolean('unread_only', false)) {
            $query->whereNull('read_at');
        }

        $unreadCount = (clone $query)->whereNull('read_at')->count();
        $items = $query->orderByDesc('created_at')->paginate((int) $request->query('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => WorkflowNotificationResource::collection($items->items()),
            'unread_count' => $unreadCount,
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function markNotificationRead(Request $request, WorkflowNotification $notification): JsonResponse
    {
        Gate::authorize('viewAny', WorkflowInstance::class);

        $user = $request->user();
        $roles = $this->userRoles($user);
        $unitPath = $notification->administrativeUnit?->ancestry_path ?? '/';

        $inScope = $user->isNationalScope() || $this->scopeService->canAccessScope($user, $unitPath);
        $isRecipient = $notification->recipient_user_id === $user->id
            || ($notification->recipient_role_code && in_array($notification->recipient_role_code, $roles, true));

        if (!$inScope || !$isRecipient) {
            throw new AuthorizationException('You are not authorized to mark this notification as read.');
        }

        if ($notification->read_at === null) {
            $notification->update([
                'read_at' => now(),
                'delivery_status' => WorkflowNotification::DELIVERY_READ,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => new WorkflowNotificationResource($notification->fresh('instance')),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function markAllNotificationsRead(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', WorkflowInstance::class);

        $user = $request->user();
        $roles = $this->userRoles($user);

        $updated = WorkflowNotification::inJurisdiction($user)
            ->whereNull('read_at')
            ->where(function ($q) use ($user, $roles) {
                $q->where('recipient_user_id', $user->id);
                if (!empty($roles)) {
                    $q->orWhereIn('recipient_role_code', $roles);
                }
            })
            ->update([
                'read_at' => now(),
                'delivery_status' => WorkflowNotification::DELIVERY_READ,
            ]);

        return response()->json([
            'success' => true,
            'marked_count' => $updated,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    protected function userRoles(User $user): array
    {
        return array_values(array_unique(array_filter(array_merge(
            [$user->role_code],
            $user->getJwtClaims()['roles'] ?? []
        ))));
    }

    public function runEscalations(Request $request): JsonResponse
    {
        Gate::authorize('runEscalations', WorkflowInstance::class);

        $result = $this->engineService->processOverdueEscalations($request->user());

        return response()->json([
            'success' => true,
            'data' => $result,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
