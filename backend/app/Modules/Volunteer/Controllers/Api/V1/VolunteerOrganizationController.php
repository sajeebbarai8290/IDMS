<?php

namespace App\Modules\Volunteer\Controllers\Api\V1;

use App\Modules\Volunteer\Models\VolunteerOrganization;
use App\Modules\Volunteer\Requests\StoreVolunteerOrganizationRequest;
use App\Modules\Volunteer\Resources\VolunteerOrganizationResource;
use App\Modules\Volunteer\Services\VolunteerManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class VolunteerOrganizationController extends Controller
{
    public function __construct(
        protected VolunteerManagementService $volunteerService
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', VolunteerOrganization::class);

        $query = VolunteerOrganization::inJurisdiction($request->user())
            ->with(['administrativeUnit', 'coverages.administrativeUnit'])
            ->withCount('volunteers');

        if ($orgType = $request->query('org_type')) {
            $query->where('org_type', strtoupper((string) $orgType));
        }
        if ($status = $request->query('verification_status')) {
            $query->where('verification_status', strtoupper((string) $status));
        }
        if ($search = trim((string) $request->query('search', ''))) {
            $likeOp = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $likeOp) {
                $q->where('org_code', $likeOp, "%{$search}%")
                    ->orWhere('name_en', $likeOp, "%{$search}%")
                    ->orWhere('name_bn', 'like', "%{$search}%");
            });
        }

        $perPage = min(100, max(5, (int) $request->query('per_page', 15)));
        $paginator = $query->orderBy('name_en')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => VolunteerOrganizationResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function store(StoreVolunteerOrganizationRequest $request): JsonResponse
    {
        $org = $this->volunteerService->createOrganization($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => new VolunteerOrganizationResource($org),
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function show(VolunteerOrganization $organization): JsonResponse
    {
        Gate::authorize('viewOrganization', $organization);
        $organization->load(['administrativeUnit', 'coverages.administrativeUnit'])->loadCount('volunteers');

        return response()->json([
            'success' => true,
            'data' => new VolunteerOrganizationResource($organization),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function update(StoreVolunteerOrganizationRequest $request, VolunteerOrganization $organization): JsonResponse
    {
        Gate::authorize('viewOrganization', $organization);
        $updated = $this->volunteerService->updateOrganization($organization, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => new VolunteerOrganizationResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function destroy(VolunteerOrganization $organization): JsonResponse
    {
        Gate::authorize('manageOrganization', VolunteerOrganization::class);
        Gate::authorize('viewOrganization', $organization);
        $organization->delete();
        $this->volunteerService->bumpDashboardCacheVersion();

        return response()->json([
            'success' => true,
            'message' => 'Volunteer organization archived.',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function verify(Request $request, VolunteerOrganization $organization): JsonResponse
    {
        Gate::authorize('verifyOrganization', $organization);

        $validated = $request->validate([
            'verification_status' => ['required', 'string', 'in:VERIFIED,REJECTED'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $updated = $this->volunteerService->verifyOrganization(
            $organization,
            $validated['verification_status'],
            $validated['remarks'] ?? null,
            $request->user()
        );

        return response()->json([
            'success' => true,
            'data' => new VolunteerOrganizationResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
