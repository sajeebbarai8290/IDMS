<?php

namespace App\Modules\Document\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Document\Models\Document;
use App\Modules\Document\Models\DocumentAccessLog;
use App\Modules\Document\Models\DocumentVersion;
use App\Modules\Document\Requests\DocumentApprovalRequest;
use App\Modules\Document\Requests\StoreDocumentRequest;
use App\Modules\Document\Requests\UpdateDocumentRequest;
use App\Modules\Document\Requests\UploadDocumentVersionRequest;
use App\Modules\Document\Resources\DocumentAccessLogResource;
use App\Modules\Document\Resources\DocumentResource;
use App\Modules\Document\Services\DocumentEntityIntegrationService;
use App\Modules\Document\Services\DocumentService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DocumentController extends Controller
{
    public function __construct(
        protected DocumentService $documentService,
        protected DocumentEntityIntegrationService $entityIntegration
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Document::class);

        $paginator = $this->documentService->listDocuments($request->user(), $request->all());

        return response()->json([
            'success' => true,
            'data' => DocumentResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function entityDocuments(Request $request, string $entityType, string $entityId): JsonResponse
    {
        Gate::authorize('viewAny', Document::class);

        $docs = $this->documentService->listEntityDocuments($request->user(), $entityType, $entityId);

        return response()->json([
            'success' => true,
            'data' => DocumentResource::collection($docs),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function store(StoreDocumentRequest $request): JsonResponse
    {
        Gate::authorize('upload', Document::class);

        $document = $this->documentService->registerDocument(
            $request->user(),
            $request->file('file'),
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'data' => new DocumentResource($document),
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function show(Request $request, Document $document): JsonResponse
    {
        Gate::authorize('view', $document);

        $loaded = $this->documentService->loadDetail($document);
        $this->documentService->recordAccessLog(
            $loaded,
            $loaded->current_version_id,
            $request->user(),
            DocumentAccessLog::ACTION_VIEWED_METADATA
        );

        $entitySummary = $this->entityIntegration->getLiveSummary($loaded->entity_type, $loaded->entity_id);

        return response()->json([
            'success' => true,
            'data' => new DocumentResource($loaded),
            'entity_summary' => $entitySummary,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function update(UpdateDocumentRequest $request, Document $document): JsonResponse
    {
        Gate::authorize('update', $document);

        $updated = $this->documentService->updateMetadata($document, $request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'data' => new DocumentResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function destroy(Request $request, Document $document): JsonResponse
    {
        Gate::authorize('delete', $document);

        $this->documentService->deleteDocument($document, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Document deleted successfully.',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function uploadVersion(UploadDocumentVersionRequest $request, Document $document): JsonResponse
    {
        Gate::authorize('uploadVersion', $document);

        $updated = $this->documentService->uploadNewVersion(
            $document,
            $request->file('file'),
            $request->user(),
            $request->validated('change_notes'),
            $request->validated('version_label')
        );

        return response()->json([
            'success' => true,
            'data' => new DocumentResource($updated),
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function restoreVersion(Request $request, Document $document, DocumentVersion $version): JsonResponse
    {
        Gate::authorize('uploadVersion', $document);

        $updated = $this->documentService->restoreVersion($document, $version, $request->user());

        return response()->json([
            'success' => true,
            'data' => new DocumentResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function download(Request $request, Document $document): StreamedResponse
    {
        Gate::authorize('view', $document);

        $version = null;
        if ($request->filled('version_id')) {
            $version = DocumentVersion::findOrFail($request->query('version_id'));
        }

        return $this->documentService->streamDocument($document, $request->user(), $version, false);
    }

    public function preview(Request $request, Document $document): StreamedResponse
    {
        Gate::authorize('view', $document);

        $version = null;
        if ($request->filled('version_id')) {
            $version = DocumentVersion::findOrFail($request->query('version_id'));
        }

        return $this->documentService->streamDocument($document, $request->user(), $version, true);
    }

    public function downloadVersion(Request $request, Document $document, DocumentVersion $version): StreamedResponse
    {
        Gate::authorize('view', $document);

        return $this->documentService->streamDocument($document, $request->user(), $version, false);
    }

    public function signedUrl(Request $request, Document $document): JsonResponse
    {
        Gate::authorize('view', $document);

        $versionId = $request->input('version_id');
        if ($versionId) {
            $ver = DocumentVersion::where('id', $versionId)->where('document_id', $document->id)->first();
            if (!$ver) {
                throw new NotFoundHttpException('Requested version not found for this document.');
            }
        }

        $ttl = $request->filled('ttl_minutes') ? (int) $request->input('ttl_minutes') : null;
        $inline = filter_var($request->input('inline', false), FILTER_VALIDATE_BOOLEAN);

        $payload = $this->documentService->generateSignedUrl($document, $request->user(), $versionId, $ttl, $inline);

        return response()->json([
            'success' => true,
            'data' => $payload,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function stream(Request $request, Document $document): StreamedResponse
    {
        if (!$request->hasValidSignature()) {
            throw new AuthorizationException('Signed document streaming URL is invalid or has expired.');
        }

        Gate::authorize('view', $document);

        $version = null;
        if ($request->filled('version_id')) {
            $version = DocumentVersion::findOrFail($request->query('version_id'));
        }
        $inline = filter_var($request->query('inline', false), FILTER_VALIDATE_BOOLEAN);

        return $this->documentService->streamDocument($document, $request->user(), $version, $inline);
    }

    public function submitApproval(Request $request, Document $document): JsonResponse
    {
        Gate::authorize('update', $document);

        $updated = $this->documentService->submitForApproval($document, $request->user());

        return response()->json([
            'success' => true,
            'data' => new DocumentResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function approve(DocumentApprovalRequest $request, Document $document): JsonResponse
    {
        Gate::authorize('approve', $document);

        $updated = $this->documentService->approveDocument(
            $document,
            $request->user(),
            $request->validated('notes')
        );

        return response()->json([
            'success' => true,
            'data' => new DocumentResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function reject(DocumentApprovalRequest $request, Document $document): JsonResponse
    {
        Gate::authorize('approve', $document);

        $reason = trim((string) ($request->validated('reason') ?? $request->validated('notes') ?? ''));
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => ['Rejection reason is mandatory.'],
            ]);
        }

        $updated = $this->documentService->rejectDocument($document, $request->user(), $reason);

        return response()->json([
            'success' => true,
            'data' => new DocumentResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function archive(DocumentApprovalRequest $request, Document $document): JsonResponse
    {
        Gate::authorize('update', $document);

        $updated = $this->documentService->archiveDocument(
            $document,
            $request->user(),
            $request->validated('notes')
        );

        return response()->json([
            'success' => true,
            'data' => new DocumentResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function verifyIntegrity(Request $request, Document $document): JsonResponse
    {
        Gate::authorize('verifyIntegrity', $document);

        $version = null;
        if ($request->filled('version_id')) {
            $version = DocumentVersion::where('id', $request->input('version_id'))
                ->where('document_id', $document->id)
                ->firstOrFail();
        }

        $report = $this->documentService->verifyIntegrity($document, $request->user(), $version);

        return response()->json([
            'success' => true,
            'data' => $report,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function history(Request $request, Document $document): JsonResponse
    {
        Gate::authorize('view', $document);

        $history = $this->documentService->getHistory($document);

        return response()->json([
            'success' => true,
            'data' => [
                'access_logs' => DocumentAccessLogResource::collection($history['access_logs']),
                'audit_logs' => $history['audit_logs'],
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
