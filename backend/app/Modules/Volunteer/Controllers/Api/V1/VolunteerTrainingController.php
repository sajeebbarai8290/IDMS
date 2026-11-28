<?php

namespace App\Modules\Volunteer\Controllers\Api\V1;

use App\Modules\Volunteer\Models\VolunteerTrainingEnrollment;
use App\Modules\Volunteer\Models\VolunteerTrainingProgram;
use App\Modules\Volunteer\Models\VolunteerTrainingSession;
use App\Modules\Volunteer\Policies\VolunteerPolicy;
use App\Modules\Volunteer\Requests\StoreTrainingProgramRequest;
use App\Modules\Volunteer\Requests\StoreTrainingSessionRequest;
use App\Modules\Volunteer\Resources\VolunteerTrainingProgramResource;
use App\Modules\Volunteer\Resources\VolunteerTrainingSessionResource;
use App\Modules\Volunteer\Services\VolunteerManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VolunteerTrainingController extends Controller
{
    public function __construct(
        protected VolunteerManagementService $volunteerService,
        protected VolunteerPolicy $policy
    ) {}

    public function programs(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', VolunteerTrainingProgram::class);

        $query = VolunteerTrainingProgram::with(['category', 'skill'])->withCount('sessions');
        if ($request->boolean('active_only', false)) {
            $query->where('is_active', true);
        }

        return response()->json([
            'success' => true,
            'data' => VolunteerTrainingProgramResource::collection($query->orderBy('title_en')->get()),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function storeProgram(StoreTrainingProgramRequest $request): JsonResponse
    {
        $program = $this->volunteerService->createTrainingProgram($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => new VolunteerTrainingProgramResource($program),
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function updateProgram(StoreTrainingProgramRequest $request, VolunteerTrainingProgram $program): JsonResponse
    {
        $program->update(collect($request->validated())->only([
            'title_en',
            'title_bn',
            'category_id',
            'skill_id',
            'provider_organization_id',
            'provider_name',
            'duration_hours',
            'validity_months',
            'description',
            'is_active',
        ])->toArray());

        return response()->json([
            'success' => true,
            'data' => new VolunteerTrainingProgramResource($program->fresh(['category', 'skill'])),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function sessions(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', VolunteerTrainingSession::class);

        $query = VolunteerTrainingSession::inJurisdiction($request->user())
            ->with(['program', 'administrativeUnit', 'enrollments.volunteer'])
            ->withCount('enrollments');

        if ($status = $request->query('status')) {
            $query->where('status', strtoupper((string) $status));
        }
        if ($programId = $request->query('program_id')) {
            $query->where('program_id', $programId);
        }

        $perPage = min(100, max(5, (int) $request->query('per_page', 15)));
        $paginator = $query->orderByDesc('start_date')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => VolunteerTrainingSessionResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function storeSession(StoreTrainingSessionRequest $request): JsonResponse
    {
        $session = $this->volunteerService->createTrainingSession($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => new VolunteerTrainingSessionResource($session),
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function showSession(VolunteerTrainingSession $session): JsonResponse
    {
        Gate::authorize('viewTrainingSession', $session);
        $session->load(['program', 'administrativeUnit', 'enrollments.volunteer']);

        return response()->json([
            'success' => true,
            'data' => new VolunteerTrainingSessionResource($session),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function updateSession(StoreTrainingSessionRequest $request, VolunteerTrainingSession $session): JsonResponse
    {
        Gate::authorize('viewTrainingSession', $session);
        $session->update(collect($request->validated())->only([
            'venue',
            'start_date',
            'end_date',
            'trainer_name',
            'trainer_contact',
            'max_participants',
            'status',
            'notes',
        ])->toArray());

        return response()->json([
            'success' => true,
            'data' => new VolunteerTrainingSessionResource($session->fresh(['program', 'administrativeUnit', 'enrollments.volunteer'])),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function enroll(Request $request, VolunteerTrainingSession $session): JsonResponse
    {
        Gate::authorize('manageTrainingSession', VolunteerTrainingSession::class);
        Gate::authorize('viewTrainingSession', $session);

        $validated = $request->validate([
            'volunteer_id' => ['required', 'uuid'],
        ]);

        $enrollment = $this->volunteerService->enrollVolunteer($session, $validated['volunteer_id'], $request->user());

        return response()->json([
            'success' => true,
            'data' => $enrollment,
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function updateEnrollment(Request $request, VolunteerTrainingEnrollment $enrollment): JsonResponse
    {
        Gate::authorize('manageTrainingSession', VolunteerTrainingSession::class);
        Gate::authorize('viewTrainingSession', $enrollment->session);

        $validated = $request->validate([
            'enrollment_status' => ['sometimes', 'string', 'in:ENROLLED,ATTENDED,ABSENT,DROPPED'],
            'attendance_hours' => ['sometimes', 'numeric', 'min:0', 'max:2000'],
            'assessment_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'passed' => ['sometimes', 'boolean'],
            'certificate_no' => ['nullable', 'string', 'max:60'],
            'issued_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $updated = $this->volunteerService->updateTrainingEnrollment($enrollment, $validated, $request->user());

        return response()->json([
            'success' => true,
            'data' => $updated,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function uploadCertificate(Request $request, VolunteerTrainingEnrollment $enrollment): JsonResponse
    {
        Gate::authorize('manageTrainingSession', VolunteerTrainingSession::class);
        Gate::authorize('viewTrainingSession', $enrollment->session);

        $request->validate([
            'certificate' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $updated = $this->volunteerService->uploadTrainingCertificate($enrollment, $request->file('certificate'), $request->user());

        return response()->json([
            'success' => true,
            'data' => $updated,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function downloadCertificate(Request $request, VolunteerTrainingEnrollment $enrollment): StreamedResponse|JsonResponse
    {
        Gate::authorize('viewTrainingSession', $enrollment->session);
        if ($this->policy->isExternalPartnerOnly($request->user())) {
            return response()->json(['success' => false, 'message' => 'External partners cannot download volunteer certificates.'], 403);
        }

        if (!$enrollment->certificate_path || !Storage::disk('local')->exists($enrollment->certificate_path)) {
            return response()->json(['success' => false, 'message' => 'Certificate file not found.'], 404);
        }

        return Storage::disk('local')->response(
            $enrollment->certificate_path,
            $enrollment->certificate_original_name ?: 'volunteer-certificate.pdf'
        );
    }
}
