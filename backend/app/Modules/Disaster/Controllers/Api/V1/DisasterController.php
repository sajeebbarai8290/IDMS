<?php

namespace App\Modules\Disaster\Controllers\Api\V1;

use App\Core\Models\AuditLog;
use App\Modules\Disaster\Models\DisasterEvent;
use App\Modules\Disaster\Models\DisasterTimeline;
use App\Modules\Disaster\Models\DisasterWarning;
use App\Modules\Disaster\Requests\StoreDisasterBoundaryRequest;
use App\Modules\Disaster\Requests\StoreDisasterRequest;
use App\Modules\Disaster\Requests\StoreDisasterTimelineRequest;
use App\Modules\Disaster\Requests\StoreDisasterWarningRequest;
use App\Modules\Disaster\Requests\TransitionDisasterLifecycleRequest;
use App\Modules\Disaster\Requests\UpdateDisasterImpactRequest;
use App\Modules\Disaster\Requests\UpdateDisasterRequest;
use App\Modules\Disaster\Resources\DisasterDetailResource;
use App\Modules\Disaster\Resources\DisasterEventResource;
use App\Modules\Disaster\Resources\DisasterImpactResource;
use App\Modules\Disaster\Resources\DisasterTimelineResource;
use App\Modules\Disaster\Resources\DisasterWarningResource;
use App\Modules\Disaster\Services\DisasterManagementService;
use App\Modules\Disaster\Services\DisasterSpatialService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class DisasterController extends Controller
{
    public function __construct(
        protected DisasterManagementService $disasterService,
        protected DisasterSpatialService $spatialService,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', DisasterEvent::class);

        return response()->json([
            'success' => true,
            'data' => $this->disasterService->getDashboardAnalytics($request->user()),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function geojson(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', DisasterEvent::class);

        $query = $this->buildFilteredQuery($request);
        $events = $query->with(['impact', 'locations.administrativeUnit'])->get();

        return response()->json([
            'success' => true,
            'geojson' => $this->spatialService->toFeatureCollection($events),
            'count' => $events->count(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function warnings(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', DisasterEvent::class);

        $user = $request->user();
        $accessibleEventIds = DisasterEvent::inJurisdiction($user)->select('id');

        $query = DisasterWarning::with(['disasterEvent:id,disaster_code,name_en,name_bn,disaster_type', 'issuer:id,full_name_en'])
            ->whereIn('disaster_event_id', $accessibleEventIds)
            ->orderByDesc('issued_at');

        if ($status = $request->query('status')) {
            $query->where('status', strtoupper((string) $status));
        }

        if ($level = $request->query('warning_level')) {
            $query->where('warning_level', strtoupper((string) $level));
        }

        $perPage = min(50, max(5, (int) $request->query('per_page', 15)));
        $warnings = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => DisasterWarningResource::collection($warnings->items()),
            'pagination' => [
                'current_page' => $warnings->currentPage(),
                'per_page' => $warnings->perPage(),
                'total' => $warnings->total(),
                'last_page' => $warnings->lastPage(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', DisasterEvent::class);

        $query = $this->buildFilteredQuery($request)->with([
            'category',
            'impact',
            'locations.administrativeUnit',
            'warnings',
        ]);

        $sortBy = (string) $request->query('sort_by', 'declaration_date');
        $sortOrder = strtolower((string) $request->query('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['disaster_code', 'name_en', 'disaster_type', 'severity_level', 'lifecycle_phase', 'declaration_date', 'created_at'];

        $query->orderBy(in_array($sortBy, $allowedSorts, true) ? $sortBy : 'declaration_date', $sortOrder);

        $perPage = min(100, max(5, (int) $request->query('per_page', 15)));
        $events = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => DisasterEventResource::collection($events->items()),
            'pagination' => [
                'current_page' => $events->currentPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
                'last_page' => $events->lastPage(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function store(StoreDisasterRequest $request): JsonResponse
    {
        Gate::authorize('create', DisasterEvent::class);

        $event = $this->disasterService->createDisaster($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Disaster event registered successfully.',
            'disaster' => new DisasterDetailResource($event),
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function show(DisasterEvent $event): JsonResponse
    {
        Gate::authorize('view', $event);

        $event->load([
            'category',
            'locations.administrativeUnit',
            'impact',
            'warnings.issuer',
            'timelines.recorder',
            'creator',
            'updater',
            'verifier',
        ]);

        return response()->json([
            'success' => true,
            'disaster' => new DisasterDetailResource($event),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function update(UpdateDisasterRequest $request, DisasterEvent $event): JsonResponse
    {
        Gate::authorize('update', $event);

        $updated = $this->disasterService->updateDisaster($event, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Disaster event updated successfully.',
            'disaster' => new DisasterDetailResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function destroy(Request $request, DisasterEvent $event): JsonResponse
    {
        Gate::authorize('delete', $event);

        $event->delete();

        AuditLog::log(
            'DISASTER_EVENT_ARCHIVED',
            DisasterEvent::class,
            $event->id,
            ['disaster_code' => $event->disaster_code],
            null,
            $request->user()
        );

        $this->disasterService->clearAnalyticsCache();

        return response()->json([
            'success' => true,
            'message' => 'Disaster event archived successfully.',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function updateImpact(UpdateDisasterImpactRequest $request, DisasterEvent $event): JsonResponse
    {
        Gate::authorize('update', $event);

        $impact = $this->disasterService->updateImpact($event, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Disaster impact assessment updated.',
            'impact' => new DisasterImpactResource($impact),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function transitionLifecycle(TransitionDisasterLifecycleRequest $request, DisasterEvent $event): JsonResponse
    {
        $targetPhase = DisasterEvent::normalizeLifecyclePhase($request->validated('lifecycle_phase'));

        if (in_array($targetPhase, [DisasterEvent::PHASE_VERIFIED, DisasterEvent::PHASE_ACTIVE, DisasterEvent::PHASE_CLOSED], true)) {
            Gate::authorize('verify', $event);
        } else {
            Gate::authorize('update', $event);
        }

        $updated = $this->disasterService->transitionLifecycle(
            $event,
            $targetPhase,
            $request->validated('notes'),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => "Disaster transitioned to {$updated->lifecycle_phase}.",
            'disaster' => new DisasterDetailResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function issueWarning(StoreDisasterWarningRequest $request, DisasterEvent $event): JsonResponse
    {
        Gate::authorize('broadcastWarning', $event);

        $warning = $this->disasterService->issueWarning($event, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Early warning issued and queued for dispatch.',
            'warning' => new DisasterWarningResource($warning),
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function addTimeline(StoreDisasterTimelineRequest $request, DisasterEvent $event): JsonResponse
    {
        Gate::authorize('update', $event);

        $entry = $this->disasterService->addTimelineEntry(
            $event,
            $request->validated(),
            $request->file('attachment'),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Timeline entry recorded.',
            'timeline' => new DisasterTimelineResource($entry),
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    public function downloadAttachment(DisasterEvent $event, DisasterTimeline $timeline)
    {
        Gate::authorize('view', $event);

        if ($timeline->disaster_event_id !== $event->id) {
            abort(404);
        }

        return $this->disasterService->getAttachmentStreamResponse($timeline);
    }

    public function uploadBoundary(StoreDisasterBoundaryRequest $request, DisasterEvent $event): JsonResponse
    {
        Gate::authorize('update', $event);

        $validated = $request->validated();

        $updated = $this->disasterService->uploadBoundary(
            event: $event,
            geojson: $validated['affected_zone_geojson'],
            lat: isset($validated['epicenter_lat']) ? (float) $validated['epicenter_lat'] : null,
            lng: isset($validated['epicenter_lng']) ? (float) $validated['epicenter_lng'] : null,
            sourceFilename: $validated['source_filename'] ?? null,
            shapefileArchive: $request->file('shapefile_archive'),
            notes: $validated['notes'] ?? null,
            user: $request->user(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Disaster boundary updated successfully.',
            'disaster' => new DisasterDetailResource($updated),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    protected function buildFilteredQuery(Request $request): Builder
    {
        $query = DisasterEvent::inJurisdiction($request->user());

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('disaster_code', 'like', "%{$search}%")
                    ->orWhere('name_en', 'like', "%{$search}%")
                    ->orWhere('name_bn', 'like', "%{$search}%")
                    ->orWhere('affected_areas_summary', 'like', "%{$search}%");
            });
        }

        if ($type = $request->query('disaster_type')) {
            $query->where('disaster_type', strtoupper((string) $type));
        }

        if ($severity = $request->query('severity_level')) {
            $query->where('severity_level', strtoupper((string) $severity));
        }

        if ($phase = $request->query('lifecycle_phase')) {
            $query->where('lifecycle_phase', DisasterEvent::normalizeLifecyclePhase((string) $phase));
        }

        if ($request->boolean('active_only')) {
            $query->whereIn('lifecycle_phase', [
                DisasterEvent::PHASE_REPORTED,
                DisasterEvent::PHASE_UNDER_ASSESSMENT,
                DisasterEvent::PHASE_VERIFIED,
                DisasterEvent::PHASE_ACTIVE,
                DisasterEvent::PHASE_RESPONSE_ONGOING,
            ]);
        }

        if ($adminUnitId = $request->query('administrative_unit_id')) {
            $query->whereHas('locations', fn ($q) => $q->where('administrative_unit_id', $adminUnitId));
        }

        if ($dateFrom = $request->query('date_from')) {
            $query->whereDate('declaration_date', '>=', $dateFrom);
        }

        if ($dateTo = $request->query('date_to')) {
            $query->whereDate('declaration_date', '<=', $dateTo);
        }

        if ($bbox = $request->query('bbox')) {
            $parts = array_map('trim', explode(',', (string) $bbox));
            if (count($parts) === 4) {
                $this->spatialService->applyBoundingBoxFilter(
                    $query,
                    (float) $parts[0],
                    (float) $parts[1],
                    (float) $parts[2],
                    (float) $parts[3]
                );
            }
        }

        if ($request->filled(['lat', 'lng', 'radius_km'])) {
            $this->spatialService->applyRadiusFilter(
                $query,
                (float) $request->query('lat'),
                (float) $request->query('lng'),
                (float) $request->query('radius_km')
            );
        }

        return $query;
    }
}
