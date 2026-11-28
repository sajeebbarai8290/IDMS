<?php

namespace App\Modules\Document\Controllers\Api\V1;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Http\Controllers\Controller;
use App\Modules\Document\Models\Document;
use App\Modules\Document\Resources\DocumentCategoryResource;
use App\Modules\Document\Resources\DocumentResource;
use App\Modules\Document\Services\DocumentEntityIntegrationService;
use App\Modules\Document\Services\DocumentService;
use App\Modules\Document\Services\FileSecurityValidator;
use App\Modules\UserManagement\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DocumentDashboardController extends Controller
{
    public function __construct(
        protected DocumentService $documentService,
        protected DocumentEntityIntegrationService $entityIntegration
    ) {}

    public function summary(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Document::class);

        $data = $this->documentService->getDashboardSummary($request->user());
        $data['recent_documents'] = DocumentResource::collection($data['recent_documents']);
        $data['pending_approval_documents'] = DocumentResource::collection($data['pending_approval_documents']);
        $data['expiring_documents'] = DocumentResource::collection($data['expiring_documents']);

        return response()->json([
            'success' => true,
            'data' => $data,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function lookups(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Document::class);

        $actor = $request->user();
        $categories = $this->documentService->listCategories(true);

        $unitsQuery = AdministrativeUnit::query()->where('is_active', true);
        if (!$actor->isNationalScope()) {
            $paths = $actor->getAccessibleAncestryPaths();
            if (!in_array('/', $paths, true)) {
                $unitsQuery->where(function ($q) use ($paths) {
                    foreach ($paths as $p) {
                        $q->orWhere('ancestry_path', 'like', rtrim($p, '/') . '/%');
                    }
                });
            }
        }
        $units = $unitsQuery->orderBy('ancestry_path')->get(['id', 'code', 'name_en', 'name_bn', 'tier_level', 'ancestry_path']);

        $organizations = Organization::query()
            ->where('is_active', true)
            ->orderBy('name_en')
            ->get(['id', 'org_code', 'name_en', 'name_bn', 'tier_level']);

        $usersQuery = User::query()
            ->where('is_active', true)
            ->orderBy('full_name_en');
        if (!$actor->isNationalScope()) {
            $unitIds = $units->pluck('id')->all();
            $usersQuery->whereIn('administrative_unit_id', $unitIds);
        }
        $users = $usersQuery->limit(150)->get(['id', 'full_name_en', 'full_name_bn', 'role_code', 'administrative_unit_id']);

        return response()->json([
            'success' => true,
            'data' => [
                'categories' => DocumentCategoryResource::collection($categories),
                'document_types' => Document::DOCUMENT_TYPES,
                'classifications' => Document::CLASSIFICATIONS,
                'approval_statuses' => Document::APPROVAL_STATUSES,
                'supported_modules' => Document::SUPPORTED_MODULES,
                'entity_types' => DocumentEntityIntegrationService::ENTITY_TYPES,
                'allowed_extensions' => array_keys(FileSecurityValidator::APPROVED_FORMAT_MIMES),
                'previewable_mimes' => Document::PREVIEWABLE_MIMES,
                'max_file_size_kb' => (int) config('services.document.max_file_size_kb', 20480),
                'signed_url_ttl_minutes' => (int) config('services.document.signed_url_ttl_minutes', 15),
                'administrative_units' => $units,
                'organizations' => $organizations,
                'users' => $users,
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function targetEntities(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Document::class);

        $entityType = (string) $request->query('entity_type', '');
        $search = $request->query('search');
        $limit = (int) $request->query('limit', 25);

        $items = $this->entityIntegration->searchTargetEntities(
            $entityType,
            $request->user(),
            is_string($search) ? $search : null,
            $limit
        );

        return response()->json([
            'success' => true,
            'data' => $items,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
