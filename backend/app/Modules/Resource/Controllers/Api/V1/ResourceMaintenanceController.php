<?php

namespace App\Modules\Resource\Controllers\Api\V1;

use App\Modules\Resource\Models\ResourceAsset;
use App\Modules\Resource\Models\ResourceMaintenance;
use App\Modules\Resource\Requests\StoreResourceMaintenanceRequest;
use App\Modules\Resource\Resources\ResourceMaintenanceResource;
use App\Modules\Resource\Services\ResourceManagementService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResourceMaintenanceController extends Controller
{
    public function __construct(
        protected ResourceManagementService $resourceService
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ResourceAsset::class);

        $query = ResourceMaintenance::inJurisdiction($request->user())->with('asset');

        if ($assetId = $request->query('resource_asset_id')) {
            $query->where('resource_asset_id', $assetId);
        }
        if ($status = $request->query('status')) {
            $query->where('status', strtoupper((string) $status));
        }
        if ($type = $request->query('record_type')) {
            $query->where('record_type', strtoupper((string) $type));
        }
        if ($search = trim((string) $request->query('search', ''))) {
            $likeOp = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $likeOp) {
                $q->where('maintenance_no', $likeOp, "%{$search}%")
                    ->orWhere('title', $likeOp, "%{$search}%")
                    ->orWhere('vendor_or_workshop', $likeOp, "%{$search}%");
            });
        }

        $perPage = min(100, max(5, (int) $request->query('per_page', 15)));
        $records = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => ResourceMaintenanceResource::collection($records->items()),
            'pagination' => [
                'current_page' => $records->currentPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
                'last_page' => $records->lastPage(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function store(StoreResourceMaintenanceRequest $request, ResourceAsset $asset): JsonResponse
    {
        $record = $this->resourceService->recordMaintenance(
            $asset,
            $request->validated(),
            $request->user(),
            $request->file('attachment')
        );

        return response()->json([
            'success' => true,
            'data' => new ResourceMaintenanceResource($record),
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function update(Request $request, ResourceMaintenance $maintenance): JsonResponse
    {
        $maintenance->loadMissing('asset');
        Gate::authorize('manageMaintenance', $maintenance->asset);

        $validated = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(ResourceMaintenance::STATUSES)],
            'performed_date' => ['nullable', 'date'],
            'next_due_date' => ['nullable', 'date'],
            'inspection_findings' => ['nullable', 'string', 'max:2000'],
            'inspection_result' => ['nullable', 'string', Rule::in(ResourceMaintenance::INSPECTION_RESULTS)],
            'repair_cost_bdt' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'vendor_or_workshop' => ['nullable', 'string', 'max:180'],
            'performed_by_name' => ['nullable', 'string', 'max:150'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx'],
        ]);

        $updated = $this->resourceService->updateMaintenance(
            $maintenance,
            $validated,
            $request->user(),
            $request->file('attachment')
        );

        return response()->json([
            'success' => true,
            'data' => new ResourceMaintenanceResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function downloadAttachment(Request $request, ResourceMaintenance $maintenance): StreamedResponse|JsonResponse
    {
        Gate::authorize('viewMaintenance', $maintenance);

        if ($request->user()->hasRole('ROLE_EXTERNAL_PARTNER')) {
            throw new AuthorizationException('External partners cannot download internal inspection documents.');
        }

        if (!$maintenance->attachment_path || !Storage::disk('local')->exists($maintenance->attachment_path)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Maintenance attachment not found.'],
                'timestamp' => now()->toIso8601String(),
            ], 404);
        }

        return Storage::disk('local')->download(
            $maintenance->attachment_path,
            $maintenance->attachment_original_name ?: 'inspection-report'
        );
    }
}
