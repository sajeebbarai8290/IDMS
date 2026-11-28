<?php

namespace App\Modules\UserManagement\Controllers\Api\V1;

use App\Core\Models\AuditLog;
use App\Core\Models\User;
use App\Modules\UserManagement\Models\UserAdministrativeScope;
use App\Modules\UserManagement\Requests\AssignUserRolesRequest;
use App\Modules\UserManagement\Requests\AssignUserScopeRequest;
use App\Modules\UserManagement\Requests\ChangeUserStatusRequest;
use App\Modules\UserManagement\Requests\StoreUserRequest;
use App\Modules\UserManagement\Requests\UpdateUserRequest;
use App\Modules\UserManagement\Resources\UserAdministrativeScopeResource;
use App\Modules\UserManagement\Resources\UserResource;
use App\Modules\UserManagement\Services\UserManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserController extends Controller
{
    public function __construct(
        protected UserManagementService $userService
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        $actor = $request->user();
        $query = User::query()
            ->inJurisdiction($actor)
            ->with(['organization', 'administrativeUnit', 'roles']);

        $this->userService->applyUserFilters($query, $request->only([
            'account_status',
            'is_active',
            'role_code',
            'organization_id',
            'administrative_unit_id',
            'tier_level',
            'idp_sync_status',
            'mfa_enforced',
            'search',
        ]));

        $sortBy = $request->query('sort_by', 'created_at');
        if (!in_array($sortBy, ['created_at', 'full_name_en', 'email', 'employee_id', 'account_status', 'last_login_at'], true)) {
            $sortBy = 'created_at';
        }
        $sortDir = strtolower((string) $request->query('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $perPage = min(100, max(1, (int) $request->query('per_page', 15)));
        $paginator = $query->orderBy($sortBy, $sortDir)->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => UserResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->userService->createUser($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => new UserResource($user),
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function show(User $user): JsonResponse
    {
        Gate::authorize('viewUser', $user);

        return response()->json([
            'success' => true,
            'data' => new UserResource($user->load($this->userService->userRelations())),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $updated = $this->userService->updateUser($user, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => new UserResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        Gate::authorize('deleteUser', $user);

        $this->userService->transitionLifecycleStatus(
            $user,
            User::STATUS_DEACTIVATED,
            $request->input('reason', 'Account archived by administrator.'),
            $request->user()
        );
        $user->delete();
        $this->userService->bumpDashboardCacheVersion();

        return response()->json([
            'success' => true,
            'message' => 'User account deactivated and archived.',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function changeStatus(ChangeUserStatusRequest $request, User $user): JsonResponse
    {
        $validated = $request->validated();
        $updated = $this->userService->transitionLifecycleStatus(
            $user,
            $validated['account_status'],
            $validated['reason'] ?? null,
            $request->user(),
            $validated['sync_with_keycloak'] ?? true
        );

        return response()->json([
            'success' => true,
            'data' => new UserResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function assignRoles(AssignUserRolesRequest $request, User $user): JsonResponse
    {
        $validated = $request->validated();
        $updated = $this->userService->assignUserRoles(
            $user,
            $validated,
            $request->user(),
            $validated['sync_with_keycloak'] ?? true
        );

        return response()->json([
            'success' => true,
            'data' => new UserResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function assignScope(AssignUserScopeRequest $request, User $user): JsonResponse
    {
        $scope = $this->userService->assignAdministrativeScope($user, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => new UserAdministrativeScopeResource($scope),
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function revokeScope(Request $request, User $user, UserAdministrativeScope $scope): JsonResponse
    {
        Gate::authorize('assignScope', $user);

        $this->userService->revokeAdministrativeScope($user, $scope, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Organizational scope assignment revoked.',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function syncKeycloak(Request $request, User $user): JsonResponse
    {
        Gate::authorize('syncKeycloak', $user);

        $enforceMfaOnly = (bool) $request->boolean('enforce_mfa_only', false);
        if ($request->has('mfa_enforced')) {
            $user->update(['mfa_enforced' => (bool) $request->boolean('mfa_enforced')]);
        }

        $result = $this->userService->syncWithKeycloak($user->fresh(), $request->user(), $enforceMfaOnly);

        return response()->json([
            'success' => $result['success'],
            'sync_result' => $result,
            'data' => new UserResource($user->fresh($this->userService->userRelations())),
            'timestamp' => now()->toIso8601String(),
        ], $result['success'] ? 200 : 502);
    }

    public function uploadPhoto(Request $request, User $user): JsonResponse
    {
        Gate::authorize('updateUser', $user);

        $request->validate([
            'photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $updated = $this->userService->uploadPhoto($user, $request->file('photo'), $request->user());

        return response()->json([
            'success' => true,
            'data' => new UserResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function downloadPhoto(User $user): StreamedResponse
    {
        Gate::authorize('viewUser', $user);

        return $this->userService->streamPhoto($user);
    }

    public function activity(Request $request, User $user): JsonResponse
    {
        Gate::authorize('viewActivity', [User::class, $user]);

        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));
        $paginator = AuditLog::with('user')
            ->where(function ($q) use ($user) {
                $q->where('auditable_id', $user->id)
                    ->orWhere('user_id', $user->id);
            })
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => collect($paginator->items())->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'action_name' => $log->action_name,
                'auditable_type' => class_basename($log->auditable_type),
                'auditable_id' => $log->auditable_id,
                'actor_name' => $log->user?->full_name_en ?? 'System',
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
}
