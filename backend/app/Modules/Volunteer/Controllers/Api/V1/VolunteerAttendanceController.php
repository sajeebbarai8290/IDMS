<?php

namespace App\Modules\Volunteer\Controllers\Api\V1;

use App\Modules\Volunteer\Models\VolunteerAttendance;
use App\Modules\Volunteer\Models\VolunteerDeployment;
use App\Modules\Volunteer\Policies\VolunteerPolicy;
use App\Modules\Volunteer\Requests\CheckInVolunteerRequest;
use App\Modules\Volunteer\Requests\CheckOutVolunteerRequest;
use App\Modules\Volunteer\Resources\VolunteerAttendanceResource;
use App\Modules\Volunteer\Services\VolunteerManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VolunteerAttendanceController extends Controller
{
    public function __construct(
        protected VolunteerManagementService $volunteerService,
        protected VolunteerPolicy $policy
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', VolunteerAttendance::class);

        $query = VolunteerAttendance::inJurisdiction($request->user())
            ->with(['volunteer', 'deployment', 'administrativeUnit', 'confirmedBy']);

        if ($deploymentId = $request->query('deployment_id')) {
            $query->where('deployment_id', $deploymentId);
        }
        if ($volunteerId = $request->query('volunteer_id')) {
            $query->where('volunteer_id', $volunteerId);
        }
        if ($status = $request->query('confirmation_status')) {
            $query->where('confirmation_status', strtoupper((string) $status));
        }

        $perPage = min(100, max(5, (int) $request->query('per_page', 15)));
        $paginator = $query->orderByDesc('attendance_date')->orderByDesc('check_in_at')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => VolunteerAttendanceResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function checkIn(CheckInVolunteerRequest $request, VolunteerDeployment $deployment): JsonResponse
    {
        $attendance = $this->volunteerService->checkIn($deployment, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => new VolunteerAttendanceResource($attendance),
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function checkOut(CheckOutVolunteerRequest $request, VolunteerAttendance $attendance): JsonResponse
    {
        $updated = $this->volunteerService->checkOut($attendance, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => new VolunteerAttendanceResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function confirm(Request $request, VolunteerAttendance $attendance): JsonResponse
    {
        Gate::authorize('confirmAttendance', $attendance);

        $validated = $request->validate([
            'confirmation_status' => ['required', 'string', 'in:CONFIRMED,DISPUTED'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $updated = $this->volunteerService->confirmAttendance(
            $attendance,
            $validated['confirmation_status'],
            $validated['remarks'] ?? null,
            $request->user()
        );

        return response()->json([
            'success' => true,
            'data' => new VolunteerAttendanceResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function uploadEvidence(Request $request, VolunteerAttendance $attendance): JsonResponse
    {
        Gate::authorize('recordAttendance', $attendance->deployment);

        $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        $updated = $this->volunteerService->uploadAttendanceEvidence($attendance, $request->file('file'), $request->user());

        return response()->json([
            'success' => true,
            'data' => new VolunteerAttendanceResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function downloadEvidence(Request $request, VolunteerAttendance $attendance): StreamedResponse|JsonResponse
    {
        Gate::authorize('viewDeployment', $attendance->deployment);
        if ($this->policy->isExternalPartnerOnly($request->user())) {
            return response()->json(['success' => false, 'message' => 'External partners cannot download attendance evidence.'], 403);
        }

        if (!$attendance->evidence_path || !Storage::disk('local')->exists($attendance->evidence_path)) {
            return response()->json(['success' => false, 'message' => 'Evidence file not found.'], 404);
        }

        return Storage::disk('local')->response(
            $attendance->evidence_path,
            $attendance->evidence_original_name ?: 'attendance-evidence'
        );
    }
}
