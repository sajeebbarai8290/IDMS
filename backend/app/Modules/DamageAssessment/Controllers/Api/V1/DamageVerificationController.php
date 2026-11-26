<?php

namespace App\Modules\DamageAssessment\Controllers\Api\V1;

use App\Modules\DamageAssessment\Models\DamageAssessment;
use App\Modules\DamageAssessment\Requests\DamageVerificationActionRequest;
use App\Modules\DamageAssessment\Resources\DamageAssessmentResource;
use App\Modules\DamageAssessment\Services\DamageAssessmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DamageVerificationController extends Controller
{
    public function __construct(
        protected DamageAssessmentService $damageService
    ) {}

    public function submit(DamageVerificationActionRequest $request, DamageAssessment $assessment): JsonResponse
    {
        Gate::authorize('submitAssessment', $assessment);

        $updated = $this->damageService->submitAssessment(
            $assessment,
            $request->user(),
            $request->validated('notes')
        );

        return response()->json([
            'success' => true,
            'data' => new DamageAssessmentResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function startVerification(DamageVerificationActionRequest $request, DamageAssessment $assessment): JsonResponse
    {
        Gate::authorize('verifyAssessment', $assessment);

        $updated = $this->damageService->startVerification(
            $assessment,
            $request->user(),
            $request->validated('notes')
        );

        return response()->json([
            'success' => true,
            'data' => new DamageAssessmentResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function requestCorrection(DamageVerificationActionRequest $request, DamageAssessment $assessment): JsonResponse
    {
        Gate::authorize('verifyAssessment', $assessment);

        $notes = trim((string) ($request->validated('notes') ?? $request->validated('reason') ?? ''));
        if ($notes === '') {
            throw ValidationException::withMessages([
                'notes' => ['Correction instructions are required when returning an assessment to the field officer.'],
            ]);
        }

        $updated = $this->damageService->requestCorrection($assessment, $request->user(), $notes);

        return response()->json([
            'success' => true,
            'data' => new DamageAssessmentResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function verify(DamageVerificationActionRequest $request, DamageAssessment $assessment): JsonResponse
    {
        Gate::authorize('verifyAssessment', $assessment);

        $updated = $this->damageService->verifyAssessment(
            $assessment,
            $request->user(),
            $request->validated('notes')
        );

        return response()->json([
            'success' => true,
            'data' => new DamageAssessmentResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function reject(DamageVerificationActionRequest $request, DamageAssessment $assessment): JsonResponse
    {
        Gate::authorize('verifyAssessment', $assessment);

        $reason = trim((string) ($request->validated('reason') ?? $request->validated('notes') ?? ''));
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => ['Rejection reason is required.'],
            ]);
        }

        $updated = $this->damageService->rejectAssessment($assessment, $request->user(), $reason);

        return response()->json([
            'success' => true,
            'data' => new DamageAssessmentResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function approve(DamageVerificationActionRequest $request, DamageAssessment $assessment): JsonResponse
    {
        Gate::authorize('approveAssessment', $assessment);

        $updated = $this->damageService->approveAssessment(
            $assessment,
            $request->user(),
            $request->validated('notes')
        );

        return response()->json([
            'success' => true,
            'data' => new DamageAssessmentResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
