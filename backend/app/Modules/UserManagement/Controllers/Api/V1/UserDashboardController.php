<?php

namespace App\Modules\UserManagement\Controllers\Api\V1;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\AuditLog;
use App\Core\Models\User;
use App\Modules\UserManagement\Contracts\KeycloakAdminServiceInterface;
use App\Modules\UserManagement\Models\Organization;
use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\UserAdministrativeScope;
use App\Modules\UserManagement\Resources\OrganizationResource;
use App\Modules\UserManagement\Resources\RoleResource;
use App\Modules\UserManagement\Services\UserManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class UserDashboardController extends Controller
{
    public function __construct(
        protected UserManagementService $userService,
        protected KeycloakAdminServiceInterface $keycloakAdmin
    ) {}

    public function summary(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        return response()->json([
            'success' => true,
            'data' => $this->userService->getDashboardSummary($request->user()),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function lookups(Request $request): JsonResponse
    {
        $actor = $request->user();

        $rolesQuery = Role::where('is_active', true)->orderBy('hierarchy_rank')->orderBy('name_en');
        $orgsQuery = Organization::where('is_active', true)->inJurisdiction($actor)->orderBy('name_en');

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

        return response()->json([
            'success' => true,
            'data' => [
                'roles' => RoleResource::collection($rolesQuery->get()),
                'organizations' => OrganizationResource::collection($orgsQuery->get()),
                'administrative_units' => $adminQuery->get([
                    'id',
                    'parent_id',
                    'code',
                    'name_en',
                    'name_bn',
                    'tier_level',
                    'ancestry_path',
                ]),
                'account_statuses' => User::ACCOUNT_STATUSES,
                'idp_sync_statuses' => User::IDP_SYNC_STATUSES,
                'assignment_types' => UserAdministrativeScope::ASSIGNMENT_TYPES,
                'org_types' => Organization::ORG_TYPES,
                'tier_levels' => Role::TIER_LEVELS,
                'action_types' => Permission::ACTION_TYPES,
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function activityLogs(Request $request): JsonResponse
    {
        Gate::authorize('viewActivity', User::class);

        $actor = $request->user();
        $query = AuditLog::with(['user', 'administrativeUnit'])
            ->whereIn('auditable_type', [User::class, Role::class, Organization::class]);

        if (!$actor->isNationalScope()) {
            $paths = $actor->getAccessibleAncestryPaths();
            if (!in_array('/', $paths, true)) {
                $query->whereHas('administrativeUnit', function ($aq) use ($paths) {
                    $aq->where(function ($sub) use ($paths) {
                        foreach ($paths as $p) {
                            $sub->orWhere('ancestry_path', 'like', rtrim($p, '/') . '/%');
                        }
                    });
                });
            }
        }

        if ($action = $request->query('action_name')) {
            $query->where('action_name', $action);
        }

        if ($userId = $request->query('user_id')) {
            $query->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)->orWhere('auditable_id', $userId);
            });
        }

        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));
        $paginator = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => collect($paginator->items())->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'action_name' => $log->action_name,
                'auditable_type' => class_basename($log->auditable_type),
                'auditable_id' => $log->auditable_id,
                'actor' => $log->user ? [
                    'id' => $log->user->id,
                    'full_name_en' => $log->user->full_name_en,
                    'full_name_bn' => $log->user->full_name_bn,
                    'role_code' => $log->user->role_code,
                ] : null,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function keycloakHealth(): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        return response()->json([
            'success' => true,
            'data' => $this->keycloakAdmin->healthCheck(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
