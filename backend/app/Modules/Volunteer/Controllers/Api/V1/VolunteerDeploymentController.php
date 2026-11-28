<?php

namespace App\Modules\Volunteer\Controllers\Api\V1;

use App\Modules\Volunteer\Models\VolunteerDeployment;
use App\Modules\Volunteer\Requests\StoreVolunteerDeploymentRequest;
use App\Modules\Volunteer\Resources\VolunteerDeploymentResource;
use App\Modules\Volunteer\Services\VolunteerManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class VolunteerDeploymentController extends Controller
{
    public function __construct(
        protected VolunteerManagementService $volunteerService
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', VolunteerDeployment::class);

        $query = VolunteerDeployment::inJurisdiction($request->user())
            ->with(['volunteer.organization', 'disasterEvent', 'responseOperation', 'administrativeUnit']);

        if ($status = $request->query('deployment_status')) {
            $query->where('deployment_status', strtoupper((string) $status));
        }
        if ($volunteerId = $request->query('volunteer_id')) {
            $query->where('volunteer_id', $volunteerId);
        }
        if ($disasterId = $request->query('disaster_event_id')) {
            $query->where('disaster_event_id', $disasterId);
        }
        if ($opId = $request->query('response_operation_id')) {
            $query->where('response_operation_id', $opId);
        }
        if ($search = trim((string) $request->query('search', ''))) {
            $likeOp = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $likeOp) {
                $q->where('deployment_no', $likeOp, "%{$search}%")
                    ->orWhere('assigned_role', $likeOp, "%{$search}%")
                    ->orWhere('deployment_location', $likeOp, "%{$search}%");
            });
        }

        $perPage = min(100, max(5, (int) $request->query('per_page', 15)));
        $paginator = $query->orderByDesc('start_date')->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => VolunteerDeploymentResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function store(StoreVolunteerDeploymentRequest $request): JsonResponse
    {
        $deployment = $this->volunteerService->deployVolunteer($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => new VolunteerDeploymentResource($deployment),
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function show(VolunteerDeployment $deployment): JsonResponse
    {
        Gate::authorize('viewDeployment', $deployment);
        $deployment->load($this->volunteerService->deploymentRelations());

        return response()->json([
            'success' => true,
            'data' => new VolunteerDeploymentResource($deployment),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function activate(Request $request, VolunteerDeployment $deployment): JsonResponse
    {
        Gate::authorize('manageDeployment', $deployment);
        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:1500']]);

        $updated = $this->volunteerService->transitionDeployment(
            $deployment,
            VolunteerDeployment::STATUS_ACTIVE,
            $validated,
            $request->user()
        );

        return response()->json([
            'success' => true,
            'data' => new VolunteerDeploymentResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function complete(Request $request, VolunteerDeployment $deployment): JsonResponse
    {
        Gate::authorize('manageDeployment', $deployment);
        $validated = $request->validate([
            'actual_end_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1500'],
        ]);

        $updated = $this->volunteerService->transitionDeployment(
            $deployment,
            VolunteerDeployment::STATUS_COMPLETED,
            $validated,
            $request->user()
        );

        return response()->json([
            'success' => true,
            'data' => new VolunteerDeploymentResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function cancel(Request $request, VolunteerDeployment $deployment): JsonResponse
    {
        Gate::authorize('manageDeployment', $deployment);
        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:1500']]);

        $updated = $this->volunteerService->transitionDeployment(
            $deployment,
            VolunteerDeployment::STATUS_CANCELLED,
            $validated,
            $request->user()
        );

        return response()->json([
            'success' => true,
            'data' => new VolunteerDeploymentResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
