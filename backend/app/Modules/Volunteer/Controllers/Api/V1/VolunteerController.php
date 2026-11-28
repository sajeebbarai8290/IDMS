<?php

namespace App\Modules\Volunteer\Controllers\Api\V1;

use App\Modules\Volunteer\Models\Volunteer;
use App\Modules\Volunteer\Policies\VolunteerPolicy;
use App\Modules\Volunteer\Requests\StoreVolunteerRequest;
use App\Modules\Volunteer\Requests\UpdateVolunteerRequest;
use App\Modules\Volunteer\Resources\VolunteerResource;
use App\Modules\Volunteer\Services\VolunteerManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VolunteerController extends Controller
{
    public function __construct(
        protected VolunteerManagementService $volunteerService,
        protected VolunteerPolicy $policy
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Volunteer::class);

        $user = $request->user();
        $query = Volunteer::inJurisdiction($user)
            ->with(['administrativeUnit', 'organization', 'primaryCategory', 'skillAssignments.skill']);

        if ($this->policy->isExternalPartnerOnly($user)) {
            $query->where('overall_verification_status', Volunteer::VERIFICATION_STATUS_VERIFIED);
        }

        $this->volunteerService->applyVolunteerFilters($query, $request->only([
            'administrative_unit_id',
            'organization_id',
            'primary_category_id',
            'skill_id',
            'overall_verification_status',
            'availability_status',
            'search',
        ]));

        $sortBy = $request->query('sort_by', 'created_at');
        if (!in_array($sortBy, ['created_at', 'volunteer_code', 'full_name_en', 'availability_status'], true)) {
            $sortBy = 'created_at';
        }
        $sortDir = strtolower((string) $request->query('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $perPage = min(100, max(1, (int) $request->query('per_page', 15)));
        $paginator = $query->orderBy($sortBy, $sortDir)->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => VolunteerResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function store(StoreVolunteerRequest $request): JsonResponse
    {
        $volunteer = $this->volunteerService->registerVolunteer($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => new VolunteerResource($volunteer),
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function show(Volunteer $volunteer): JsonResponse
    {
        Gate::authorize('viewVolunteer', $volunteer);
        $volunteer->load($this->volunteerService->volunteerRelations());

        return response()->json([
            'success' => true,
            'data' => new VolunteerResource($volunteer),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function update(UpdateVolunteerRequest $request, Volunteer $volunteer): JsonResponse
    {
        $updated = $this->volunteerService->updateVolunteer($volunteer, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => new VolunteerResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function destroy(Volunteer $volunteer): JsonResponse
    {
        Gate::authorize('deleteVolunteer', $volunteer);
        $volunteer->delete();
        $this->volunteerService->bumpDashboardCacheVersion();

        return response()->json([
            'success' => true,
            'message' => 'Volunteer record archived.',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function uploadPhoto(Request $request, Volunteer $volunteer): JsonResponse
    {
        Gate::authorize('updateVolunteer', $volunteer);

        $request->validate([
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $updated = $this->volunteerService->uploadPhoto($volunteer, $request->file('photo'), $request->user());

        return response()->json([
            'success' => true,
            'data' => new VolunteerResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function downloadPhoto(Request $request, Volunteer $volunteer): StreamedResponse|JsonResponse
    {
        Gate::authorize('viewVolunteer', $volunteer);
        if ($this->policy->isExternalPartnerOnly($request->user())) {
            return response()->json(['success' => false, 'message' => 'External partners cannot access volunteer photos.'], 403);
        }

        if (!$volunteer->photo_path || !Storage::disk('local')->exists($volunteer->photo_path)) {
            return response()->json(['success' => false, 'message' => 'Volunteer photo not found.'], 404);
        }

        return Storage::disk('local')->response($volunteer->photo_path, $volunteer->photo_original_name ?: 'volunteer-photo.jpg');
    }

    public function revealPii(Request $request, Volunteer $volunteer): JsonResponse
    {
        Gate::authorize('decryptPii', $volunteer);

        return response()->json([
            'success' => true,
            'data' => $this->volunteerService->revealPii($volunteer, $request->user()),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function syncSkills(Request $request, Volunteer $volunteer): JsonResponse
    {
        Gate::authorize('updateVolunteer', $volunteer);

        $validated = $request->validate([
            'skills' => ['required', 'array'],
            'skills.*.skill_id' => ['required', 'uuid'],
            'skills.*.proficiency_level' => ['nullable', 'string', 'in:BASIC,INTERMEDIATE,ADVANCED,INSTRUCTOR'],
            'skills.*.certification_level' => ['nullable', 'string', 'max:60'],
            'skills.*.certified_by' => ['nullable', 'string', 'max:150'],
            'skills.*.issued_at' => ['nullable', 'date'],
            'skills.*.expires_at' => ['nullable', 'date'],
        ]);

        $updated = $this->volunteerService->syncSkills($volunteer, $validated['skills'], $request->user());

        return response()->json([
            'success' => true,
            'data' => new VolunteerResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function submit(Request $request, Volunteer $volunteer): JsonResponse
    {
        Gate::authorize('submitVolunteer', $volunteer);

        $validated = $request->validate(['remarks' => ['nullable', 'string', 'max:1000']]);
        $updated = $this->volunteerService->submitForVerification($volunteer, $validated['remarks'] ?? null, $request->user());

        return response()->json([
            'success' => true,
            'data' => new VolunteerResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function verifyPillar(Request $request, Volunteer $volunteer): JsonResponse
    {
        Gate::authorize('verifyOrApproveVolunteer', $volunteer);

        $validated = $request->validate([
            'pillar' => ['required', 'string', 'in:IDENTITY,ORGANIZATION,TRAINING'],
            'status' => ['required', 'string', 'in:PENDING,VERIFIED,REJECTED'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $updated = $this->volunteerService->verifyPillar(
            $volunteer,
            $validated['pillar'],
            $validated['status'],
            $validated['remarks'] ?? null,
            $request->user()
        );

        return response()->json([
            'success' => true,
            'data' => new VolunteerResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function approve(Request $request, Volunteer $volunteer): JsonResponse
    {
        Gate::authorize('verifyOrApproveVolunteer', $volunteer);

        $validated = $request->validate(['remarks' => ['nullable', 'string', 'max:1000']]);
        $updated = $this->volunteerService->approveVolunteer($volunteer, $validated['remarks'] ?? null, $request->user());

        return response()->json([
            'success' => true,
            'data' => new VolunteerResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function reject(Request $request, Volunteer $volunteer): JsonResponse
    {
        Gate::authorize('verifyOrApproveVolunteer', $volunteer);

        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $updated = $this->volunteerService->rejectVolunteer($volunteer, $validated['reason'], $request->user());

        return response()->json([
            'success' => true,
            'data' => new VolunteerResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
