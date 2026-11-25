<?php

namespace App\Modules\Disaster\Services;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\AuditLog;
use App\Core\Models\User;
use App\Core\Services\AdministrativeScopeService;
use App\Modules\Disaster\Jobs\DispatchDisasterWarningJob;
use App\Modules\Disaster\Models\DisasterCategory;
use App\Modules\Disaster\Models\DisasterEvent;
use App\Modules\Disaster\Models\DisasterImpact;
use App\Modules\Disaster\Models\DisasterLocation;
use App\Modules\Disaster\Models\DisasterTimeline;
use App\Modules\Disaster\Models\DisasterWarning;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Class DisasterManagementService
 *
 * Orchestrates disaster registration, multi-location scoping, impact tracking,
 * 7-stage lifecycle transitions, early warnings, timeline attachments, and dashboard analytics.
 */
class DisasterManagementService
{
    public function __construct(
        protected DisasterSpatialService $spatialService,
        protected AdministrativeScopeService $scopeService,
    ) {}

    /**
     * Register a new disaster event with optional locations and initial impact estimate.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function createDisaster(array $data, User $user): DisasterEvent
    {
        $isNational = (bool) ($data['is_national'] ?? false);
        if ($isNational && !$user->isNationalScope()) {
            throw new AuthorizationException('Only National/Directorate officers may declare nationwide disasters.');
        }

        $category = null;
        if (!empty($data['category_id'])) {
            $category = DisasterCategory::find($data['category_id']);
        } elseif (!empty($data['disaster_type'])) {
            $category = DisasterCategory::where('code', strtoupper((string) $data['disaster_type']))->first();
        }

        $disasterType = strtoupper((string) ($data['disaster_type'] ?? $category?->code ?? 'OTHER'));
        $code = !empty($data['disaster_code'])
            ? strtoupper(trim((string) $data['disaster_code']))
            : $this->generateDisasterCode($disasterType);

        if (DisasterEvent::where('disaster_code', $code)->exists()) {
            throw ValidationException::withMessages([
                'disaster_code' => 'A disaster with this identification number already exists.',
            ]);
        }

        $phase = DisasterEvent::normalizeLifecyclePhase($data['lifecycle_phase'] ?? DisasterEvent::PHASE_REPORTED);
        $locationsInput = $this->resolveAndValidateLocations($data['locations'] ?? [], $isNational, $user);

        return DB::transaction(function () use ($data, $user, $category, $disasterType, $code, $phase, $isNational, $locationsInput) {
            $event = DisasterEvent::create([
                'category_id' => $category?->id,
                'disaster_code' => $code,
                'name_en' => trim((string) $data['name_en']),
                'name_bn' => trim((string) $data['name_bn']),
                'disaster_type' => $disasterType,
                'subtype' => $data['subtype'] ?? null,
                'description_en' => $data['description_en'] ?? null,
                'description_bn' => $data['description_bn'] ?? null,
                'severity_level' => strtoupper((string) ($data['severity_level'] ?? 'WARNING')),
                'lifecycle_phase' => $phase,
                'declaration_date' => $data['declaration_date'] ?? now(),
                'start_date' => $data['start_date'] ?? now()->toDateString(),
                'end_date' => $data['end_date'] ?? null,
                'epicenter_lat' => $data['epicenter_lat'] ?? null,
                'epicenter_lng' => $data['epicenter_lng'] ?? null,
                'affected_areas_summary' => $data['affected_areas_summary'] ?? null,
                'affected_zone_geojson' => $this->spatialService->normalizeToPolygonGeometry($data['affected_zone_geojson'] ?? null),
                'is_national' => $isNational,
                'is_emergency_declared' => (bool) ($data['is_emergency_declared'] ?? false),
                'created_by' => $user->id,
                'updated_by' => $user->id,
                'verified_by' => in_array($phase, [DisasterEvent::PHASE_VERIFIED, DisasterEvent::PHASE_ACTIVE], true) ? $user->id : null,
                'verified_at' => in_array($phase, [DisasterEvent::PHASE_VERIFIED, DisasterEvent::PHASE_ACTIVE], true) ? now() : null,
            ]);

            $this->spatialService->syncEventGeometry($event);
            $this->syncLocations($event, $locationsInput);

            DisasterImpact::create(array_merge(
                $this->extractImpactPayload($data['impact'] ?? []),
                [
                    'disaster_event_id' => $event->id,
                    'updated_by' => $user->id,
                ]
            ));

            DisasterTimeline::create([
                'disaster_event_id' => $event->id,
                'event_type' => 'CREATED',
                'to_phase' => $phase,
                'title_en' => 'Disaster Registered: ' . $event->name_en,
                'title_bn' => 'দুর্যোগ নিবন্ধিত: ' . $event->name_bn,
                'notes' => $data['description_en'] ?? $data['affected_areas_summary'] ?? null,
                'recorded_by' => $user->id,
                'occurred_at' => $event->declaration_date ?? now(),
            ]);

            AuditLog::log(
                'DISASTER_EVENT_CREATED',
                DisasterEvent::class,
                $event->id,
                null,
                [
                    'disaster_code' => $event->disaster_code,
                    'disaster_type' => $event->disaster_type,
                    'severity_level' => $event->severity_level,
                    'lifecycle_phase' => $event->lifecycle_phase,
                    'locations_count' => count($locationsInput),
                ],
                $user,
                $locationsInput[0]['administrative_unit_id'] ?? null
            );

            $this->clearAnalyticsCache();

            return $event->fresh(['category', 'locations.administrativeUnit', 'impact', 'warnings', 'timelines.recorder', 'creator']);
        });
    }

    /**
     * Update disaster details, locations, GIS geometry, and impact estimates.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function updateDisaster(DisasterEvent $event, array $data, User $user): DisasterEvent
    {
        $isNational = array_key_exists('is_national', $data) ? (bool) $data['is_national'] : (bool) $event->is_national;
        if ($isNational && !$event->is_national && !$user->isNationalScope()) {
            throw new AuthorizationException('Only National/Directorate officers may mark a disaster as nationwide.');
        }

        $oldSnapshot = [
            'severity_level' => $event->severity_level,
            'lifecycle_phase' => $event->lifecycle_phase,
            'is_emergency_declared' => $event->is_emergency_declared,
        ];

        $locationsInput = array_key_exists('locations', $data)
            ? $this->resolveAndValidateLocations($data['locations'] ?? [], $isNational, $user)
            : null;

        return DB::transaction(function () use ($event, $data, $user, $isNational, $locationsInput, $oldSnapshot) {
            $categoryId = $event->category_id;
            $disasterType = $event->disaster_type;

            if (!empty($data['category_id'])) {
                $cat = DisasterCategory::find($data['category_id']);
                if ($cat) {
                    $categoryId = $cat->id;
                    $disasterType = strtoupper((string) ($data['disaster_type'] ?? $cat->code));
                }
            } elseif (!empty($data['disaster_type'])) {
                $disasterType = strtoupper((string) $data['disaster_type']);
                $categoryId = DisasterCategory::where('code', $disasterType)->value('id') ?? $categoryId;
            }

            $event->update([
                'category_id' => $categoryId,
                'name_en' => $data['name_en'] ?? $event->name_en,
                'name_bn' => $data['name_bn'] ?? $event->name_bn,
                'disaster_type' => $disasterType,
                'subtype' => array_key_exists('subtype', $data) ? $data['subtype'] : $event->subtype,
                'description_en' => array_key_exists('description_en', $data) ? $data['description_en'] : $event->description_en,
                'description_bn' => array_key_exists('description_bn', $data) ? $data['description_bn'] : $event->description_bn,
                'severity_level' => isset($data['severity_level']) ? strtoupper((string) $data['severity_level']) : $event->severity_level,
                'declaration_date' => $data['declaration_date'] ?? $event->declaration_date,
                'start_date' => array_key_exists('start_date', $data) ? $data['start_date'] : $event->start_date,
                'end_date' => array_key_exists('end_date', $data) ? $data['end_date'] : $event->end_date,
                'epicenter_lat' => array_key_exists('epicenter_lat', $data) ? $data['epicenter_lat'] : $event->epicenter_lat,
                'epicenter_lng' => array_key_exists('epicenter_lng', $data) ? $data['epicenter_lng'] : $event->epicenter_lng,
                'affected_areas_summary' => array_key_exists('affected_areas_summary', $data) ? $data['affected_areas_summary'] : $event->affected_areas_summary,
                'affected_zone_geojson' => array_key_exists('affected_zone_geojson', $data)
                    ? $this->spatialService->normalizeToPolygonGeometry($data['affected_zone_geojson'])
                    : $event->affected_zone_geojson,
                'is_national' => $isNational,
                'is_emergency_declared' => array_key_exists('is_emergency_declared', $data)
                    ? (bool) $data['is_emergency_declared']
                    : $event->is_emergency_declared,
                'updated_by' => $user->id,
            ]);

            $this->spatialService->syncEventGeometry($event);

            if ($locationsInput !== null) {
                $this->syncLocations($event, $locationsInput);
            }

            if (!empty($data['impact']) && is_array($data['impact'])) {
                $this->upsertImpact($event, $data['impact'], $user);
            }

            DisasterTimeline::create([
                'disaster_event_id' => $event->id,
                'event_type' => 'SITUATION_UPDATE',
                'title_en' => 'Disaster Profile Updated',
                'title_bn' => 'দুর্যোগ তথ্য হালনাগাদ',
                'notes' => $data['update_notes'] ?? 'Updated disaster details, locations, or impact estimates.',
                'recorded_by' => $user->id,
                'occurred_at' => now(),
            ]);

            AuditLog::log(
                'DISASTER_EVENT_UPDATED',
                DisasterEvent::class,
                $event->id,
                $oldSnapshot,
                [
                    'severity_level' => $event->severity_level,
                    'lifecycle_phase' => $event->lifecycle_phase,
                    'is_emergency_declared' => $event->is_emergency_declared,
                ],
                $user
            );

            $this->clearAnalyticsCache();

            return $event->fresh(['category', 'locations.administrativeUnit', 'impact', 'warnings', 'timelines.recorder', 'creator']);
        });
    }

    /**
     * Update impact estimates independently.
     */
    public function updateImpact(DisasterEvent $event, array $impactData, User $user): DisasterImpact
    {
        return DB::transaction(function () use ($event, $impactData, $user) {
            $impact = $this->upsertImpact($event, $impactData, $user);

            DisasterTimeline::create([
                'disaster_event_id' => $event->id,
                'event_type' => 'IMPACT_UPDATE',
                'title_en' => 'Impact Assessment Updated',
                'title_bn' => 'ক্ষয়ক্ষতি ও প্রভাব তথ্য হালনাগাদ',
                'notes' => sprintf(
                    'Affected population: %d, Households: %d, Deaths: %d, Economic loss: BDT %s',
                    $impact->affected_population,
                    $impact->affected_households,
                    $impact->deaths_count,
                    number_format((float) $impact->total_economic_loss_bdt, 2)
                ),
                'recorded_by' => $user->id,
                'occurred_at' => now(),
            ]);

            AuditLog::log(
                'DISASTER_IMPACT_UPDATED',
                DisasterImpact::class,
                $impact->id,
                null,
                [
                    'disaster_code' => $event->disaster_code,
                    'affected_population' => $impact->affected_population,
                    'deaths_count' => $impact->deaths_count,
                    'total_economic_loss_bdt' => $impact->total_economic_loss_bdt,
                ],
                $user
            );

            $this->clearAnalyticsCache();

            return $impact->fresh();
        });
    }

    /**
     * Transition disaster lifecycle phase with historical timeline tracking.
     *
     * @throws ValidationException
     */
    public function transitionLifecycle(DisasterEvent $event, string $targetPhase, ?string $notes, User $user): DisasterEvent
    {
        $normalizedTarget = DisasterEvent::normalizeLifecyclePhase($targetPhase);
        $current = DisasterEvent::normalizeLifecyclePhase($event->lifecycle_phase);

        if ($current === $normalizedTarget) {
            throw ValidationException::withMessages([
                'lifecycle_phase' => "Disaster is already in {$normalizedTarget} phase.",
            ]);
        }

        return DB::transaction(function () use ($event, $current, $normalizedTarget, $notes, $user) {
            $updates = [
                'lifecycle_phase' => $normalizedTarget,
                'updated_by' => $user->id,
            ];

            if (in_array($normalizedTarget, [DisasterEvent::PHASE_VERIFIED, DisasterEvent::PHASE_ACTIVE], true) && !$event->verified_at) {
                $updates['verified_by'] = $user->id;
                $updates['verified_at'] = now();
            }

            if ($normalizedTarget === DisasterEvent::PHASE_CLOSED && !$event->end_date) {
                $updates['end_date'] = now()->toDateString();
            }

            $event->update($updates);

            DisasterTimeline::create([
                'disaster_event_id' => $event->id,
                'event_type' => 'STATUS_CHANGE',
                'from_phase' => $current,
                'to_phase' => $normalizedTarget,
                'title_en' => "Lifecycle Transition: {$current} -> {$normalizedTarget}",
                'title_bn' => "অবস্থা পরিবর্তন: {$current} → {$normalizedTarget}",
                'notes' => $notes,
                'recorded_by' => $user->id,
                'occurred_at' => now(),
            ]);

            AuditLog::log(
                'DISASTER_LIFECYCLE_CHANGED',
                DisasterEvent::class,
                $event->id,
                ['lifecycle_phase' => $current],
                ['lifecycle_phase' => $normalizedTarget, 'notes' => $notes],
                $user
            );

            $this->clearAnalyticsCache();

            return $event->fresh(['category', 'locations.administrativeUnit', 'impact', 'warnings', 'timelines.recorder', 'verifier']);
        });
    }

    /**
     * Issue an early warning and queue notification dispatch.
     */
    public function issueWarning(DisasterEvent $event, array $data, User $user): DisasterWarning
    {
        return DB::transaction(function () use ($event, $data, $user) {
            $warningCode = 'WARN-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));

            $warning = DisasterWarning::create([
                'disaster_event_id' => $event->id,
                'warning_code' => $warningCode,
                'warning_level' => strtoupper((string) $data['warning_level']),
                'title_en' => trim((string) $data['title_en']),
                'title_bn' => $data['title_bn'] ?? null,
                'message_en' => trim((string) $data['message_en']),
                'message_bn' => $data['message_bn'] ?? null,
                'issuing_authority' => trim((string) $data['issuing_authority']),
                'issued_at' => $data['issued_at'] ?? now(),
                'expires_at' => $data['expires_at'] ?? null,
                'affected_unit_ids' => $data['affected_unit_ids'] ?? [],
                'status' => 'ACTIVE',
                'notification_dispatch_status' => 'QUEUED',
                'issued_by' => $user->id,
            ]);

            DisasterTimeline::create([
                'disaster_event_id' => $event->id,
                'event_type' => 'WARNING_ISSUED',
                'title_en' => "Early Warning Issued ({$warning->warning_level}): {$warning->title_en}",
                'title_bn' => 'সতর্কবার্তা জারি: ' . ($warning->title_bn ?: $warning->title_en),
                'notes' => $warning->message_en,
                'recorded_by' => $user->id,
                'occurred_at' => $warning->issued_at,
            ]);

            AuditLog::log(
                'DISASTER_WARNING_ISSUED',
                DisasterWarning::class,
                $warning->id,
                null,
                [
                    'disaster_code' => $event->disaster_code,
                    'warning_code' => $warning->warning_code,
                    'warning_level' => $warning->warning_level,
                    'issuing_authority' => $warning->issuing_authority,
                ],
                $user
            );

            DispatchDisasterWarningJob::dispatch($warning->id);

            $this->clearAnalyticsCache();

            return $warning->fresh(['issuer']);
        });
    }

    /**
     * Append a timeline update with optional private file attachment (report, photo, document).
     */
    public function addTimelineEntry(
        DisasterEvent $event,
        array $data,
        ?UploadedFile $file,
        User $user
    ): DisasterTimeline {
        $attachmentMeta = [
            'attachment_original_name' => null,
            'attachment_path' => null,
            'attachment_mime' => null,
            'attachment_size_bytes' => null,
            'attachment_sha256' => null,
        ];

        if ($file) {
            $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
            $storedFilename = (string) Str::uuid() . '.' . $ext;
            $directory = "private/disaster-timelines/{$event->id}";
            $path = $file->storeAs($directory, $storedFilename, 'local');

            $attachmentMeta = [
                'attachment_original_name' => $file->getClientOriginalName(),
                'attachment_path' => $path,
                'attachment_mime' => $file->getMimeType() ?: $file->getClientMimeType(),
                'attachment_size_bytes' => $file->getSize(),
                'attachment_sha256' => hash_file('sha256', $file->getRealPath()),
            ];
        }

        return DB::transaction(function () use ($event, $data, $attachmentMeta, $user) {
            $entry = DisasterTimeline::create(array_merge([
                'disaster_event_id' => $event->id,
                'event_type' => $attachmentMeta['attachment_path'] ? 'ATTACHMENT_ADDED' : strtoupper((string) ($data['event_type'] ?? 'SITUATION_UPDATE')),
                'title_en' => trim((string) $data['title_en']),
                'title_bn' => $data['title_bn'] ?? null,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $user->id,
                'occurred_at' => $data['occurred_at'] ?? now(),
            ], $attachmentMeta));

            AuditLog::log(
                'DISASTER_TIMELINE_RECORDED',
                DisasterTimeline::class,
                $entry->id,
                null,
                [
                    'disaster_code' => $event->disaster_code,
                    'event_type' => $entry->event_type,
                    'has_attachment' => (bool) $entry->attachment_path,
                ],
                $user
            );

            return $entry->fresh(['recorder']);
        });
    }

    /**
     * Compute dashboard analytics scoped to the requesting officer's jurisdiction.
     */
    public function getDashboardAnalytics(User $user): array
    {
        $scopeKey = $user->isNationalScope()
            ? 'national'
            : md5((string) ($user->getAdministrativeScope()['ancestry_path'] ?? '/'));

        return Cache::remember("disaster:dashboard:{$scopeKey}", 60, function () use ($user) {
            $events = DisasterEvent::with(['impact', 'locations.administrativeUnit', 'warnings'])
                ->inJurisdiction($user)
                ->get();

            $activePhases = [
                DisasterEvent::PHASE_REPORTED,
                DisasterEvent::PHASE_UNDER_ASSESSMENT,
                DisasterEvent::PHASE_VERIFIED,
                DisasterEvent::PHASE_ACTIVE,
                DisasterEvent::PHASE_RESPONSE_ONGOING,
            ];

            $activeEvents = $events->filter(fn ($e) => in_array($e->lifecycle_phase, $activePhases, true));

            $byCategory = $events->groupBy('disaster_type')->map(fn ($group, $type) => [
                'category' => $type,
                'count' => $group->count(),
                'active_count' => $group->filter(fn ($e) => in_array($e->lifecycle_phase, $activePhases, true))->count(),
                'affected_population' => (int) $group->sum(fn ($e) => $e->impact?->affected_population ?? 0),
            ])->values();

            $bySeverity = $events->groupBy('severity_level')->map(fn ($group, $sev) => [
                'severity_level' => $sev,
                'count' => $group->count(),
            ])->values();

            $byPhase = $events->groupBy('lifecycle_phase')->map(fn ($group, $phase) => [
                'lifecycle_phase' => $phase,
                'count' => $group->count(),
            ])->values();

            $geoDistribution = [];
            foreach ($events as $ev) {
                foreach ($ev->locations as $loc) {
                    $unit = $loc->administrativeUnit;
                    if (!$unit) {
                        continue;
                    }
                    if (!isset($geoDistribution[$unit->id])) {
                        $geoDistribution[$unit->id] = [
                            'administrative_unit_id' => $unit->id,
                            'name_en' => $unit->name_en,
                            'name_bn' => $unit->name_bn,
                            'tier_level' => $unit->tier_level,
                            'disasters_count' => 0,
                        ];
                    }
                    $geoDistribution[$unit->id]['disasters_count']++;
                }
            }

            $monthlyTrends = $events->groupBy(fn ($e) => $e->declaration_date?->format('Y-m') ?? 'Unknown')
                ->sortKeys()
                ->map(fn ($group, $month) => [
                    'period' => $month,
                    'events_count' => $group->count(),
                    'affected_population' => (int) $group->sum(fn ($e) => $e->impact?->affected_population ?? 0),
                ])->values();

            $activeWarningsCount = $events->sum(function ($e) {
                return $e->warnings->filter(function ($w) {
                    return $w->status === 'ACTIVE' && ($w->expires_at === null || $w->expires_at->isFuture());
                })->count();
            });

            return [
                'summary' => [
                    'total_disasters' => $events->count(),
                    'active_disasters' => $activeEvents->count(),
                    'emergency_declared_count' => $events->where('is_emergency_declared', true)->count(),
                    'active_warnings_count' => $activeWarningsCount,
                    'total_affected_population' => (int) $events->sum(fn ($e) => $e->impact?->affected_population ?? 0),
                    'total_affected_households' => (int) $events->sum(fn ($e) => $e->impact?->affected_households ?? 0),
                    'total_deaths' => (int) $events->sum(fn ($e) => $e->impact?->deaths_count ?? 0),
                    'total_injured' => (int) $events->sum(fn ($e) => $e->impact?->injured_count ?? 0),
                    'total_damaged_houses' => (int) $events->sum(fn ($e) => ($e->impact?->damaged_houses_fully ?? 0) + ($e->impact?->damaged_houses_partially ?? 0)),
                    'total_economic_loss_bdt' => round((float) $events->sum(fn ($e) => $e->impact?->total_economic_loss_bdt ?? 0), 2),
                ],
                'by_category' => $byCategory,
                'by_severity' => $bySeverity,
                'by_lifecycle' => $byPhase,
                'geo_distribution' => array_values($geoDistribution),
                'trends' => $monthlyTrends,
            ];
        });
    }

    /**
     * Validate locations belong to existing units and lie within officer scope.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    protected function resolveAndValidateLocations(array $locations, bool $isNational, User $user): array
    {
        $resolved = [];

        if (empty($locations) && !$isNational && $user->administrative_unit_id) {
            $userUnit = AdministrativeUnit::find($user->administrative_unit_id);
            if ($userUnit) {
                $locations = [['administrative_unit_id' => $userUnit->id]];
            }
        }

        foreach ($locations as $loc) {
            $unitId = is_array($loc) ? ($loc['administrative_unit_id'] ?? null) : $loc;
            if (!$unitId) {
                continue;
            }

            $unit = AdministrativeUnit::find($unitId);
            if (!$unit) {
                throw ValidationException::withMessages([
                    'locations' => "Administrative unit {$unitId} does not exist.",
                ]);
            }

            if (!$user->isNationalScope() && !$this->scopeService->canAccessScope($user, $unit->ancestry_path)) {
                throw new AuthorizationException("Administrative unit {$unit->name_en} is outside your jurisdiction.");
            }

            $resolved[$unit->id] = [
                'administrative_unit_id' => $unit->id,
                'latitude' => is_array($loc) ? ($loc['latitude'] ?? $unit->latitude) : $unit->latitude,
                'longitude' => is_array($loc) ? ($loc['longitude'] ?? $unit->longitude) : $unit->longitude,
                'boundary_geojson' => is_array($loc) ? ($loc['boundary_geojson'] ?? null) : null,
                'severity_override' => is_array($loc) ? ($loc['severity_override'] ?? null) : null,
                'notes' => is_array($loc) ? ($loc['notes'] ?? null) : null,
            ];
        }

        return array_values($resolved);
    }

    protected function syncLocations(DisasterEvent $event, array $locationsInput): void
    {
        DisasterLocation::where('disaster_event_id', $event->id)->delete();

        foreach ($locationsInput as $locData) {
            $loc = DisasterLocation::create(array_merge($locData, [
                'disaster_event_id' => $event->id,
            ]));
            $this->spatialService->syncLocationGeometry($loc);
        }
    }

    protected function upsertImpact(DisasterEvent $event, array $impactInput, User $user): DisasterImpact
    {
        $payload = array_merge($this->extractImpactPayload($impactInput), [
            'updated_by' => $user->id,
        ]);

        return DisasterImpact::updateOrCreate(
            ['disaster_event_id' => $event->id],
            $payload
        );
    }

    protected function extractImpactPayload(array $impact): array
    {
        return [
            'affected_population' => (int) ($impact['affected_population'] ?? 0),
            'affected_households' => (int) ($impact['affected_households'] ?? 0),
            'deaths_count' => (int) ($impact['deaths_count'] ?? 0),
            'injured_count' => (int) ($impact['injured_count'] ?? 0),
            'missing_count' => (int) ($impact['missing_count'] ?? 0),
            'damaged_houses_fully' => (int) ($impact['damaged_houses_fully'] ?? 0),
            'damaged_houses_partially' => (int) ($impact['damaged_houses_partially'] ?? 0),
            'damaged_infrastructure_count' => (int) ($impact['damaged_infrastructure_count'] ?? 0),
            'agricultural_loss_acres' => (float) ($impact['agricultural_loss_acres'] ?? 0),
            'agricultural_loss_bdt' => (float) ($impact['agricultural_loss_bdt'] ?? 0),
            'livestock_loss_count' => (int) ($impact['livestock_loss_count'] ?? 0),
            'livestock_loss_bdt' => (float) ($impact['livestock_loss_bdt'] ?? 0),
            'total_economic_loss_bdt' => (float) ($impact['total_economic_loss_bdt'] ?? 0),
            'remarks' => $impact['remarks'] ?? null,
        ];
    }

    protected function generateDisasterCode(string $disasterType): string
    {
        $prefix = strtoupper(substr(preg_replace('/[^A-Z]/', '', $disasterType), 0, 4) ?: 'DIS');

        return $prefix . '-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4));
    }

    public function clearAnalyticsCache(): void
    {
        Cache::forget('disaster:dashboard:national');
    }

    public function getAttachmentStreamResponse(DisasterTimeline $timeline)
    {
        if (!$timeline->attachment_path || !Storage::disk('local')->exists($timeline->attachment_path)) {
            abort(404, 'Attachment file not found.');
        }

        return Storage::disk('local')->download(
            $timeline->attachment_path,
            $timeline->attachment_original_name ?: 'attachment'
        );
    }

    /**
     * Upload/save a GeoJSON or Shapefile boundary onto a disaster event and record timeline + audit history.
     *
     * @throws ValidationException
     */
    public function uploadBoundary(
        DisasterEvent $event,
        array $geojson,
        ?float $lat,
        ?float $lng,
        ?string $sourceFilename,
        ?UploadedFile $shapefileArchive,
        ?string $notes,
        User $user
    ): DisasterEvent {
        $normalized = $this->spatialService->normalizeToPolygonGeometry($geojson);
        if (!$normalized) {
            throw ValidationException::withMessages([
                'affected_zone_geojson' => 'Uploaded boundary does not contain valid Polygon or MultiPolygon geometries.',
            ]);
        }

        $attachmentMeta = [
            'attachment_original_name' => null,
            'attachment_path' => null,
            'attachment_mime' => null,
            'attachment_size_bytes' => null,
            'attachment_sha256' => null,
        ];

        if ($shapefileArchive) {
            $ext = strtolower($shapefileArchive->getClientOriginalExtension() ?: 'zip');
            $storedFilename = (string) Str::uuid() . '.' . $ext;
            $path = $shapefileArchive->storeAs("private/disaster-timelines/{$event->id}", $storedFilename, 'local');

            $attachmentMeta = [
                'attachment_original_name' => $shapefileArchive->getClientOriginalName(),
                'attachment_path' => $path,
                'attachment_mime' => $shapefileArchive->getMimeType() ?: $shapefileArchive->getClientMimeType(),
                'attachment_size_bytes' => $shapefileArchive->getSize(),
                'attachment_sha256' => hash_file('sha256', $shapefileArchive->getRealPath()),
            ];
        }

        return DB::transaction(function () use ($event, $normalized, $lat, $lng, $sourceFilename, $notes, $attachmentMeta, $user) {
            $updates = [
                'affected_zone_geojson' => $normalized,
                'updated_by' => $user->id,
            ];
            if ($lat !== null && $lng !== null) {
                $updates['epicenter_lat'] = $lat;
                $updates['epicenter_lng'] = $lng;
            }

            $event->update($updates);
            $this->spatialService->syncEventGeometry($event);

            $label = $sourceFilename ?: ($attachmentMeta['attachment_original_name'] ?? 'GeoJSON/Shapefile');

            DisasterTimeline::create(array_merge([
                'disaster_event_id' => $event->id,
                'event_type' => 'ATTACHMENT_ADDED',
                'title_en' => "GIS Boundary / Shapefile Updated ({$label})",
                'title_bn' => "জিআইএস সীমানা / শেপফাইল হালনাগাদ ({$label})",
                'notes' => $notes ?: 'Affected area boundary geometry updated from GIS file upload.',
                'recorded_by' => $user->id,
                'occurred_at' => now(),
            ], $attachmentMeta));

            AuditLog::log(
                'DISASTER_BOUNDARY_UPDATED',
                DisasterEvent::class,
                $event->id,
                null,
                [
                    'disaster_code' => $event->disaster_code,
                    'geometry_type' => $normalized['type'],
                    'source_filename' => $label,
                ],
                $user
            );

            $this->clearAnalyticsCache();

            return $event->fresh(['category', 'locations.administrativeUnit', 'impact', 'warnings', 'timelines.recorder']);
        });
    }
}
