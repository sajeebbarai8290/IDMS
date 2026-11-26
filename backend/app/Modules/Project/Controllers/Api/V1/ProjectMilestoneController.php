<?php

namespace App\Modules\Project\Controllers\Api\V1;

use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectMilestone;
use App\Modules\Project\Requests\CertifyProjectMilestoneRequest;
use App\Modules\Project\Requests\StoreProjectMilestoneRequest;
use App\Modules\Project\Requests\UpdateProjectMilestoneProgressRequest;
use App\Modules\Project\Resources\ProjectMilestoneResource;
use App\Modules\Project\Services\ProjectManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class ProjectMilestoneController extends Controller
{
    public function __construct(
        protected ProjectManagementService $projectService
    ) {}

    public function store(StoreProjectMilestoneRequest $request, Project $project): JsonResponse
    {
        $milestone = $this->projectService->createMilestone($project, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => new ProjectMilestoneResource($milestone),
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function updateProgress(UpdateProjectMilestoneProgressRequest $request, ProjectMilestone $milestone): JsonResponse
    {
        $updated = $this->projectService->updateMilestoneProgress($milestone, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => new ProjectMilestoneResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function certify(CertifyProjectMilestoneRequest $request, ProjectMilestone $milestone): JsonResponse
    {
        $certified = $this->projectService->certifyMilestone(
            $milestone,
            $request->validated('approval_status'),
            $request->validated('notes'),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'data' => new ProjectMilestoneResource($certified),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
