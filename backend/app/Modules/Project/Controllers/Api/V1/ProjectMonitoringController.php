<?php

namespace App\Modules\Project\Controllers\Api\V1;

use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectMonitoringReport;
use App\Modules\Project\Models\ProjectRisk;
use App\Modules\Project\Requests\StoreProjectMonitoringReportRequest;
use App\Modules\Project\Requests\StoreProjectRiskRequest;
use App\Modules\Project\Requests\UpdateProjectRiskRequest;
use App\Modules\Project\Resources\ProjectMonitoringReportResource;
use App\Modules\Project\Resources\ProjectRiskResource;
use App\Modules\Project\Services\ProjectManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectMonitoringController extends Controller
{
    public function __construct(
        protected ProjectManagementService $projectService
    ) {}

    public function storeReport(StoreProjectMonitoringReportRequest $request, Project $project): JsonResponse
    {
        $report = $this->projectService->createMonitoringReport(
            $project,
            $request->validated(),
            $request->file('attachment'),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'data' => new ProjectMonitoringReportResource($report),
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function downloadAttachment(ProjectMonitoringReport $report): StreamedResponse
    {
        Gate::authorize('downloadInternalAttachment', $report->project);
        return $this->projectService->downloadMonitoringAttachment($report);
    }

    public function storeRisk(StoreProjectRiskRequest $request, Project $project): JsonResponse
    {
        $risk = $this->projectService->createRisk($project, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => new ProjectRiskResource($risk),
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function updateRisk(UpdateProjectRiskRequest $request, ProjectRisk $risk): JsonResponse
    {
        $updated = $this->projectService->updateRisk($risk, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => new ProjectRiskResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
