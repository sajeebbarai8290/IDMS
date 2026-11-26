<?php

namespace App\Modules\Resource\Controllers\Api\V1;

use App\Modules\Resource\Models\ResourceAsset;
use App\Modules\Resource\Models\ResourceAttachment;
use App\Modules\Resource\Requests\StoreResourceAssetRequest;
use App\Modules\Resource\Requests\UpdateResourceAssetRequest;
use App\Modules\Resource\Requests\UploadResourceAttachmentRequest;
use App\Modules\Resource\Resources\ResourceAssetResource;
use App\Modules\Resource\Services\ResourceManagementService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResourceAssetController extends Controller
{
    public function __construct(
        protected ResourceManagementService $resourceService
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ResourceAsset::class);

        $query = ResourceAsset::inJurisdiction($request->user())
            ->with(['category', 'administrativeUnit'])
            ->withCount(['allocations', 'maintenances', 'attachments']);

        if ($categoryId = $request->query('category_id')) {
            $query->where('category_id', $categoryId);
        }
        if ($status = $request->query('status')) {
            $query->whereIn('status', ResourceAsset::statusesForFilter((string) $status));
        }
        if ($unitId = $request->query('administrative_unit_id')) {
            $query->where('administrative_unit_id', $unitId);
        }
        if ($search = trim((string) $request->query('search', ''))) {
            $likeOp = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $likeOp) {
                $q->where('asset_code', $likeOp, "%{$search}%")
                    ->orWhere('name_en', $likeOp, "%{$search}%")
                    ->orWhere('name_bn', $likeOp, "%{$search}%")
                    ->orWhere('resource_type', $likeOp, "%{$search}%")
                    ->orWhere('owning_organization', $likeOp, "%{$search}%")
                    ->orWhere('current_location', $likeOp, "%{$search}%");
            });
        }

        $sortBy = in_array($request->query('sort_by'), ['created_at', 'asset_code', 'name_en', 'status', 'acquisition_date', 'next_maintenance_due_at'], true)
            ? $request->query('sort_by')
            : 'created_at';
        $sortDir = strtolower((string) $request->query('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $perPage = min(100, max(5, (int) $request->query('per_page', 15)));
        $assets = $query->orderBy($sortBy, $sortDir)->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => ResourceAssetResource::collection($assets->items()),
            'pagination' => [
                'current_page' => $assets->currentPage(),
                'per_page' => $assets->perPage(),
                'total' => $assets->total(),
                'last_page' => $assets->lastPage(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function store(StoreResourceAssetRequest $request): JsonResponse
    {
        $asset = $this->resourceService->registerAsset($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => new ResourceAssetResource($asset),
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function show(ResourceAsset $asset): JsonResponse
    {
        Gate::authorize('viewAsset', $asset);

        $asset->load([
            'category',
            'administrativeUnit',
            'attachments',
            'allocations.disasterEvent',
            'allocations.responseOperation',
            'allocations.administrativeUnit',
            'maintenances',
            'humanDeployments.responseOperation',
            'humanDeployments.disasterEvent',
        ]);

        return response()->json([
            'success' => true,
            'data' => new ResourceAssetResource($asset),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function update(UpdateResourceAssetRequest $request, ResourceAsset $asset): JsonResponse
    {
        $updated = $this->resourceService->updateAsset($asset, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'data' => new ResourceAssetResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function destroy(ResourceAsset $asset, Request $request): JsonResponse
    {
        Gate::authorize('deleteAsset', $asset);

        $this->resourceService->retireAndDeleteAsset($asset, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Resource asset retired and removed from active register.',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function uploadAttachment(UploadResourceAttachmentRequest $request, ResourceAsset $asset): JsonResponse
    {
        $attachment = $this->resourceService->attachFile(
            $asset,
            $request->file('file'),
            $request->validated('attachment_kind'),
            $request->user(),
            $request->validated('title')
        );

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $attachment->id,
                'attachment_kind' => $attachment->attachment_kind,
                'title' => $attachment->title,
                'original_name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'file_size_bytes' => $attachment->file_size_bytes,
                'sha256_hash' => $attachment->sha256_hash,
            ],
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function downloadAttachment(Request $request, ResourceAsset $asset, ResourceAttachment $attachment): StreamedResponse|JsonResponse
    {
        Gate::authorize('viewAsset', $asset);

        if ($request->user()->hasRole('ROLE_EXTERNAL_PARTNER')) {
            throw new AuthorizationException('External partners cannot download internal asset documents.');
        }

        if ($attachment->resource_asset_id !== $asset->id || !Storage::disk('local')->exists($attachment->storage_path)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Attachment file not found.'],
                'timestamp' => now()->toIso8601String(),
            ], 404);
        }

        return Storage::disk('local')->download(
            $attachment->storage_path,
            $attachment->original_name
        );
    }
}
