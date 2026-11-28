<?php

namespace App\Modules\Document\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Document\Models\Document;
use App\Modules\Document\Models\DocumentCategory;
use App\Modules\Document\Requests\StoreDocumentCategoryRequest;
use App\Modules\Document\Requests\UpdateDocumentCategoryRequest;
use App\Modules\Document\Resources\DocumentCategoryResource;
use App\Modules\Document\Services\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DocumentCategoryController extends Controller
{
    public function __construct(
        protected DocumentService $documentService
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Document::class);

        $activeOnly = filter_var($request->query('active_only', false), FILTER_VALIDATE_BOOLEAN);
        $categories = $this->documentService->listCategories($activeOnly);

        return response()->json([
            'success' => true,
            'data' => DocumentCategoryResource::collection($categories),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function store(StoreDocumentCategoryRequest $request): JsonResponse
    {
        Gate::authorize('manageCategories', Document::class);

        $category = $this->documentService->createCategory($request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'data' => new DocumentCategoryResource($category),
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function update(UpdateDocumentCategoryRequest $request, DocumentCategory $category): JsonResponse
    {
        Gate::authorize('manageCategories', Document::class);

        $updated = $this->documentService->updateCategory($category, $request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'data' => new DocumentCategoryResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function destroy(Request $request, DocumentCategory $category): JsonResponse
    {
        Gate::authorize('manageCategories', Document::class);

        $this->documentService->deleteCategory($category, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Document category deleted successfully.',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
