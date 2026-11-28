<?php

namespace App\Modules\Document\Services;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\AuditLog;
use App\Core\Models\User;
use App\Core\Services\AdministrativeScopeService;
use App\Modules\Document\Contracts\DocumentStorageInterface;
use App\Modules\Document\Contracts\VirusScannerInterface;
use App\Modules\Document\Models\Document;
use App\Modules\Document\Models\DocumentAccessLog;
use App\Modules\Document\Models\DocumentCategory;
use App\Modules\Document\Models\DocumentVersion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class DocumentService
{
    public const DASHBOARD_VERSION_KEY = 'document:dashboard:version';

    public function __construct(
        protected AdministrativeScopeService $scopeService,
        protected DocumentStorageInterface $storage,
        protected VirusScannerInterface $scanner,
        protected FileSecurityValidator $validator,
        protected DocumentEntityIntegrationService $entityIntegration
    ) {}

    public function bumpDashboardCache(): void
    {
        try {
            $current = (int) Cache::get(self::DASHBOARD_VERSION_KEY, 1);
            Cache::forever(self::DASHBOARD_VERSION_KEY, $current + 1);
        } catch (Throwable) {
            // Ignore transient cache failures
        }
    }

    public function getDashboardSummary(User $actor): array
    {
        $version = (int) Cache::get(self::DASHBOARD_VERSION_KEY, 1);
        $scopeKey = $actor->isNationalScope()
            ? 'national'
            : md5(implode('|', $actor->getAccessibleAncestryPaths()));
        $isExternal = $actor->hasRole('ROLE_EXTERNAL_PARTNER') ? 'ext' : 'int';
        $cacheKey = "document:dashboard:v{$version}:{$actor->id}:{$scopeKey}:{$isExternal}";

        return Cache::remember($cacheKey, 120, function () use ($actor) {
            $baseQuery = $this->scopedBaseQuery($actor);
            $now = now();
            $soonCutoff = now()->addDays(30);

            $totalDocuments = (clone $baseQuery)->count();
            $approvedDocuments = (clone $baseQuery)->where('approval_status', Document::STATUS_APPROVED)->count();
            $pendingApprovalsCount = (clone $baseQuery)->where('approval_status', Document::STATUS_PENDING_APPROVAL)->count();
            $draftDocuments = (clone $baseQuery)->where('approval_status', Document::STATUS_DRAFT)->count();
            $rejectedDocuments = (clone $baseQuery)->where('approval_status', Document::STATUS_REJECTED)->count();
            $confidentialCount = (clone $baseQuery)->where('is_confidential', true)->count();
            $totalStorageBytes = (int) ((clone $baseQuery)->sum('file_size_bytes') ?? 0);

            $expiringSoonCount = (clone $baseQuery)
                ->whereNotNull('expires_at')
                ->where('expires_at', '>=', $now)
                ->where('expires_at', '<=', $soonCutoff)
                ->where('approval_status', '!=', Document::STATUS_ARCHIVED)
                ->count();

            $expiredCount = (clone $baseQuery)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', $now)
                ->where('approval_status', '!=', Document::STATUS_ARCHIVED)
                ->count();

            $byCategoryRows = (clone $baseQuery)
                ->selectRaw('category_id, COUNT(*) as total, SUM(file_size_bytes) as total_bytes')
                ->groupBy('category_id')
                ->get();

            $categoriesMap = DocumentCategory::whereIn('id', $byCategoryRows->pluck('category_id')->filter())
                ->get()
                ->keyBy('id');

            $byCategory = $byCategoryRows->map(function ($row) use ($categoriesMap) {
                $cat = $categoriesMap->get($row->category_id);
                return [
                    'category_id' => $row->category_id,
                    'code' => $cat?->code ?? 'OTHER',
                    'name_en' => $cat?->name_en ?? 'Unknown',
                    'name_bn' => $cat?->name_bn ?? 'অজানা',
                    'total' => (int) $row->total,
                    'total_bytes' => (int) ($row->total_bytes ?? 0),
                ];
            })->sortByDesc('total')->values()->all();

            $byClassification = (clone $baseQuery)
                ->selectRaw('classification, COUNT(*) as total')
                ->groupBy('classification')
                ->get()
                ->map(fn ($r) => ['classification' => $r->classification, 'total' => (int) $r->total])
                ->values()
                ->all();

            $byApprovalStatus = (clone $baseQuery)
                ->selectRaw('approval_status, COUNT(*) as total')
                ->groupBy('approval_status')
                ->get()
                ->map(fn ($r) => ['approval_status' => $r->approval_status, 'total' => (int) $r->total])
                ->values()
                ->all();

            $recentDocuments = (clone $baseQuery)
                ->with(['category:id,code,name_en,name_bn', 'administrativeUnit:id,name_en,name_bn', 'uploader:id,full_name_en,full_name_bn'])
                ->orderByDesc('uploaded_at')
                ->orderByDesc('created_at')
                ->limit(8)
                ->get();

            $pendingApprovalDocs = (clone $baseQuery)
                ->with(['category:id,code,name_en,name_bn', 'administrativeUnit:id,name_en,name_bn', 'uploader:id,full_name_en,full_name_bn'])
                ->where('approval_status', Document::STATUS_PENDING_APPROVAL)
                ->orderByDesc('submitted_at')
                ->orderByDesc('created_at')
                ->limit(8)
                ->get();

            $expiringDocuments = (clone $baseQuery)
                ->with(['category:id,code,name_en,name_bn', 'administrativeUnit:id,name_en,name_bn'])
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', $soonCutoff)
                ->where('approval_status', '!=', Document::STATUS_ARCHIVED)
                ->orderBy('expires_at')
                ->limit(8)
                ->get();

            return [
                'kpis' => [
                    'total_documents' => $totalDocuments,
                    'approved_documents' => $approvedDocuments,
                    'pending_approvals' => $pendingApprovalsCount,
                    'draft_documents' => $draftDocuments,
                    'rejected_documents' => $rejectedDocuments,
                    'expiring_soon_count' => $expiringSoonCount,
                    'expired_count' => $expiredCount,
                    'confidential_count' => $confidentialCount,
                    'total_storage_bytes' => $totalStorageBytes,
                ],
                'by_category' => $byCategory,
                'by_classification' => $byClassification,
                'by_approval_status' => $byApprovalStatus,
                'recent_documents' => $recentDocuments,
                'pending_approval_documents' => $pendingApprovalDocs,
                'expiring_documents' => $expiringDocuments,
                'storage_health' => $this->storage->health(),
                'scanner_health' => $this->scanner->health(),
            ];
        });
    }

    public function listDocuments(User $actor, array $filters = []): LengthAwarePaginator
    {
        $query = $this->scopedBaseQuery($actor)->with([
            'category:id,code,name_en,name_bn',
            'organization:id,org_code,name_en,name_bn',
            'administrativeUnit:id,code,name_en,name_bn,tier_level,ancestry_path',
            'owner:id,full_name_en,full_name_bn,role_code',
            'uploader:id,full_name_en,full_name_bn,role_code',
        ]);

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }
        if (!empty($filters['category_code'])) {
            $code = strtoupper(trim((string) $filters['category_code']));
            $query->whereHas('category', fn ($q) => $q->where('code', $code));
        }
        if (!empty($filters['document_type'])) {
            $query->where('document_type', strtoupper(trim((string) $filters['document_type'])));
        }
        if (!empty($filters['module_code'])) {
            $query->where('module_code', strtoupper(trim((string) $filters['module_code'])));
        }
        if (!empty($filters['entity_type'])) {
            $query->where('entity_type', strtoupper(trim((string) $filters['entity_type'])));
        }
        if (!empty($filters['entity_id'])) {
            $query->where('entity_id', $filters['entity_id']);
        }
        if (!empty($filters['approval_status'])) {
            $query->where('approval_status', strtoupper(trim((string) $filters['approval_status'])));
        }
        if (!empty($filters['classification'])) {
            $query->where('classification', strtoupper(trim((string) $filters['classification'])));
        }
        if (!empty($filters['scan_status'])) {
            $query->where('scan_status', strtoupper(trim((string) $filters['scan_status'])));
        }
        if (!empty($filters['organization_id'])) {
            $query->where('organization_id', $filters['organization_id']);
        }
        if (!empty($filters['administrative_unit_id'])) {
            $unit = AdministrativeUnit::find($filters['administrative_unit_id']);
            if ($unit) {
                $path = rtrim($unit->ancestry_path, '/') . '/';
                $query->whereHas('administrativeUnit', fn ($q) => $q->where('ancestry_path', 'like', $path . '%'));
            }
        }
        if (!empty($filters['date_from'])) {
            $query->whereDate('uploaded_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('uploaded_at', '<=', $filters['date_to']);
        }
        if (!empty($filters['expired_only']) && filter_var($filters['expired_only'], FILTER_VALIDATE_BOOLEAN)) {
            $query->whereNotNull('expires_at')->where('expires_at', '<', now());
        } elseif (!empty($filters['expiring_within_days'])) {
            $days = max(1, min(365, (int) $filters['expiring_within_days']));
            $query->whereNotNull('expires_at')
                ->where('expires_at', '>=', now())
                ->where('expires_at', '<=', now()->addDays($days));
        }
        if (!empty($filters['tag'])) {
            $tag = trim((string) $filters['tag']);
            $query->whereJsonContains('tags', $tag);
        }
        if (!empty($filters['search'])) {
            $term = '%' . trim((string) $filters['search']) . '%';
            $likeOp = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($term, $likeOp) {
                $q->where('document_no', $likeOp, $term)
                    ->orWhere('reference_no', $likeOp, $term)
                    ->orWhere('title_en', $likeOp, $term)
                    ->orWhere('title_bn', $likeOp, $term)
                    ->orWhere('issuing_organization', $likeOp, $term)
                    ->orWhere('original_filename', $likeOp, $term)
                    ->orWhere('entity_reference', $likeOp, $term)
                    ->orWhere('description_en', $likeOp, $term)
                    ->orWhere('description_bn', $likeOp, $term);
            });
        }

        $allowedSorts = ['uploaded_at', 'created_at', 'title_en', 'expires_at', 'file_size_bytes', 'approval_status', 'document_no'];
        $sortBy = in_array($filters['sort_by'] ?? '', $allowedSorts, true) ? $filters['sort_by'] : 'uploaded_at';
        $sortDir = strtolower((string) ($filters['sort_dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $perPage = max(5, min(100, (int) ($filters['per_page'] ?? 15)));

        return $query->orderBy($sortBy, $sortDir)->paginate($perPage);
    }

    public function listEntityDocuments(User $actor, string $entityType, string $entityId): array
    {
        $normalizedType = strtoupper(trim($entityType));

        return $this->scopedBaseQuery($actor)
            ->with(['category:id,code,name_en,name_bn', 'uploader:id,full_name_en,full_name_bn'])
            ->where('entity_type', $normalizedType)
            ->where('entity_id', $entityId)
            ->orderByDesc('uploaded_at')
            ->get()
            ->all();
    }

    public function registerDocument(User $actor, UploadedFile $file, array $data): Document
    {
        $category = DocumentCategory::findOrFail($data['category_id']);
        if (!$category->is_active) {
            throw ValidationException::withMessages([
                'category_id' => ['Selected document category is inactive.'],
            ]);
        }

        $validatedFile = $this->validator->validateAndScan($file, $category);

        $target = $this->entityIntegration->resolveTarget(
            $actor,
            $data['entity_type'] ?? null,
            $data['entity_id'] ?? null,
            $data['module_code'] ?? null,
            $data['administrative_unit_id'] ?? null
        );

        // Duplicate prevention:
        // 1. Same reference_no + category_id + administrative_unit_id (when reference_no is provided)
        if (!empty($data['reference_no'])) {
            $dupRef = Document::where('reference_no', trim((string) $data['reference_no']))
                ->where('category_id', $category->id)
                ->where('administrative_unit_id', $target['administrative_unit_id'])
                ->exists();
            if ($dupRef) {
                throw ValidationException::withMessages([
                    'reference_no' => ['A document with this reference number already exists in the selected category and jurisdiction.'],
                ]);
            }
        }

        // 2. Identical SHA-256 checksum attached to the same entity record
        if ($target['entity_type'] && $target['entity_id']) {
            $dupHash = Document::where('entity_type', $target['entity_type'])
                ->where('entity_id', $target['entity_id'])
                ->where('file_hash_sha256', $validatedFile['file_hash_sha256'])
                ->exists();
            if ($dupHash) {
                throw ValidationException::withMessages([
                    'file' => ['This exact file (identical SHA-256 checksum) is already attached to the target record.'],
                ]);
            }
        }

        $classification = strtoupper((string) ($data['classification'] ?? $category->default_classification ?? Document::CLASS_INTERNAL));
        $isConfidential = in_array($classification, [Document::CLASS_CONFIDENTIAL, Document::CLASS_RESTRICTED], true);
        $submitForApproval = filter_var($data['submit_for_approval'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $initialStatus = $submitForApproval ? Document::STATUS_PENDING_APPROVAL : Document::STATUS_DRAFT;

        $stored = $this->storage->put($validatedFile['storage_path'], $validatedFile['contents']);

        try {
            $document = DB::transaction(function () use ($actor, $category, $validatedFile, $target, $data, $classification, $isConfidential, $submitForApproval, $initialStatus, $stored) {
                $now = now();
                $documentId = (string) Str::uuid();
                $versionId = (string) Str::uuid();
                $documentNo = $this->generateDocumentNo();

                $tags = $this->normalizeTags($data['tags'] ?? []);

                $document = Document::create([
                    'id' => $documentId,
                    'document_no' => $documentNo,
                    'reference_no' => !empty($data['reference_no']) ? trim((string) $data['reference_no']) : null,
                    'category_id' => $category->id,
                    'document_type' => strtoupper((string) ($data['document_type'] ?? Document::TYPE_OTHER)),
                    'title_bn' => trim((string) $data['title_bn']),
                    'title_en' => trim((string) $data['title_en']),
                    'description_bn' => $data['description_bn'] ?? null,
                    'description_en' => $data['description_en'] ?? null,
                    'issuing_organization' => $data['issuing_organization'] ?? null,
                    'organization_id' => $data['organization_id'] ?? $actor->organization_id,
                    'administrative_unit_id' => $target['administrative_unit_id'],
                    'owner_user_id' => $data['owner_user_id'] ?? $actor->id,
                    'uploaded_by' => $actor->id,
                    'uploaded_at' => $now,
                    'effective_date' => $data['effective_date'] ?? null,
                    'expires_at' => $data['expires_at'] ?? null,
                    'module_code' => $target['module_code'],
                    'entity_type' => $target['entity_type'],
                    'entity_id' => $target['entity_id'],
                    'entity_reference' => $target['entity_reference'],
                    'classification' => $classification,
                    'tags' => $tags,
                    'approval_status' => $initialStatus,
                    'submitted_by' => $submitForApproval ? $actor->id : null,
                    'submitted_at' => $submitForApproval ? $now : null,
                    'current_version_number' => 1,
                    'current_version_id' => $versionId,
                    'original_filename' => $validatedFile['original_filename'],
                    'storage_disk' => $stored['disk'],
                    'storage_path' => $stored['path'],
                    'mime_type' => $validatedFile['mime_type'],
                    'file_extension' => $validatedFile['extension'],
                    'file_size_bytes' => $validatedFile['file_size_bytes'],
                    'file_hash_sha256' => $validatedFile['file_hash_sha256'],
                    'scan_status' => $validatedFile['scan_status'],
                    'scanned_at' => $now,
                    'scan_engine' => $validatedFile['scan_engine'],
                    'scan_details' => $validatedFile['scan_details'],
                    'is_confidential' => $isConfidential,
                    'metadata' => $data['metadata'] ?? null,
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);

                DocumentVersion::create([
                    'id' => $versionId,
                    'document_id' => $document->id,
                    'version_number' => 1,
                    'version_label' => 'v1.0',
                    'change_notes' => $data['change_notes'] ?? 'Initial document upload',
                    'original_filename' => $validatedFile['original_filename'],
                    'storage_disk' => $stored['disk'],
                    'storage_path' => $stored['path'],
                    'mime_type' => $validatedFile['mime_type'],
                    'file_extension' => $validatedFile['extension'],
                    'file_size_bytes' => $validatedFile['file_size_bytes'],
                    'file_hash_sha256' => $validatedFile['file_hash_sha256'],
                    'scan_status' => $validatedFile['scan_status'],
                    'scanned_at' => $now,
                    'scan_engine' => $validatedFile['scan_engine'],
                    'is_current' => true,
                    'uploaded_by' => $actor->id,
                ]);

                $this->recordAccessLog(
                    $document,
                    $versionId,
                    $actor,
                    DocumentAccessLog::ACTION_UPLOADED,
                    [
                        'document_no' => $documentNo,
                        'mime_type' => $validatedFile['mime_type'],
                        'sha256' => $validatedFile['file_hash_sha256'],
                        'scan_status' => $validatedFile['scan_status'],
                    ]
                );

                AuditLog::log(
                    'DOCUMENT_UPLOADED',
                    Document::class,
                    $document->id,
                    null,
                    [
                        'document_no' => $documentNo,
                        'title_en' => $document->title_en,
                        'classification' => $classification,
                        'sha256' => $validatedFile['file_hash_sha256'],
                        'entity_type' => $target['entity_type'],
                        'entity_id' => $target['entity_id'],
                    ],
                    $actor,
                    $document->administrative_unit_id
                );

                return $document;
            });
        } catch (Throwable $e) {
            $this->storage->delete($stored['disk'], $stored['path']);
            throw $e;
        }

        $this->bumpDashboardCache();

        return $this->loadDetail($document);
    }

    public function updateMetadata(Document $document, User $actor, array $data): Document
    {
        $oldSnapshot = $document->only([
            'reference_no',
            'category_id',
            'document_type',
            'title_bn',
            'title_en',
            'issuing_organization',
            'classification',
            'expires_at',
            'entity_type',
            'entity_id',
        ]);

        if (!empty($data['category_id'])) {
            $cat = DocumentCategory::findOrFail($data['category_id']);
            if (!$cat->is_active) {
                throw ValidationException::withMessages(['category_id' => ['Selected document category is inactive.']]);
            }
        }

        $targetUpdates = [];
        if (array_key_exists('entity_type', $data) || array_key_exists('entity_id', $data) || array_key_exists('administrative_unit_id', $data)) {
            $resolved = $this->entityIntegration->resolveTarget(
                $actor,
                $data['entity_type'] ?? $document->entity_type,
                $data['entity_id'] ?? $document->entity_id,
                $data['module_code'] ?? $document->module_code,
                $data['administrative_unit_id'] ?? $document->administrative_unit_id
            );
            $targetUpdates = $resolved;
        }

        $updated = DB::transaction(function () use ($document, $actor, $data, $targetUpdates, $oldSnapshot) {
            $locked = Document::whereKey($document->id)->lockForUpdate()->firstOrFail();

            $classification = isset($data['classification'])
                ? strtoupper((string) $data['classification'])
                : $locked->classification;

            $locked->update(array_merge([
                'reference_no' => array_key_exists('reference_no', $data) ? ($data['reference_no'] ? trim((string) $data['reference_no']) : null) : $locked->reference_no,
                'category_id' => $data['category_id'] ?? $locked->category_id,
                'document_type' => isset($data['document_type']) ? strtoupper((string) $data['document_type']) : $locked->document_type,
                'title_bn' => isset($data['title_bn']) ? trim((string) $data['title_bn']) : $locked->title_bn,
                'title_en' => isset($data['title_en']) ? trim((string) $data['title_en']) : $locked->title_en,
                'description_bn' => array_key_exists('description_bn', $data) ? $data['description_bn'] : $locked->description_bn,
                'description_en' => array_key_exists('description_en', $data) ? $data['description_en'] : $locked->description_en,
                'issuing_organization' => array_key_exists('issuing_organization', $data) ? $data['issuing_organization'] : $locked->issuing_organization,
                'organization_id' => array_key_exists('organization_id', $data) ? $data['organization_id'] : $locked->organization_id,
                'owner_user_id' => array_key_exists('owner_user_id', $data) ? $data['owner_user_id'] : $locked->owner_user_id,
                'effective_date' => array_key_exists('effective_date', $data) ? $data['effective_date'] : $locked->effective_date,
                'expires_at' => array_key_exists('expires_at', $data) ? $data['expires_at'] : $locked->expires_at,
                'classification' => $classification,
                'is_confidential' => in_array($classification, [Document::CLASS_CONFIDENTIAL, Document::CLASS_RESTRICTED], true),
                'tags' => array_key_exists('tags', $data) ? $this->normalizeTags($data['tags']) : $locked->tags,
                'updated_by' => $actor->id,
            ], $targetUpdates));

            $this->recordAccessLog(
                $locked,
                $locked->current_version_id,
                $actor,
                DocumentAccessLog::ACTION_UPDATED,
                ['updated_fields' => array_keys($data)]
            );

            AuditLog::log(
                'DOCUMENT_METADATA_UPDATED',
                Document::class,
                $locked->id,
                $oldSnapshot,
                $locked->only(array_keys($oldSnapshot)),
                $actor,
                $locked->administrative_unit_id
            );

            return $locked;
        });

        $this->bumpDashboardCache();

        return $this->loadDetail($updated);
    }

    public function uploadNewVersion(
        Document $document,
        UploadedFile $file,
        User $actor,
        ?string $changeNotes = null,
        ?string $versionLabel = null
    ): Document {
        $category = $document->category;
        $validatedFile = $this->validator->validateAndScan($file, $category);

        if ($validatedFile['file_hash_sha256'] === $document->file_hash_sha256) {
            throw ValidationException::withMessages([
                'file' => ['Uploaded file is identical to the current active version (matching SHA-256).'],
            ]);
        }

        $stored = $this->storage->put($validatedFile['storage_path'], $validatedFile['contents']);

        try {
            $updated = DB::transaction(function () use ($document, $actor, $validatedFile, $stored, $changeNotes, $versionLabel) {
                $locked = Document::whereKey($document->id)->lockForUpdate()->firstOrFail();
                $nextNum = ((int) $locked->versions()->max('version_number')) + 1;
                $versionId = (string) Str::uuid();
                $now = now();

                DocumentVersion::where('document_id', $locked->id)->update(['is_current' => false]);

                DocumentVersion::create([
                    'id' => $versionId,
                    'document_id' => $locked->id,
                    'version_number' => $nextNum,
                    'version_label' => $versionLabel ?: "v{$nextNum}.0",
                    'change_notes' => $changeNotes ?: "Updated to version {$nextNum}",
                    'original_filename' => $validatedFile['original_filename'],
                    'storage_disk' => $stored['disk'],
                    'storage_path' => $stored['path'],
                    'mime_type' => $validatedFile['mime_type'],
                    'file_extension' => $validatedFile['extension'],
                    'file_size_bytes' => $validatedFile['file_size_bytes'],
                    'file_hash_sha256' => $validatedFile['file_hash_sha256'],
                    'scan_status' => $validatedFile['scan_status'],
                    'scanned_at' => $now,
                    'scan_engine' => $validatedFile['scan_engine'],
                    'is_current' => true,
                    'uploaded_by' => $actor->id,
                ]);

                $requiresReapproval = $locked->approval_status === Document::STATUS_APPROVED
                    && ($locked->category?->requires_approval ?? false);

                $locked->update([
                    'current_version_number' => $nextNum,
                    'current_version_id' => $versionId,
                    'original_filename' => $validatedFile['original_filename'],
                    'storage_disk' => $stored['disk'],
                    'storage_path' => $stored['path'],
                    'mime_type' => $validatedFile['mime_type'],
                    'file_extension' => $validatedFile['extension'],
                    'file_size_bytes' => $validatedFile['file_size_bytes'],
                    'file_hash_sha256' => $validatedFile['file_hash_sha256'],
                    'scan_status' => $validatedFile['scan_status'],
                    'scanned_at' => $now,
                    'scan_engine' => $validatedFile['scan_engine'],
                    'scan_details' => $validatedFile['scan_details'],
                    'approval_status' => $requiresReapproval ? Document::STATUS_PENDING_APPROVAL : $locked->approval_status,
                    'submitted_by' => $requiresReapproval ? $actor->id : $locked->submitted_by,
                    'submitted_at' => $requiresReapproval ? $now : $locked->submitted_at,
                    'updated_by' => $actor->id,
                ]);

                $this->recordAccessLog(
                    $locked,
                    $versionId,
                    $actor,
                    DocumentAccessLog::ACTION_VERSION_UPLOADED,
                    ['version_number' => $nextNum, 'sha256' => $validatedFile['file_hash_sha256']]
                );

                AuditLog::log(
                    'DOCUMENT_VERSION_UPLOADED',
                    Document::class,
                    $locked->id,
                    null,
                    ['version_number' => $nextNum, 'sha256' => $validatedFile['file_hash_sha256']],
                    $actor,
                    $locked->administrative_unit_id
                );

                return $locked;
            });
        } catch (Throwable $e) {
            $this->storage->delete($stored['disk'], $stored['path']);
            throw $e;
        }

        $this->bumpDashboardCache();

        return $this->loadDetail($updated);
    }

    public function restoreVersion(Document $document, DocumentVersion $version, User $actor): Document
    {
        if ($version->document_id !== $document->id) {
            throw new NotFoundHttpException('Requested document version does not belong to this document.');
        }
        if ($version->scan_status !== VirusScannerInterface::STATUS_CLEAN) {
            throw ValidationException::withMessages([
                'version' => ['Cannot restore a version that did not pass virus scanning.'],
            ]);
        }

        $updated = DB::transaction(function () use ($document, $version, $actor) {
            $locked = Document::whereKey($document->id)->lockForUpdate()->firstOrFail();

            DocumentVersion::where('document_id', $locked->id)->update(['is_current' => false]);
            $version->update(['is_current' => true]);

            $locked->update([
                'current_version_number' => $version->version_number,
                'current_version_id' => $version->id,
                'original_filename' => $version->original_filename,
                'storage_disk' => $version->storage_disk,
                'storage_path' => $version->storage_path,
                'mime_type' => $version->mime_type,
                'file_extension' => $version->file_extension,
                'file_size_bytes' => $version->file_size_bytes,
                'file_hash_sha256' => $version->file_hash_sha256,
                'scan_status' => $version->scan_status,
                'scanned_at' => $version->scanned_at,
                'scan_engine' => $version->scan_engine,
                'updated_by' => $actor->id,
            ]);

            $this->recordAccessLog(
                $locked,
                $version->id,
                $actor,
                DocumentAccessLog::ACTION_VERSION_RESTORED,
                ['restored_version_number' => $version->version_number]
            );

            AuditLog::log(
                'DOCUMENT_VERSION_RESTORED',
                Document::class,
                $locked->id,
                null,
                ['restored_version_number' => $version->version_number, 'version_id' => $version->id],
                $actor,
                $locked->administrative_unit_id
            );

            return $locked;
        });

        $this->bumpDashboardCache();

        return $this->loadDetail($updated);
    }

    public function submitForApproval(Document $document, User $actor): Document
    {
        $updated = DB::transaction(function () use ($document, $actor) {
            $locked = Document::whereKey($document->id)->lockForUpdate()->firstOrFail();
            if (!in_array($locked->approval_status, [Document::STATUS_DRAFT, Document::STATUS_REJECTED], true)) {
                throw ValidationException::withMessages([
                    'approval_status' => ['Only DRAFT or REJECTED documents can be submitted for approval.'],
                ]);
            }

            $old = ['approval_status' => $locked->approval_status];
            $locked->update([
                'approval_status' => Document::STATUS_PENDING_APPROVAL,
                'submitted_by' => $actor->id,
                'submitted_at' => now(),
                'updated_by' => $actor->id,
            ]);

            $this->recordAccessLog($locked, $locked->current_version_id, $actor, DocumentAccessLog::ACTION_SUBMITTED_FOR_APPROVAL);
            AuditLog::log('DOCUMENT_SUBMITTED_FOR_APPROVAL', Document::class, $locked->id, $old, ['approval_status' => Document::STATUS_PENDING_APPROVAL], $actor, $locked->administrative_unit_id);

            return $locked;
        });

        $this->bumpDashboardCache();

        return $this->loadDetail($updated);
    }

    public function approveDocument(Document $document, User $actor, ?string $notes = null): Document
    {
        $updated = DB::transaction(function () use ($document, $actor, $notes) {
            $locked = Document::whereKey($document->id)->lockForUpdate()->firstOrFail();
            if (!in_array($locked->approval_status, [Document::STATUS_DRAFT, Document::STATUS_PENDING_APPROVAL], true)) {
                throw ValidationException::withMessages([
                    'approval_status' => ['Document must be in DRAFT or PENDING_APPROVAL state to be approved.'],
                ]);
            }

            if ($locked->scan_status !== VirusScannerInterface::STATUS_CLEAN) {
                throw ValidationException::withMessages([
                    'scan_status' => ['Cannot approve a document that has not passed antivirus scanning.'],
                ]);
            }

            $old = ['approval_status' => $locked->approval_status];
            $locked->update([
                'approval_status' => Document::STATUS_APPROVED,
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'approval_notes' => $notes,
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
                'updated_by' => $actor->id,
            ]);

            $this->recordAccessLog($locked, $locked->current_version_id, $actor, DocumentAccessLog::ACTION_APPROVED, ['notes' => $notes]);
            AuditLog::log('DOCUMENT_APPROVED', Document::class, $locked->id, $old, ['approval_status' => Document::STATUS_APPROVED, 'notes' => $notes], $actor, $locked->administrative_unit_id);

            return $locked;
        });

        $this->bumpDashboardCache();

        return $this->loadDetail($updated);
    }

    public function rejectDocument(Document $document, User $actor, string $reason): Document
    {
        $updated = DB::transaction(function () use ($document, $actor, $reason) {
            $locked = Document::whereKey($document->id)->lockForUpdate()->firstOrFail();
            if (!in_array($locked->approval_status, [Document::STATUS_DRAFT, Document::STATUS_PENDING_APPROVAL], true)) {
                throw ValidationException::withMessages([
                    'approval_status' => ['Only DRAFT or PENDING_APPROVAL documents can be rejected.'],
                ]);
            }

            $old = ['approval_status' => $locked->approval_status];
            $locked->update([
                'approval_status' => Document::STATUS_REJECTED,
                'rejected_by' => $actor->id,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
                'updated_by' => $actor->id,
            ]);

            $this->recordAccessLog($locked, $locked->current_version_id, $actor, DocumentAccessLog::ACTION_REJECTED, ['reason' => $reason]);
            AuditLog::log('DOCUMENT_REJECTED', Document::class, $locked->id, $old, ['approval_status' => Document::STATUS_REJECTED, 'reason' => $reason], $actor, $locked->administrative_unit_id);

            return $locked;
        });

        $this->bumpDashboardCache();

        return $this->loadDetail($updated);
    }

    public function archiveDocument(Document $document, User $actor, ?string $notes = null): Document
    {
        $updated = DB::transaction(function () use ($document, $actor, $notes) {
            $locked = Document::whereKey($document->id)->lockForUpdate()->firstOrFail();
            $old = ['approval_status' => $locked->approval_status];
            $locked->update([
                'approval_status' => Document::STATUS_ARCHIVED,
                'approval_notes' => $notes ?: $locked->approval_notes,
                'updated_by' => $actor->id,
            ]);

            $this->recordAccessLog($locked, $locked->current_version_id, $actor, DocumentAccessLog::ACTION_ARCHIVED, ['notes' => $notes]);
            AuditLog::log('DOCUMENT_ARCHIVED', Document::class, $locked->id, $old, ['approval_status' => Document::STATUS_ARCHIVED], $actor, $locked->administrative_unit_id);

            return $locked;
        });

        $this->bumpDashboardCache();

        return $this->loadDetail($updated);
    }

    public function streamDocument(
        Document $document,
        User $actor,
        ?DocumentVersion $version = null,
        bool $inline = false
    ): StreamedResponse {
        $targetVersion = $version ?: $document->versions()->where('is_current', true)->first();

        if ($version && $version->document_id !== $document->id) {
            throw new NotFoundHttpException('Requested version does not belong to this document.');
        }

        $disk = $targetVersion?->storage_disk ?? $document->storage_disk;
        $path = $targetVersion?->storage_path ?? $document->storage_path;
        $filename = $targetVersion?->original_filename ?? $document->original_filename;
        $mime = $targetVersion?->mime_type ?? $document->mime_type;

        if ($inline && !in_array($mime, Document::PREVIEWABLE_MIMES, true)) {
            throw ValidationException::withMessages([
                'preview' => ["Inline browser preview is not supported for '{$mime}'. Please download the file instead."],
            ]);
        }

        $this->recordAccessLog(
            $document,
            $targetVersion?->id,
            $actor,
            $inline ? DocumentAccessLog::ACTION_PREVIEWED : DocumentAccessLog::ACTION_DOWNLOADED,
            ['version_number' => $targetVersion?->version_number ?? $document->current_version_number]
        );

        return $this->storage->streamResponse($disk, $path, $filename, $mime, $inline);
    }

    public function generateSignedUrl(
        Document $document,
        User $actor,
        ?string $versionId = null,
        ?int $requestedTtlMinutes = null,
        bool $inline = false
    ): array {
        $maxTtl = max(1, (int) config('services.document.signed_url_ttl_minutes', 15));
        $ttlMinutes = $requestedTtlMinutes ? min($maxTtl, max(1, $requestedTtlMinutes)) : $maxTtl;
        $expiresAt = now()->addMinutes($ttlMinutes);

        $params = ['document' => $document->id];
        if ($versionId) {
            $params['version_id'] = $versionId;
        }
        if ($inline) {
            $params['inline'] = '1';
        }

        $signedUrl = URL::temporarySignedRoute('api.v1.documents.stream', $expiresAt, $params);

        $this->recordAccessLog(
            $document,
            $versionId ?: $document->current_version_id,
            $actor,
            DocumentAccessLog::ACTION_SIGNED_URL_ISSUED,
            ['ttl_minutes' => $ttlMinutes, 'expires_at' => $expiresAt->toIso8601String()]
        );

        return [
            'url' => $signedUrl,
            'expires_at' => $expiresAt->toIso8601String(),
            'ttl_minutes' => $ttlMinutes,
        ];
    }

    public function verifyIntegrity(Document $document, User $actor, ?DocumentVersion $version = null): array
    {
        $targetVersion = $version ?: $document->versions()->where('is_current', true)->first();
        $disk = $targetVersion?->storage_disk ?? $document->storage_disk;
        $path = $targetVersion?->storage_path ?? $document->storage_path;
        $expectedHash = $targetVersion?->file_hash_sha256 ?? $document->file_hash_sha256;

        $exists = $this->storage->exists($disk, $path);
        $actualHash = null;
        $matches = false;

        if ($exists) {
            $bytes = $this->storage->get($disk, $path);
            $actualHash = hash('sha256', $bytes);
            $matches = hash_equals($expectedHash, $actualHash);
        }

        $result = [
            'document_id' => $document->id,
            'document_no' => $document->document_no,
            'version_number' => $targetVersion?->version_number ?? $document->current_version_number,
            'storage_exists' => $exists,
            'expected_sha256' => $expectedHash,
            'actual_sha256' => $actualHash,
            'checksum_verified' => $matches,
            'scan_status' => $targetVersion?->scan_status ?? $document->scan_status,
            'scan_engine' => $targetVersion?->scan_engine ?? $document->scan_engine,
            'verified_at' => now()->toIso8601String(),
        ];

        $this->recordAccessLog(
            $document,
            $targetVersion?->id,
            $actor,
            DocumentAccessLog::ACTION_INTEGRITY_VERIFIED,
            $result
        );

        AuditLog::log(
            'DOCUMENT_INTEGRITY_VERIFIED',
            Document::class,
            $document->id,
            null,
            $result,
            $actor,
            $document->administrative_unit_id
        );

        return $result;
    }

    public function deleteDocument(Document $document, User $actor): void
    {
        DB::transaction(function () use ($document, $actor) {
            $locked = Document::whereKey($document->id)->lockForUpdate()->firstOrFail();

            $this->recordAccessLog($locked, $locked->current_version_id, $actor, DocumentAccessLog::ACTION_DELETED);
            AuditLog::log(
                'DOCUMENT_DELETED',
                Document::class,
                $locked->id,
                ['document_no' => $locked->document_no, 'approval_status' => $locked->approval_status],
                null,
                $actor,
                $locked->administrative_unit_id
            );

            $locked->delete();
        });

        $this->bumpDashboardCache();
    }

    public function getHistory(Document $document): array
    {
        $accessLogs = $document->accessLogs()
            ->with(['user:id,full_name_en,full_name_bn,role_code', 'version:id,version_number,version_label'])
            ->limit(100)
            ->get();

        $auditLogs = AuditLog::with('user:id,full_name_en,full_name_bn,role_code')
            ->where('auditable_type', Document::class)
            ->where('auditable_id', $document->id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return [
            'access_logs' => $accessLogs,
            'audit_logs' => $auditLogs,
        ];
    }

    public function loadDetail(Document $document): Document
    {
        return $document->load([
            'category',
            'organization:id,org_code,name_en,name_bn',
            'administrativeUnit:id,code,name_en,name_bn,tier_level,ancestry_path',
            'owner:id,full_name_en,full_name_bn,role_code',
            'uploader:id,full_name_en,full_name_bn,role_code',
            'submitter:id,full_name_en,full_name_bn,role_code',
            'approver:id,full_name_en,full_name_bn,role_code',
            'rejecter:id,full_name_en,full_name_bn,role_code',
            'versions.uploader:id,full_name_en,full_name_bn,role_code',
        ]);
    }

    public function recordAccessLog(
        Document $document,
        ?string $versionId,
        User $actor,
        string $action,
        ?array $details = null
    ): void {
        DocumentAccessLog::create([
            'id' => (string) Str::uuid(),
            'document_id' => $document->id,
            'document_version_id' => $versionId,
            'user_id' => $actor->id,
            'administrative_unit_id' => $actor->administrative_unit_id,
            'action' => $action,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'details' => $details,
            'created_at' => now(),
        ]);
    }

    public function listCategories(bool $activeOnly = false)
    {
        $query = DocumentCategory::query()->withCount('documents');
        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->orderBy('sort_order')->orderBy('name_en')->get();
    }

    public function createCategory(User $actor, array $data): DocumentCategory
    {
        $category = DocumentCategory::create([
            'id' => (string) Str::uuid(),
            'code' => strtoupper(trim((string) $data['code'])),
            'name_en' => trim((string) $data['name_en']),
            'name_bn' => trim((string) $data['name_bn']),
            'description_en' => $data['description_en'] ?? null,
            'description_bn' => $data['description_bn'] ?? null,
            'default_classification' => strtoupper((string) ($data['default_classification'] ?? Document::CLASS_INTERNAL)),
            'allowed_formats' => $data['allowed_formats'] ?? null,
            'retention_days' => $data['retention_days'] ?? null,
            'requires_approval' => (bool) ($data['requires_approval'] ?? false),
            'is_system' => false,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => (int) ($data['sort_order'] ?? 50),
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        AuditLog::log('DOCUMENT_CATEGORY_CREATED', DocumentCategory::class, $category->id, null, $category->toArray(), $actor);
        $this->bumpDashboardCache();

        return $category;
    }

    public function updateCategory(DocumentCategory $category, User $actor, array $data): DocumentCategory
    {
        $old = $category->only(['name_en', 'name_bn', 'default_classification', 'is_active', 'requires_approval']);
        $category->update([
            'name_en' => $data['name_en'] ?? $category->name_en,
            'name_bn' => $data['name_bn'] ?? $category->name_bn,
            'description_en' => array_key_exists('description_en', $data) ? $data['description_en'] : $category->description_en,
            'description_bn' => array_key_exists('description_bn', $data) ? $data['description_bn'] : $category->description_bn,
            'default_classification' => isset($data['default_classification'])
                ? strtoupper((string) $data['default_classification'])
                : $category->default_classification,
            'allowed_formats' => array_key_exists('allowed_formats', $data) ? $data['allowed_formats'] : $category->allowed_formats,
            'retention_days' => array_key_exists('retention_days', $data) ? $data['retention_days'] : $category->retention_days,
            'requires_approval' => array_key_exists('requires_approval', $data) ? (bool) $data['requires_approval'] : $category->requires_approval,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $category->is_active,
            'sort_order' => $data['sort_order'] ?? $category->sort_order,
            'updated_by' => $actor->id,
        ]);

        AuditLog::log('DOCUMENT_CATEGORY_UPDATED', DocumentCategory::class, $category->id, $old, $category->only(array_keys($old)), $actor);
        $this->bumpDashboardCache();

        return $category->fresh();
    }

    public function deleteCategory(DocumentCategory $category, User $actor): void
    {
        if ($category->is_system) {
            throw ValidationException::withMessages([
                'category' => ['Built-in system document categories cannot be deleted.'],
            ]);
        }

        if ($category->documents()->exists()) {
            throw ValidationException::withMessages([
                'category' => ['Cannot delete category while documents are assigned to it.'],
            ]);
        }

        AuditLog::log('DOCUMENT_CATEGORY_DELETED', DocumentCategory::class, $category->id, ['code' => $category->code], null, $actor);
        $category->delete();
        $this->bumpDashboardCache();
    }

    protected function scopedBaseQuery(User $actor): Builder
    {
        $query = Document::query();

        // External partners are restricted to PUBLIC, APPROVED, non-confidential documents
        if ($actor->hasRole('ROLE_EXTERNAL_PARTNER') && !$actor->isNationalScope()) {
            return $query->where('classification', Document::CLASS_PUBLIC)
                ->where('approval_status', Document::STATUS_APPROVED)
                ->where('is_confidential', false);
        }

        $query->inJurisdiction($actor);

        // Non-privileged field roles cannot browse other officers' RESTRICTED documents
        $hasRestrictedClearance = $actor->isNationalScope()
            || $actor->hasRole('ROLE_DIVISIONAL_COMMISSIONER')
            || $actor->hasRole('ROLE_DEPUTY_COMMISSIONER')
            || $actor->hasRole('ROLE_DRRO')
            || $actor->hasRole('ROLE_UNO')
            || $actor->hasRole('ROLE_PIO')
            || $actor->hasRole('ROLE_NATIONAL_AUDITOR');

        if (!$hasRestrictedClearance) {
            $query->where(function ($q) use ($actor) {
                $q->where('classification', '!=', Document::CLASS_RESTRICTED)
                    ->orWhere('uploaded_by', $actor->id)
                    ->orWhere('owner_user_id', $actor->id);
            });
        }

        return $query;
    }

    protected function generateDocumentNo(): string
    {
        return 'DOC-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
    }

    protected function normalizeTags(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : explode(',', $raw);
        }

        if (!is_array($raw)) {
            return [];
        }

        $tags = [];
        foreach ($raw as $item) {
            $clean = trim((string) $item);
            if ($clean !== '') {
                $tags[] = mb_substr($clean, 0, 50);
            }
        }

        return array_values(array_unique(array_slice($tags, 0, 20)));
    }
}
