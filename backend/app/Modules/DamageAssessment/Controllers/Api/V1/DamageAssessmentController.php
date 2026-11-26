<?php

namespace App\Modules\DamageAssessment\Controllers\Api\V1;

use App\Modules\DamageAssessment\Models\DamageAssessment;
use App\Modules\DamageAssessment\Models\DamageAttachment;
use App\Modules\DamageAssessment\Policies\DamageAssessmentPolicy;
use App\Modules\DamageAssessment\Requests\StoreDamageAssessmentRequest;
use App\Modules\DamageAssessment\Requests\StoreDamageAttachmentRequest;
use App\Modules\DamageAssessment\Requests\StoreDamageBoundaryRequest;
use App\Modules\DamageAssessment\Requests\SyncDamageAssessmentsRequest;
use App\Modules\DamageAssessment\Resources\DamageAssessmentResource;
use App\Modules\DamageAssessment\Resources\DamageAttachmentResource;
use App\Modules\DamageAssessment\Services\DamageAssessmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DamageAssessmentController extends Controller
{
    public function __construct(
        protected DamageAssessmentService $damageService,
        protected DamageAssessmentPolicy $policy
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', DamageAssessment::class);

        $user = $request->user();
        $query = DamageAssessment::inJurisdiction($user)
            ->with(['disasterEvent', 'administrativeUnit', 'sectorItems.category']);

        if ($this->policy->isExternalPartnerOnly($user)) {
            $query->whereIn('verification_status', DamageAssessment::REPORTABLE_STATUSES);
        }

        $this->damageService->applyCommonFilters($query, $request->only([
            'disaster_event_id',
            'administrative_unit_id',
            'verification_status',
            'assessment_type',
            'severity_level',
            'category_id',
            'date_from',
            'date_to',
            'search',
        ]));

        $sortBy = $request->query('sort_by', 'assessment_date');
        $allowedSort = ['assessment_date', 'total_estimated_loss_bdt', 'affected_households', 'created_at', 'd_form_code'];
        if (!in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'assessment_date';
        }
        $sortDir = strtolower((string) $request->query('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $perPage = min(100, max(1, (int) $request->query('per_page', 15)));
        $paginator = $query->orderBy($sortBy, $sortDir)->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => DamageAssessmentResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function store(StoreDamageAssessmentRequest $request): JsonResponse
    {
        $assessment = $this->damageService->createAssessment($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => new DamageAssessmentResource($assessment),
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function show(DamageAssessment $assessment): JsonResponse
    {
        Gate::authorize('viewAssessment', $assessment);

        $assessment->load($this->damageService->defaultRelations());

        return response()->json([
            'success' => true,
            'data' => new DamageAssessmentResource($assessment),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function update(StoreDamageAssessmentRequest $request, DamageAssessment $assessment): JsonResponse
    {
        $updated = $this->damageService->updateAssessment($assessment, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => new DamageAssessmentResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function destroy(Request $request, DamageAssessment $assessment): JsonResponse
    {
        Gate::authorize('deleteAssessment', $assessment);

        $this->damageService->deleteAssessment($assessment, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Damage assessment deleted.',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function sync(SyncDamageAssessmentsRequest $request): JsonResponse
    {
        $result = $this->damageService->syncOfflineAssessments(
            $request->validated('records'),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'data' => $result,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function uploadBoundary(StoreDamageBoundaryRequest $request, DamageAssessment $assessment): JsonResponse
    {
        $updated = $this->damageService->updateBoundary(
            $assessment,
            $request->validated('boundary_geojson'),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'data' => new DamageAssessmentResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function uploadAttachment(StoreDamageAttachmentRequest $request, DamageAssessment $assessment): JsonResponse
    {
        $attachment = $this->damageService->uploadAttachment(
            $assessment,
            $request->file('file'),
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'data' => new DamageAttachmentResource($attachment),
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function downloadAttachment(DamageAssessment $assessment, DamageAttachment $attachment): StreamedResponse
    {
        Gate::authorize('downloadAttachment', $assessment);

        if ($attachment->damage_assessment_id !== $assessment->id) {
            abort(404);
        }

        if (!$attachment->storage_path || !Storage::disk('local')->exists($attachment->storage_path)) {
            abort(404, 'Attachment file not found.');
        }

        return Storage::disk('local')->download(
            $attachment->storage_path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type]
        );
    }

    public function deleteAttachment(Request $request, DamageAssessment $assessment, DamageAttachment $attachment): JsonResponse
    {
        Gate::authorize('updateAssessment', $assessment);

        if ($attachment->damage_assessment_id !== $assessment->id) {
            abort(404);
        }

        $this->damageService->deleteAttachment($assessment, $attachment, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Attachment deleted.',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
