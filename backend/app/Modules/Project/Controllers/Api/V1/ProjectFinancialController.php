<?php

namespace App\Modules\Project\Controllers\Api\V1;

use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectDisbursement;
use App\Modules\Project\Requests\ApproveProjectDisbursementRequest;
use App\Modules\Project\Requests\StoreProjectDisbursementRequest;
use App\Modules\Project\Resources\ProjectDisbursementResource;
use App\Modules\Project\Services\ProjectManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectFinancialController extends Controller
{
    public function __construct(
        protected ProjectManagementService $projectService
    ) {}

    public function store(StoreProjectDisbursementRequest $request, Project $project): JsonResponse
    {
        $disbursement = $this->projectService->recordDisbursement(
            $project,
            $request->validated(),
            $request->file('supporting_doc'),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'data' => new ProjectDisbursementResource($disbursement),
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function approve(ApproveProjectDisbursementRequest $request, ProjectDisbursement $disbursement): JsonResponse
    {
        $updated = $this->projectService->approveDisbursement(
            $disbursement,
            $request->validated('payment_status'),
            $request->validated('notes'),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'data' => new ProjectDisbursementResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function downloadDocument(ProjectDisbursement $disbursement): StreamedResponse
    {
        Gate::authorize('downloadInternalAttachment', $disbursement->project);
        return $this->projectService->downloadFinancialDocument($disbursement);
    }
}
