<?php

namespace App\Modules\UserManagement\Controllers\Api\V1;

use App\Core\Models\AuditLog;
use App\Modules\UserManagement\Models\Organization;
use App\Modules\UserManagement\Requests\StoreOrganizationRequest;
use App\Modules\UserManagement\Requests\UpdateOrganizationRequest;
use App\Modules\UserManagement\Resources\OrganizationResource;
use App\Modules\UserManagement\Services\UserManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class OrganizationController extends Controller
{
    public function __construct(
        protected UserManagementService $userService
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewOrganizations', Organization::class);

        $query = Organization::inJurisdiction($request->user())
            ->with(['administrativeUnit', 'parentOrganization'])
            ->withCount('users');

        if ($orgType = $request->query('org_type')) {
            $query->where('org_type', strtoupper((string) $orgType));
        }

        if ($tier = $request->query('tier_level')) {
            $query->where('tier_level', strtoupper((string) $tier));
        }

        if ($request->filled('search')) {
            $term = '%' . trim((string) $request->query('search')) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('org_code', 'like', $term)
                    ->orWhere('name_en', 'like', $term)
                    ->orWhere('name_bn', 'like', $term);
            });
        }

        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));
        $paginator = $query->orderBy('name_en')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => OrganizationResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function store(StoreOrganizationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $actor = $request->user();

        if (!empty($data['administrative_unit_id'])) {
            $this->userService->assertUnitInActorScope($actor, $data['administrative_unit_id'], 'organization unit');
        }

        $org = Organization::create([
            'id' => (string) Str::uuid(),
            'org_code' => strtoupper(trim((string) $data['org_code'])),
            'name_en' => $data['name_en'],
            'name_bn' => $data['name_bn'] ?? null,
            'org_type' => strtoupper((string) $data['org_type']),
            'tier_level' => strtoupper((string) $data['tier_level']),
            'administrative_unit_id' => $data['administrative_unit_id'] ?? null,
            'parent_organization_id' => $data['parent_organization_id'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        AuditLog::log(
            'ORGANIZATION_CREATED',
            Organization::class,
            $org->id,
            null,
            ['org_code' => $org->org_code, 'org_type' => $org->org_type],
            $actor,
            $org->administrative_unit_id
        );

        $this->userService->bumpDashboardCacheVersion();

        return response()->json([
            'success' => true,
            'data' => new OrganizationResource($org->load(['administrativeUnit', 'parentOrganization'])),
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function show(Organization $organization): JsonResponse
    {
        Gate::authorize('viewOrganization', $organization);

        return response()->json([
            'success' => true,
            'data' => new OrganizationResource(
                $organization->load(['administrativeUnit', 'parentOrganization'])->loadCount('users')
            ),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function update(UpdateOrganizationRequest $request, Organization $organization): JsonResponse
    {
        $data = $request->validated();
        $actor = $request->user();

        if (!empty($data['administrative_unit_id'])) {
            $this->userService->assertUnitInActorScope($actor, $data['administrative_unit_id'], 'organization unit');
        }

        $oldValues = ['name_en' => $organization->name_en, 'org_type' => $organization->org_type, 'is_active' => $organization->is_active];
        $organization->update(array_merge($data, [
            'org_code' => isset($data['org_code']) ? strtoupper(trim((string) $data['org_code'])) : $organization->org_code,
            'updated_by' => $actor->id,
        ]));

        AuditLog::log(
            'ORGANIZATION_UPDATED',
            Organization::class,
            $organization->id,
            $oldValues,
            ['name_en' => $organization->name_en, 'org_type' => $organization->org_type, 'is_active' => $organization->is_active],
            $actor,
            $organization->administrative_unit_id
        );

        $this->userService->bumpDashboardCacheVersion();

        return response()->json([
            'success' => true,
            'data' => new OrganizationResource($organization->fresh(['administrativeUnit', 'parentOrganization'])),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function destroy(Request $request, Organization $organization): JsonResponse
    {
        Gate::authorize('manageOrganizations', $organization);

        if ($organization->users()->exists()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ORGANIZATION_IN_USE',
                    'message' => 'Cannot archive an organization that still has assigned users.',
                ],
                'timestamp' => now()->toIso8601String(),
            ], 422);
        }

        $organization->delete();
        $this->userService->bumpDashboardCacheVersion();

        return response()->json([
            'success' => true,
            'message' => 'Organization archived.',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
