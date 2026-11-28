<?php

namespace App\Modules\UserManagement\Controllers\Api\V1;

use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Requests\StoreRoleRequest;
use App\Modules\UserManagement\Requests\SyncRolePermissionsRequest;
use App\Modules\UserManagement\Requests\UpdateRoleRequest;
use App\Modules\UserManagement\Resources\PermissionResource;
use App\Modules\UserManagement\Resources\RoleResource;
use App\Modules\UserManagement\Services\RolePermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class RoleController extends Controller
{
    public function __construct(
        protected RolePermissionService $roleService
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewRoles', Role::class);

        $query = Role::withCount(['users', 'permissions'])->with('permissions');

        if ($tier = $request->query('tier_level')) {
            $query->where('tier_level', strtoupper((string) $tier));
        }

        if ($request->filled('search')) {
            $term = '%' . trim((string) $request->query('search')) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('code', 'like', $term)
                    ->orWhere('name_en', 'like', $term)
                    ->orWhere('name_bn', 'like', $term);
            });
        }

        $roles = $query->orderBy('hierarchy_rank')->orderBy('name_en')->get();

        return response()->json([
            'success' => true,
            'data' => RoleResource::collection($roles),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = $this->roleService->createRole($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => new RoleResource($role->loadCount(['users', 'permissions'])),
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function show(Role $role): JsonResponse
    {
        Gate::authorize('viewRoles', Role::class);

        return response()->json([
            'success' => true,
            'data' => new RoleResource($role->load('permissions')->loadCount(['users', 'permissions'])),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $updated = $this->roleService->updateRole($role, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => new RoleResource($updated->loadCount(['users', 'permissions'])),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        Gate::authorize('manageRoleConfiguration', $role);

        $this->roleService->deleteRole($role, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Role deleted.',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function permissions(Request $request): JsonResponse
    {
        Gate::authorize('viewRoles', Role::class);

        $query = Permission::query();
        if ($module = $request->query('module_code')) {
            $query->where('module_code', strtolower((string) $module));
        }
        if ($action = $request->query('action_type')) {
            $query->where('action_type', strtoupper((string) $action));
        }

        $permissions = $query->orderBy('module_code')->orderBy('code')->get();

        return response()->json([
            'success' => true,
            'data' => PermissionResource::collection($permissions),
            'grouped_by_module' => $permissions->groupBy('module_code')->map(
                fn ($group) => PermissionResource::collection($group)
            ),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function syncPermissions(SyncRolePermissionsRequest $request, Role $role): JsonResponse
    {
        $updated = $this->roleService->syncRolePermissions(
            $role,
            $request->validated()['permissions'],
            $request->user()
        );

        return response()->json([
            'success' => true,
            'data' => new RoleResource($updated->loadCount(['users', 'permissions'])),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
