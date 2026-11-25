<?php

namespace App\Modules\Response\Controllers\Api\V1;

use App\Modules\Response\Models\ResponseOperation;
use App\Modules\Response\Models\ResponseSituationReport;
use App\Modules\Response\Requests\StoreSituationReportRequest;
use App\Modules\Response\Resources\ResponseSituationReportResource;
use App\Modules\Response\Services\ResponseManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class ResponseSituationReportController extends Controller
{
    public function __construct(
        protected ResponseManagementService $responseService
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ResponseOperation::class);

        $query = ResponseSituationReport::inJurisdiction($request->user())
            ->with(['operation:id,operation_no,title_en,title_bn', 'administrativeUnit', 'reporter']);

        if ($opId = $request->query('response_operation_id')) {
            $query->where('response_operation_id', $opId);
        }
        if ($type = $request->query('report_type')) {
            $query->where('report_type', strtoupper((string) $type));
        }

        $perPage = min(100, max(5, (int) $request->query('per_page', 15)));
        $reports = $query->orderByDesc('reported_at')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => ResponseSituationReportResource::collection($reports->items()),
            'pagination' => [
                'current_page' => $reports->currentPage(),
                'per_page' => $reports->perPage(),
                'total' => $reports->total(),
                'last_page' => $reports->lastPage(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function store(StoreSituationReportRequest $request, ResponseOperation $operation): JsonResponse
    {
        $report = $this->responseService->createSituationReport($operation, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => new ResponseSituationReportResource($report),
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }
}
