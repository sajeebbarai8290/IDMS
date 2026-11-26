<?php

namespace App\Modules\DamageAssessment\Services;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\AuditLog;
use App\Core\Models\User;
use App\Core\Services\AdministrativeScopeService;
use App\Modules\DamageAssessment\Models\DamageAssessment;
use App\Modules\DamageAssessment\Models\DamageAttachment;
use App\Modules\DamageAssessment\Models\DamageCategory;
use App\Modules\DamageAssessment\Models\DamageLocation;
use App\Modules\DamageAssessment\Models\DamageSectorItem;
use App\Modules\DamageAssessment\Models\DamageVerificationLog;
use App\Modules\DamageAssessment\Policies\DamageAssessmentPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DamageAssessmentService
{
    public function __construct(
        protected AdministrativeScopeService $scopeService,
        protected DamageSpatialService $spatialService,
        protected DamageAssessmentPolicy $policy
    ) {}

    /**
     * Calculate aggregated damaged counts and total estimated financial loss from sector items.
     */
    public function calculateSectorTotals(iterable $sectorItems): array
    {
        $fully = 0;
        $partially = 0;
        $minor = 0;
        $loss = 0.0;

        foreach ($sectorItems as $item) {
            $fully += max(0, (int) ($item['fully_damaged_count'] ?? $item->fully_damaged_count ?? 0));
            $partially += max(0, (int) ($item['partially_damaged_count'] ?? $item->partially_damaged_count ?? 0));
            $minor += max(0, (int) ($item['minor_damaged_count'] ?? $item->minor_damaged_count ?? 0));
            $loss += max(0.0, (float) ($item['estimated_loss_bdt'] ?? $item->estimated_loss_bdt ?? 0));
        }

        return [
            'total_fully_damaged' => $fully,
            'total_partially_damaged' => $partially,
            'total_minor_damaged' => $minor,
            'total_estimated_loss_bdt' => round($loss, 2),
        ];
    }

    /**
     * Derive severity classification from damage scale when not explicitly overridden.
     */
    public function deriveSeverityLevel(int $fullyDamaged, int $affectedHouseholds, float $totalLossBdt): string
    {
        if ($fullyDamaged >= 500 || $affectedHouseholds >= 2000 || $totalLossBdt >= 50000000) {
            return DamageAssessment::SEVERITY_CRITICAL;
        }
        if ($fullyDamaged >= 100 || $affectedHouseholds >= 500 || $totalLossBdt >= 10000000) {
            return DamageAssessment::SEVERITY_SEVERE;
        }
        if ($fullyDamaged >= 20 || $affectedHouseholds >= 100 || $totalLossBdt >= 1000000) {
            return DamageAssessment::SEVERITY_MODERATE;
        }

        return DamageAssessment::SEVERITY_LOW;
    }

    public function createAssessment(array $data, User $actor, bool $isOfflineSync = false): DamageAssessment
    {
        $this->assertUnitInUserScope($actor, $data['administrative_unit_id'], 'assessment jurisdiction');

        $locations = $data['locations'] ?? [];
        foreach ($locations as $loc) {
            if (!empty($loc['administrative_unit_id'])) {
                $this->assertUnitInUserScope($actor, $loc['administrative_unit_id'], 'coverage location');
            }
        }

        // Idempotency protection for network retries & offline mobile sync (scoped to actor)
        if (!empty($data['idempotency_key'])) {
            $existing = DamageAssessment::where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                if (!$this->policy->viewAssessment($actor, $existing)->allowed()) {
                    throw new AuthorizationException('Idempotency key conflicts with an assessment outside your jurisdiction.');
                }
                return $existing->load($this->defaultRelations());
            }
        }

        if (!empty($data['client_uuid'])) {
            $existingClient = DamageAssessment::where('client_uuid', $data['client_uuid'])->first();
            if ($existingClient) {
                if (!$this->policy->viewAssessment($actor, $existingClient)->allowed()) {
                    throw new AuthorizationException('Client UUID conflicts with an assessment outside your jurisdiction.');
                }
                return $existingClient->load($this->defaultRelations());
            }
        }

        return DB::transaction(function () use ($data, $locations, $actor, $isOfflineSync) {
            $sectorItems = $data['sector_items'] ?? [];
            $totals = $this->calculateSectorTotals($sectorItems);

            $totalLoss = !empty($sectorItems)
                ? $totals['total_estimated_loss_bdt']
                : round((float) ($data['total_estimated_loss_bdt'] ?? 0), 2);

            $affectedHouseholds = (int) ($data['affected_households'] ?? 0);
            $severity = !empty($data['severity_level'])
                ? strtoupper((string) $data['severity_level'])
                : $this->deriveSeverityLevel($totals['total_fully_damaged'], $affectedHouseholds, $totalLoss);

            $normalizedBoundary = !empty($data['boundary_geojson'])
                ? $this->spatialService->normalizeToPolygonGeometry($data['boundary_geojson'])
                : null;

            $dFormCode = !empty($data['d_form_code'])
                ? strtoupper(trim((string) $data['d_form_code']))
                : $this->generateDFormCode();

            $initialStatus = DamageAssessment::STATUS_DRAFT;
            $submitNow = !empty($data['submit_immediately']) && $this->policy->submitAssessment($actor, new DamageAssessment([
                'administrative_unit_id' => $data['administrative_unit_id'],
            ]))->allowed();

            $assessment = DamageAssessment::create([
                'disaster_event_id' => $data['disaster_event_id'],
                'administrative_unit_id' => $data['administrative_unit_id'],
                'd_form_code' => $dFormCode,
                'reference_no' => $data['reference_no'] ?? null,
                'assessment_type' => strtoupper((string) ($data['assessment_type'] ?? DamageAssessment::TYPE_PRELIMINARY_D_FORM)),
                'assessment_date' => $data['assessment_date'],
                'assessed_at' => $data['assessed_at'] ?? now(),
                'assessing_organization' => $data['assessing_organization'],
                'team_name' => $data['team_name'] ?? null,
                'team_lead_name' => $data['team_lead_name'] ?? null,
                'team_members' => $data['team_members'] ?? null,
                'assessor_user_id' => $data['assessor_user_id'] ?? $actor->id,
                'assessor_name' => $data['assessor_name'] ?? $actor->full_name_en,
                'assessor_designation' => $data['assessor_designation'] ?? $actor->designation,
                'assessor_contact' => $data['assessor_contact'] ?? $actor->mobile_number,
                'ward_no' => $data['ward_no'] ?? null,
                'location_summary' => $data['location_summary'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'boundary_geojson' => $normalizedBoundary,
                'severity_level' => $severity,
                'affected_households' => $affectedHouseholds,
                'affected_population' => (int) ($data['affected_population'] ?? 0),
                'displaced_population' => (int) ($data['displaced_population'] ?? 0),
                'deaths_count' => (int) ($data['deaths_count'] ?? 0),
                'injured_count' => (int) ($data['injured_count'] ?? 0),
                'missing_count' => (int) ($data['missing_count'] ?? 0),
                'total_fully_damaged' => $totals['total_fully_damaged'],
                'total_partially_damaged' => $totals['total_partially_damaged'],
                'total_minor_damaged' => $totals['total_minor_damaged'],
                'total_estimated_loss_bdt' => $totalLoss,
                'field_observations' => $data['field_observations'] ?? null,
                'verification_status' => $submitNow ? DamageAssessment::STATUS_SUBMITTED : $initialStatus,
                'submitted_by' => $submitNow ? $actor->id : null,
                'submitted_at' => $submitNow ? now() : null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
                'client_uuid' => $data['client_uuid'] ?? null,
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'device_id' => $data['device_id'] ?? null,
                'offline_created_at' => $data['offline_created_at'] ?? null,
                'synced_at' => $isOfflineSync ? now() : null,
                'sync_status' => $isOfflineSync ? DamageAssessment::SYNC_SYNCED : DamageAssessment::SYNC_ONLINE,
                'sync_version' => 1,
            ]);

            $this->syncLocations($assessment, $locations);
            $this->syncSectorItems($assessment, $sectorItems);
            $this->spatialService->syncAssessmentGeometry($assessment);

            $this->recordVerificationLog(
                $assessment,
                $submitNow ? 'CREATED_AND_SUBMITTED' : ($isOfflineSync ? 'OFFLINE_SYNC_CREATED' : 'CREATED'),
                null,
                $assessment->verification_status,
                $actor,
                $data['field_observations'] ?? 'Damage assessment registered.'
            );

            AuditLog::log(
                'DAMAGE_ASSESSMENT_CREATED',
                DamageAssessment::class,
                $assessment->id,
                null,
                [
                    'd_form_code' => $assessment->d_form_code,
                    'verification_status' => $assessment->verification_status,
                    'total_estimated_loss_bdt' => (float) $assessment->total_estimated_loss_bdt,
                    'sync_status' => $assessment->sync_status,
                ],
                $actor,
                $assessment->administrative_unit_id
            );

            $this->clearDashboardCache();

            return $assessment->fresh($this->defaultRelations());
        });
    }

    public function updateAssessment(DamageAssessment $assessment, array $data, User $actor): DamageAssessment
    {
        if (!$assessment->isEditable()) {
            throw ValidationException::withMessages([
                'verification_status' => [
                    "Assessment cannot be edited while in {$assessment->verification_status} state. Only DRAFT or CORRECTION_REQUESTED assessments can be modified.",
                ],
            ]);
        }

        if (isset($data['sync_version']) && (int) $data['sync_version'] !== (int) $assessment->sync_version) {
            throw ValidationException::withMessages([
                'sync_version' => [
                    "Sync conflict detected: server version is {$assessment->sync_version}, client sent {$data['sync_version']}.",
                ],
            ]);
        }

        if (!empty($data['administrative_unit_id'])) {
            $this->assertUnitInUserScope($actor, $data['administrative_unit_id'], 'assessment jurisdiction');
        }

        if (array_key_exists('locations', $data) && is_array($data['locations'])) {
            foreach ($data['locations'] as $loc) {
                if (!empty($loc['administrative_unit_id'])) {
                    $this->assertUnitInUserScope($actor, $loc['administrative_unit_id'], 'coverage location');
                }
            }
        }

        return DB::transaction(function () use ($assessment, $data, $actor) {
            $oldSnapshot = [
                'verification_status' => $assessment->verification_status,
                'total_estimated_loss_bdt' => (float) $assessment->total_estimated_loss_bdt,
                'affected_households' => (int) $assessment->affected_households,
            ];

            $payload = collect($data)->only([
                'disaster_event_id',
                'administrative_unit_id',
                'reference_no',
                'assessment_type',
                'assessment_date',
                'assessed_at',
                'assessing_organization',
                'team_name',
                'team_lead_name',
                'team_members',
                'assessor_user_id',
                'assessor_name',
                'assessor_designation',
                'assessor_contact',
                'ward_no',
                'location_summary',
                'latitude',
                'longitude',
                'severity_level',
                'affected_households',
                'affected_population',
                'displaced_population',
                'deaths_count',
                'injured_count',
                'missing_count',
                'field_observations',
            ])->toArray();

            if (array_key_exists('assessment_type', $payload)) {
                $payload['assessment_type'] = strtoupper((string) $payload['assessment_type']);
            }
            if (array_key_exists('severity_level', $payload)) {
                $payload['severity_level'] = strtoupper((string) $payload['severity_level']);
            }
            if (array_key_exists('boundary_geojson', $data)) {
                $payload['boundary_geojson'] = $data['boundary_geojson']
                    ? $this->spatialService->normalizeToPolygonGeometry($data['boundary_geojson'])
                    : null;
            }

            if (array_key_exists('sector_items', $data) && is_array($data['sector_items'])) {
                $this->syncSectorItems($assessment, $data['sector_items']);
                $totals = $this->calculateSectorTotals($data['sector_items']);
                $payload['total_fully_damaged'] = $totals['total_fully_damaged'];
                $payload['total_partially_damaged'] = $totals['total_partially_damaged'];
                $payload['total_minor_damaged'] = $totals['total_minor_damaged'];
                $payload['total_estimated_loss_bdt'] = $totals['total_estimated_loss_bdt'];
            } elseif (array_key_exists('total_estimated_loss_bdt', $data)) {
                $payload['total_estimated_loss_bdt'] = round((float) $data['total_estimated_loss_bdt'], 2);
            }

            $payload['updated_by'] = $actor->id;
            $payload['sync_version'] = (int) $assessment->sync_version + 1;

            $assessment->update($payload);

            if (array_key_exists('locations', $data) && is_array($data['locations'])) {
                $this->syncLocations($assessment, $data['locations']);
            }

            $this->spatialService->syncAssessmentGeometry($assessment->fresh());

            $this->recordVerificationLog(
                $assessment,
                'UPDATED',
                $assessment->verification_status,
                $assessment->verification_status,
                $actor,
                $data['update_notes'] ?? 'Assessment details updated.'
            );

            AuditLog::log(
                'DAMAGE_ASSESSMENT_UPDATED',
                DamageAssessment::class,
                $assessment->id,
                $oldSnapshot,
                [
                    'total_estimated_loss_bdt' => (float) $assessment->total_estimated_loss_bdt,
                    'affected_households' => (int) $assessment->affected_households,
                    'sync_version' => $assessment->sync_version,
                ],
                $actor,
                $assessment->administrative_unit_id
            );

            $this->clearDashboardCache();

            return $assessment->fresh($this->defaultRelations());
        });
    }

    public function deleteAssessment(DamageAssessment $assessment, User $actor): void
    {
        if ($assessment->verification_status !== DamageAssessment::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'verification_status' => ['Only DRAFT damage assessments can be deleted.'],
            ]);
        }

        DB::transaction(function () use ($assessment, $actor) {
            AuditLog::log(
                'DAMAGE_ASSESSMENT_DELETED',
                DamageAssessment::class,
                $assessment->id,
                ['d_form_code' => $assessment->d_form_code, 'verification_status' => $assessment->verification_status],
                null,
                $actor,
                $assessment->administrative_unit_id
            );

            $assessment->delete();
            $this->clearDashboardCache();
        });
    }

    /**
     * Batch offline synchronization endpoint for future Flutter mobile field app.
     */
    public function syncOfflineAssessments(array $records, User $actor): array
    {
        $results = [];

        foreach ($records as $index => $record) {
            $clientUuid = $record['client_uuid'] ?? null;
            $idempotencyKey = $record['idempotency_key'] ?? null;

            $existingByKey = $idempotencyKey
                ? DamageAssessment::where('idempotency_key', $idempotencyKey)->first()
                : null;
            if ($existingByKey) {
                if (!$this->policy->viewAssessment($actor, $existingByKey)->allowed()) {
                    throw new AuthorizationException('Offline sync idempotency key belongs to another jurisdiction.');
                }
                $results[] = [
                    'index' => $index,
                    'client_uuid' => $clientUuid,
                    'idempotency_key' => $idempotencyKey,
                    'outcome' => 'DUPLICATE_SKIPPED',
                    'assessment_id' => $existingByKey->id,
                    'd_form_code' => $existingByKey->d_form_code,
                    'sync_version' => (int) $existingByKey->sync_version,
                ];
                continue;
            }

            $existingByClient = $clientUuid
                ? DamageAssessment::where('client_uuid', $clientUuid)->first()
                : null;

            if ($existingByClient) {
                $authCheck = $this->policy->updateAssessment($actor, $existingByClient);
                if (!$authCheck->allowed()) {
                    throw new AuthorizationException($authCheck->message() ?: 'Unauthorized offline sync update.');
                }
                $clientVersion = (int) ($record['sync_version'] ?? 1);
                if (!$existingByClient->isEditable() || $clientVersion < (int) $existingByClient->sync_version) {
                    $existingByClient->update([
                        'sync_status' => DamageAssessment::SYNC_CONFLICT,
                        'synced_at' => now(),
                    ]);

                    $results[] = [
                        'index' => $index,
                        'client_uuid' => $clientUuid,
                        'outcome' => 'CONFLICT',
                        'assessment_id' => $existingByClient->id,
                        'd_form_code' => $existingByClient->d_form_code,
                        'server_status' => $existingByClient->verification_status,
                        'server_sync_version' => (int) $existingByClient->sync_version,
                    ];
                    continue;
                }

                $updated = $this->updateAssessment($existingByClient, $record, $actor);
                $updated->update([
                    'sync_status' => DamageAssessment::SYNC_SYNCED,
                    'synced_at' => now(),
                    'device_id' => $record['device_id'] ?? $updated->device_id,
                ]);

                $results[] = [
                    'index' => $index,
                    'client_uuid' => $clientUuid,
                    'outcome' => 'UPDATED',
                    'assessment_id' => $updated->id,
                    'd_form_code' => $updated->d_form_code,
                    'sync_version' => (int) $updated->sync_version,
                ];
                continue;
            }

            $created = $this->createAssessment($record, $actor, true);
            $results[] = [
                'index' => $index,
                'client_uuid' => $clientUuid,
                'idempotency_key' => $idempotencyKey,
                'outcome' => 'CREATED',
                'assessment_id' => $created->id,
                'd_form_code' => $created->d_form_code,
                'sync_version' => (int) $created->sync_version,
            ];
        }

        return [
            'synced_at' => now()->toIso8601String(),
            'total_received' => count($records),
            'results' => $results,
        ];
    }

    public function submitAssessment(DamageAssessment $assessment, User $actor, ?string $notes = null): DamageAssessment
    {
        if (!in_array($assessment->verification_status, [
            DamageAssessment::STATUS_DRAFT,
            DamageAssessment::STATUS_CORRECTION_REQUESTED,
        ], true)) {
            throw ValidationException::withMessages([
                'verification_status' => ["Only DRAFT or CORRECTION_REQUESTED assessments can be submitted (current: {$assessment->verification_status})."],
            ]);
        }

        if ($assessment->sectorItems()->count() === 0) {
            throw ValidationException::withMessages([
                'sector_items' => ['At least one sectoral damage category entry is required before submitting the D-Form.'],
            ]);
        }

        return $this->transitionStatus(
            $assessment,
            DamageAssessment::STATUS_SUBMITTED,
            'SUBMITTED',
            $actor,
            $notes ?: 'Field officer submitted assessment for verification.',
            [
                'submitted_by' => $actor->id,
                'submitted_at' => now(),
                'correction_notes' => null,
            ]
        );
    }

    public function startVerification(DamageAssessment $assessment, User $actor, ?string $notes = null): DamageAssessment
    {
        if ($assessment->verification_status !== DamageAssessment::STATUS_SUBMITTED) {
            throw ValidationException::withMessages([
                'verification_status' => ["Only SUBMITTED assessments can be placed under verification (current: {$assessment->verification_status})."],
            ]);
        }

        return $this->transitionStatus(
            $assessment,
            DamageAssessment::STATUS_UNDER_VERIFICATION,
            'VERIFICATION_STARTED',
            $actor,
            $notes ?: 'Supervisor initiated technical verification.'
        );
    }

    public function requestCorrection(DamageAssessment $assessment, User $actor, string $notes): DamageAssessment
    {
        if (!in_array($assessment->verification_status, [
            DamageAssessment::STATUS_SUBMITTED,
            DamageAssessment::STATUS_UNDER_VERIFICATION,
        ], true)) {
            throw ValidationException::withMessages([
                'verification_status' => ["Corrections can only be requested for SUBMITTED or UNDER_VERIFICATION assessments (current: {$assessment->verification_status})."],
            ]);
        }

        return $this->transitionStatus(
            $assessment,
            DamageAssessment::STATUS_CORRECTION_REQUESTED,
            'CORRECTION_REQUESTED',
            $actor,
            $notes,
            ['correction_notes' => $notes]
        );
    }

    public function verifyAssessment(DamageAssessment $assessment, User $actor, ?string $notes = null): DamageAssessment
    {
        if (!in_array($assessment->verification_status, [
            DamageAssessment::STATUS_SUBMITTED,
            DamageAssessment::STATUS_UNDER_VERIFICATION,
        ], true)) {
            throw ValidationException::withMessages([
                'verification_status' => ["Only SUBMITTED or UNDER_VERIFICATION assessments can be verified (current: {$assessment->verification_status})."],
            ]);
        }

        return $this->transitionStatus(
            $assessment,
            DamageAssessment::STATUS_VERIFIED,
            'VERIFIED',
            $actor,
            $notes ?: 'Assessment verified and made available for reporting.',
            [
                'verified_by' => $actor->id,
                'verified_at' => now(),
            ]
        );
    }

    public function rejectAssessment(DamageAssessment $assessment, User $actor, string $reason): DamageAssessment
    {
        if (!in_array($assessment->verification_status, [
            DamageAssessment::STATUS_SUBMITTED,
            DamageAssessment::STATUS_UNDER_VERIFICATION,
            DamageAssessment::STATUS_VERIFIED,
        ], true)) {
            throw ValidationException::withMessages([
                'verification_status' => ["Assessment cannot be rejected from {$assessment->verification_status} state."],
            ]);
        }

        return $this->transitionStatus(
            $assessment,
            DamageAssessment::STATUS_REJECTED,
            'REJECTED',
            $actor,
            $reason,
            [
                'rejected_by' => $actor->id,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]
        );
    }

    public function approveAssessment(DamageAssessment $assessment, User $actor, ?string $notes = null): DamageAssessment
    {
        if ($assessment->verification_status !== DamageAssessment::STATUS_VERIFIED) {
            throw ValidationException::withMessages([
                'verification_status' => ["Only VERIFIED assessments can receive final approval (current: {$assessment->verification_status})."],
            ]);
        }

        return $this->transitionStatus(
            $assessment,
            DamageAssessment::STATUS_APPROVED,
            'APPROVED',
            $actor,
            $notes ?: 'Assessment formally approved for national consolidation and rehabilitation planning.',
            [
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]
        );
    }

    public function updateBoundary(DamageAssessment $assessment, array $geojson, User $actor): DamageAssessment
    {
        if (!$assessment->isEditable()) {
            throw ValidationException::withMessages([
                'verification_status' => ["Boundary cannot be modified while assessment is in {$assessment->verification_status} state."],
            ]);
        }

        $normalized = $this->spatialService->normalizeToPolygonGeometry($geojson);
        if (!$normalized) {
            throw ValidationException::withMessages([
                'boundary_geojson' => ['Invalid GeoJSON Polygon or MultiPolygon payload.'],
            ]);
        }

        return DB::transaction(function () use ($assessment, $normalized, $actor) {
            $assessment->update([
                'boundary_geojson' => $normalized,
                'updated_by' => $actor->id,
            ]);

            $this->spatialService->syncAssessmentGeometry($assessment->fresh());

            AuditLog::log(
                'DAMAGE_BOUNDARY_UPDATED',
                DamageAssessment::class,
                $assessment->id,
                null,
                ['d_form_code' => $assessment->d_form_code],
                $actor,
                $assessment->administrative_unit_id
            );

            $this->clearDashboardCache();

            return $assessment->fresh($this->defaultRelations());
        });
    }

    public function uploadAttachment(
        DamageAssessment $assessment,
        UploadedFile $file,
        array $meta,
        User $actor
    ): DamageAttachment {
        if (!$assessment->isEditable()) {
            throw ValidationException::withMessages([
                'verification_status' => ["Attachments can only be uploaded while assessment is in DRAFT or CORRECTION_REQUESTED state (current: {$assessment->verification_status})."],
            ]);
        }

        if (!empty($meta['damage_sector_item_id'])) {
            $belongsToAssessment = $assessment->sectorItems()
                ->whereKey($meta['damage_sector_item_id'])
                ->exists();
            if (!$belongsToAssessment) {
                throw ValidationException::withMessages([
                    'damage_sector_item_id' => ['Referenced sector item does not belong to this damage assessment.'],
                ]);
            }
        }

        return DB::transaction(function () use ($assessment, $file, $meta, $actor) {
            $ext = strtolower($file->getClientOriginalExtension() ?: 'bin');
            $filename = (string) Str::uuid() . '.' . $ext;
            $dir = "damage/attachments/{$assessment->id}";
            $path = $file->storeAs($dir, $filename, 'local');
            $sha256 = hash_file('sha256', $file->getRealPath());

            $mime = (string) ($file->getMimeType() ?: $file->getClientMimeType());
            $kind = strtoupper((string) ($meta['attachment_kind'] ?? ''));
            if (!in_array($kind, DamageAttachment::KINDS, true)) {
                $kind = str_starts_with($mime, 'image/')
                    ? DamageAttachment::KIND_PHOTOGRAPH
                    : DamageAttachment::KIND_DOCUMENT;
            }

            $attachment = DamageAttachment::create([
                'damage_assessment_id' => $assessment->id,
                'damage_sector_item_id' => $meta['damage_sector_item_id'] ?? null,
                'attachment_kind' => $kind,
                'title' => $meta['title'] ?? $file->getClientOriginalName(),
                'caption' => $meta['caption'] ?? null,
                'original_name' => $file->getClientOriginalName(),
                'storage_path' => $path,
                'mime_type' => $mime,
                'file_size_bytes' => $file->getSize(),
                'sha256_hash' => $sha256,
                'latitude' => $meta['latitude'] ?? $assessment->latitude,
                'longitude' => $meta['longitude'] ?? $assessment->longitude,
                'captured_at' => $meta['captured_at'] ?? now(),
                'uploaded_by' => $actor->id,
            ]);

            AuditLog::log(
                'DAMAGE_ATTACHMENT_UPLOADED',
                DamageAttachment::class,
                $attachment->id,
                null,
                [
                    'damage_assessment_id' => $assessment->id,
                    'attachment_kind' => $kind,
                    'sha256_hash' => $sha256,
                ],
                $actor,
                $assessment->administrative_unit_id
            );

            return $attachment->fresh('uploader');
        });
    }

    public function deleteAttachment(DamageAssessment $assessment, DamageAttachment $attachment, User $actor): void
    {
        if (!$assessment->isEditable()) {
            throw ValidationException::withMessages([
                'verification_status' => ['Attachments can only be removed while assessment is in DRAFT or CORRECTION_REQUESTED state.'],
            ]);
        }

        DB::transaction(function () use ($assessment, $attachment, $actor) {
            if ($attachment->storage_path && Storage::disk('local')->exists($attachment->storage_path)) {
                Storage::disk('local')->delete($attachment->storage_path);
            }

            AuditLog::log(
                'DAMAGE_ATTACHMENT_DELETED',
                DamageAttachment::class,
                $attachment->id,
                ['original_name' => $attachment->original_name],
                null,
                $actor,
                $assessment->administrative_unit_id
            );

            $attachment->delete();
        });
    }

    public function getDashboardSummary(User $user, array $filters = []): array
    {
        $scopePath = $user->isNationalScope()
            ? 'NATIONAL'
            : ($user->getAdministrativeScope()['ancestry_path'] ?? '/');
        $isExternal = $this->policy->isExternalPartnerOnly($user);

        $version = (int) Cache::get('damage:dashboard:version', 1);
        $cacheKey = 'damage:dashboard:v' . $version . ':' . md5(json_encode([
            'scope' => $scopePath,
            'ext' => $isExternal,
            'filters' => $filters,
        ]));

        return Cache::remember($cacheKey, 180, function () use ($user, $filters, $isExternal) {
            $query = DamageAssessment::inJurisdiction($user)
                ->with(['administrativeUnit', 'disasterEvent', 'sectorItems.category']);

            if ($isExternal) {
                $query->whereIn('verification_status', DamageAssessment::REPORTABLE_STATUSES);
            }

            $this->applyCommonFilters($query, $filters);
            $assessments = $query->get();

            $byStatus = [];
            foreach (DamageAssessment::VERIFICATION_STATUSES as $st) {
                $byStatus[$st] = 0;
            }

            $byType = [];
            foreach (DamageAssessment::ASSESSMENT_TYPES as $tp) {
                $byType[$tp] = ['count' => 0, 'loss_bdt' => 0.0];
            }

            $bySeverity = [];
            foreach (DamageAssessment::SEVERITY_LEVELS as $sv) {
                $bySeverity[$sv] = 0;
            }

            $categoryAgg = [];
            $totalAffectedHouseholds = 0;
            $totalAffectedPopulation = 0;
            $totalDisplaced = 0;
            $totalDeaths = 0;
            $totalInjured = 0;
            $totalFully = 0;
            $totalPartially = 0;
            $totalMinor = 0;
            $totalLoss = 0.0;

            foreach ($assessments as $item) {
                $st = $item->verification_status;
                $byStatus[$st] = ($byStatus[$st] ?? 0) + 1;

                $tp = $item->assessment_type;
                if (!isset($byType[$tp])) {
                    $byType[$tp] = ['count' => 0, 'loss_bdt' => 0.0];
                }
                $byType[$tp]['count']++;
                $byType[$tp]['loss_bdt'] = round($byType[$tp]['loss_bdt'] + (float) $item->total_estimated_loss_bdt, 2);

                $sv = $item->severity_level;
                $bySeverity[$sv] = ($bySeverity[$sv] ?? 0) + 1;

                $totalAffectedHouseholds += (int) $item->affected_households;
                $totalAffectedPopulation += (int) $item->affected_population;
                $totalDisplaced += (int) $item->displaced_population;
                $totalDeaths += (int) $item->deaths_count;
                $totalInjured += (int) $item->injured_count;
                $totalFully += (int) $item->total_fully_damaged;
                $totalPartially += (int) $item->total_partially_damaged;
                $totalMinor += (int) $item->total_minor_damaged;
                $totalLoss += (float) $item->total_estimated_loss_bdt;

                foreach ($item->sectorItems as $sector) {
                    $catCode = $sector->category?->code ?? $sector->sector_code;
                    if (!isset($categoryAgg[$catCode])) {
                        $categoryAgg[$catCode] = [
                            'category_id' => $sector->category_id,
                            'code' => $catCode,
                            'name_en' => $sector->category?->name_en ?? $catCode,
                            'name_bn' => $sector->category?->name_bn ?? $catCode,
                            'color_hex' => $sector->category?->color_hex ?? '#0d6efd',
                            'items_count' => 0,
                            'fully_damaged_count' => 0,
                            'partially_damaged_count' => 0,
                            'minor_damaged_count' => 0,
                            'affected_households' => 0,
                            'estimated_loss_bdt' => 0.0,
                        ];
                    }
                    $categoryAgg[$catCode]['items_count']++;
                    $categoryAgg[$catCode]['fully_damaged_count'] += (int) $sector->fully_damaged_count;
                    $categoryAgg[$catCode]['partially_damaged_count'] += (int) $sector->partially_damaged_count;
                    $categoryAgg[$catCode]['minor_damaged_count'] += (int) $sector->minor_damaged_count;
                    $categoryAgg[$catCode]['affected_households'] += (int) $sector->affected_households;
                    $categoryAgg[$catCode]['estimated_loss_bdt'] = round(
                        $categoryAgg[$catCode]['estimated_loss_bdt'] + (float) $sector->estimated_loss_bdt,
                        2
                    );
                }
            }

            $categoryList = array_values($categoryAgg);
            usort($categoryList, fn ($a, $b) => $b['estimated_loss_bdt'] <=> $a['estimated_loss_bdt']);

            $verifiedCount = ($byStatus[DamageAssessment::STATUS_VERIFIED] ?? 0) + ($byStatus[DamageAssessment::STATUS_APPROVED] ?? 0);

            return [
                'summary' => [
                    'total_assessments' => $assessments->count(),
                    'verified_assessments' => $verifiedCount,
                    'approved_assessments' => $byStatus[DamageAssessment::STATUS_APPROVED] ?? 0,
                    'pending_verification' => ($byStatus[DamageAssessment::STATUS_SUBMITTED] ?? 0) + ($byStatus[DamageAssessment::STATUS_UNDER_VERIFICATION] ?? 0),
                    'correction_requested' => $byStatus[DamageAssessment::STATUS_CORRECTION_REQUESTED] ?? 0,
                    'rejected_assessments' => $byStatus[DamageAssessment::STATUS_REJECTED] ?? 0,
                    'draft_assessments' => $byStatus[DamageAssessment::STATUS_DRAFT] ?? 0,
                    'total_affected_households' => $totalAffectedHouseholds,
                    'total_affected_population' => $totalAffectedPopulation,
                    'total_displaced_population' => $totalDisplaced,
                    'total_deaths' => $totalDeaths,
                    'total_injured' => $totalInjured,
                    'total_fully_damaged' => $totalFully,
                    'total_partially_damaged' => $totalPartially,
                    'total_minor_damaged' => $totalMinor,
                    'total_estimated_loss_bdt' => round($totalLoss, 2),
                ],
                'by_status' => $byStatus,
                'by_assessment_type' => $byType,
                'by_severity' => $bySeverity,
                'damage_by_category' => $categoryList,
                'damage_by_area' => $this->spatialService->aggregateByGeographicalArea($assessments),
            ];
        });
    }

    /**
     * Generate analytical reports (disaster-wise, location-wise, category-wise, financial loss).
     * Verified & Approved assessments are used for official reporting by default.
     */
    public function getReports(User $user, string $reportType, array $filters = []): array
    {
        $isExternal = $this->policy->isExternalPartnerOnly($user);
        $includeUnverified = !$isExternal && filter_var($filters['include_unverified'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $query = DamageAssessment::inJurisdiction($user)
            ->with(['disasterEvent', 'administrativeUnit', 'sectorItems.category']);

        if (!$includeUnverified) {
            $query->whereIn('verification_status', DamageAssessment::REPORTABLE_STATUSES);
        }

        $this->applyCommonFilters($query, $filters);
        $assessments = $query->get();

        $disasterWise = [];
        $categoryWise = [];
        $financialRows = [];

        foreach ($assessments as $assessment) {
            $dId = $assessment->disaster_event_id;
            if (!isset($disasterWise[$dId])) {
                $disasterWise[$dId] = [
                    'disaster_event_id' => $dId,
                    'disaster_code' => $assessment->disasterEvent?->disaster_code,
                    'name_en' => $assessment->disasterEvent?->name_en,
                    'name_bn' => $assessment->disasterEvent?->name_bn,
                    'assessment_count' => 0,
                    'affected_households' => 0,
                    'affected_population' => 0,
                    'deaths_count' => 0,
                    'injured_count' => 0,
                    'total_fully_damaged' => 0,
                    'total_partially_damaged' => 0,
                    'total_minor_damaged' => 0,
                    'total_estimated_loss_bdt' => 0.0,
                ];
            }
            $disasterWise[$dId]['assessment_count']++;
            $disasterWise[$dId]['affected_households'] += (int) $assessment->affected_households;
            $disasterWise[$dId]['affected_population'] += (int) $assessment->affected_population;
            $disasterWise[$dId]['deaths_count'] += (int) $assessment->deaths_count;
            $disasterWise[$dId]['injured_count'] += (int) $assessment->injured_count;
            $disasterWise[$dId]['total_fully_damaged'] += (int) $assessment->total_fully_damaged;
            $disasterWise[$dId]['total_partially_damaged'] += (int) $assessment->total_partially_damaged;
            $disasterWise[$dId]['total_minor_damaged'] += (int) $assessment->total_minor_damaged;
            $disasterWise[$dId]['total_estimated_loss_bdt'] = round(
                $disasterWise[$dId]['total_estimated_loss_bdt'] + (float) $assessment->total_estimated_loss_bdt,
                2
            );

            $financialRows[] = [
                'id' => $assessment->id,
                'd_form_code' => $assessment->d_form_code,
                'disaster_code' => $assessment->disasterEvent?->disaster_code,
                'disaster_name_en' => $assessment->disasterEvent?->name_en,
                'disaster_name_bn' => $assessment->disasterEvent?->name_bn,
                'administrative_unit' => $assessment->administrativeUnit?->name_en,
                'administrative_unit_bn' => $assessment->administrativeUnit?->name_bn,
                'assessment_type' => $assessment->assessment_type,
                'assessment_date' => $assessment->assessment_date?->toDateString(),
                'verification_status' => $assessment->verification_status,
                'severity_level' => $assessment->severity_level,
                'affected_households' => (int) $assessment->affected_households,
                'total_fully_damaged' => (int) $assessment->total_fully_damaged,
                'total_partially_damaged' => (int) $assessment->total_partially_damaged,
                'total_minor_damaged' => (int) $assessment->total_minor_damaged,
                'total_estimated_loss_bdt' => (float) $assessment->total_estimated_loss_bdt,
            ];

            foreach ($assessment->sectorItems as $item) {
                $code = $item->category?->code ?? $item->sector_code;
                if (!isset($categoryWise[$code])) {
                    $categoryWise[$code] = [
                        'category_id' => $item->category_id,
                        'code' => $code,
                        'name_en' => $item->category?->name_en ?? $code,
                        'name_bn' => $item->category?->name_bn ?? $code,
                        'color_hex' => $item->category?->color_hex ?? '#0d6efd',
                        'assessment_lines' => 0,
                        'fully_damaged_count' => 0,
                        'partially_damaged_count' => 0,
                        'minor_damaged_count' => 0,
                        'affected_households' => 0,
                        'estimated_loss_bdt' => 0.0,
                    ];
                }
                $categoryWise[$code]['assessment_lines']++;
                $categoryWise[$code]['fully_damaged_count'] += (int) $item->fully_damaged_count;
                $categoryWise[$code]['partially_damaged_count'] += (int) $item->partially_damaged_count;
                $categoryWise[$code]['minor_damaged_count'] += (int) $item->minor_damaged_count;
                $categoryWise[$code]['affected_households'] += (int) $item->affected_households;
                $categoryWise[$code]['estimated_loss_bdt'] = round(
                    $categoryWise[$code]['estimated_loss_bdt'] + (float) $item->estimated_loss_bdt,
                    2
                );
            }
        }

        $disasterList = array_values($disasterWise);
        usort($disasterList, fn ($a, $b) => $b['total_estimated_loss_bdt'] <=> $a['total_estimated_loss_bdt']);

        $categoryList = array_values($categoryWise);
        usort($categoryList, fn ($a, $b) => $b['estimated_loss_bdt'] <=> $a['estimated_loss_bdt']);

        usort($financialRows, fn ($a, $b) => $b['total_estimated_loss_bdt'] <=> $a['total_estimated_loss_bdt']);

        return [
            'report_type' => $reportType,
            'verified_only' => !$includeUnverified,
            'total_assessments' => $assessments->count(),
            'total_estimated_loss_bdt' => round((float) $assessments->sum('total_estimated_loss_bdt'), 2),
            'disaster_wise' => $disasterList,
            'location_wise' => $this->spatialService->aggregateByGeographicalArea($assessments),
            'category_wise' => $categoryList,
            'financial_loss' => $financialRows,
        ];
    }

    public function exportCsv(User $user, array $filters = []): StreamedResponse
    {
        $isExternal = $this->policy->isExternalPartnerOnly($user);
        $query = DamageAssessment::inJurisdiction($user)
            ->with(['disasterEvent', 'administrativeUnit', 'sectorItems.category'])
            ->orderByDesc('assessment_date');

        if ($isExternal) {
            $query->whereIn('verification_status', DamageAssessment::REPORTABLE_STATUSES);
        }

        $this->applyCommonFilters($query, $filters);
        $rows = $query->get();

        AuditLog::log(
            'DAMAGE_ASSESSMENT_EXPORTED',
            DamageAssessment::class,
            null,
            null,
            ['exported_count' => $rows->count(), 'filters' => $filters],
            $user,
            $user->administrative_unit_id
        );

        $filename = 'idms-damage-assessments-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($rows, $isExternal) {
            $handle = fopen('php://output', 'w');
            // UTF-8 BOM for proper Unicode Bangla rendering in Excel
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'D-Form Code',
                'Disaster',
                'Administrative Unit',
                'Assessment Type',
                'Assessment Date',
                'Organization',
                'Team',
                'Assessor',
                'Severity',
                'Verification Status',
                'Affected Households',
                'Affected Population',
                'Fully Damaged',
                'Partially Damaged',
                'Minor Damage',
                'Estimated Loss (BDT)',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $this->sanitizeCsvCell($row->d_form_code),
                    $this->sanitizeCsvCell($row->disasterEvent?->name_en ?? $row->disasterEvent?->disaster_code),
                    $this->sanitizeCsvCell($row->administrativeUnit?->name_en),
                    $this->sanitizeCsvCell($row->assessment_type),
                    $this->sanitizeCsvCell($row->assessment_date?->toDateString()),
                    $this->sanitizeCsvCell($row->assessing_organization),
                    $this->sanitizeCsvCell($row->team_name),
                    $isExternal ? 'REDACTED' : $this->sanitizeCsvCell($row->assessor_name),
                    $this->sanitizeCsvCell($row->severity_level),
                    $this->sanitizeCsvCell($row->verification_status),
                    $row->affected_households,
                    $row->affected_population,
                    $row->total_fully_damaged,
                    $row->total_partially_damaged,
                    $row->total_minor_damaged,
                    number_format((float) $row->total_estimated_loss_bdt, 2, '.', ''),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function applyCommonFilters($query, array $filters): void
    {
        if (!empty($filters['disaster_event_id'])) {
            $query->where('disaster_event_id', $filters['disaster_event_id']);
        }
        if (!empty($filters['administrative_unit_id'])) {
            $unit = AdministrativeUnit::find($filters['administrative_unit_id']);
            if ($unit) {
                $path = rtrim($unit->ancestry_path, '/') . '/';
                $query->whereHas('administrativeUnit', function ($sub) use ($path) {
                    $sub->where('ancestry_path', 'like', $path . '%');
                });
            }
        }
        if (!empty($filters['verification_status'])) {
            $query->where('verification_status', strtoupper((string) $filters['verification_status']));
        }
        if (!empty($filters['assessment_type'])) {
            $query->where('assessment_type', strtoupper((string) $filters['assessment_type']));
        }
        if (!empty($filters['severity_level'])) {
            $query->where('severity_level', strtoupper((string) $filters['severity_level']));
        }
        if (!empty($filters['category_id'])) {
            $categoryId = $filters['category_id'];
            $query->whereHas('sectorItems', function ($sub) use ($categoryId) {
                $sub->where('category_id', $categoryId);
            });
        }
        if (!empty($filters['date_from'])) {
            $query->whereDate('assessment_date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('assessment_date', '<=', $filters['date_to']);
        }
        if (!empty($filters['search'])) {
            $term = '%' . trim((string) $filters['search']) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('d_form_code', 'like', $term)
                    ->orWhere('reference_no', 'like', $term)
                    ->orWhere('assessing_organization', 'like', $term)
                    ->orWhere('team_name', 'like', $term)
                    ->orWhere('location_summary', 'like', $term);
            });
        }
    }

    protected function transitionStatus(
        DamageAssessment $assessment,
        string $toStatus,
        string $action,
        User $actor,
        string $notes,
        array $extraAttributes = []
    ): DamageAssessment {
        return DB::transaction(function () use ($assessment, $toStatus, $action, $actor, $notes, $extraAttributes) {
            $fromStatus = $assessment->verification_status;

            $assessment->update(array_merge([
                'verification_status' => $toStatus,
                'updated_by' => $actor->id,
                'sync_version' => (int) $assessment->sync_version + 1,
            ], $extraAttributes));

            $this->recordVerificationLog($assessment, $action, $fromStatus, $toStatus, $actor, $notes);

            AuditLog::log(
                'DAMAGE_ASSESSMENT_' . $action,
                DamageAssessment::class,
                $assessment->id,
                ['verification_status' => $fromStatus],
                ['verification_status' => $toStatus, 'notes' => $notes],
                $actor,
                $assessment->administrative_unit_id
            );

            $this->clearDashboardCache();

            return $assessment->fresh($this->defaultRelations());
        });
    }

    protected function syncLocations(DamageAssessment $assessment, array $locations): void
    {
        $assessment->locations()->delete();

        foreach ($locations as $loc) {
            if (empty($loc['administrative_unit_id'])) {
                continue;
            }

            $created = DamageLocation::create([
                'damage_assessment_id' => $assessment->id,
                'administrative_unit_id' => $loc['administrative_unit_id'],
                'ward_no' => $loc['ward_no'] ?? null,
                'locality_name_en' => $loc['locality_name_en'] ?? null,
                'locality_name_bn' => $loc['locality_name_bn'] ?? null,
                'affected_households' => (int) ($loc['affected_households'] ?? 0),
                'affected_population' => (int) ($loc['affected_population'] ?? 0),
                'severity_level' => strtoupper((string) ($loc['severity_level'] ?? $assessment->severity_level)),
                'latitude' => $loc['latitude'] ?? null,
                'longitude' => $loc['longitude'] ?? null,
                'notes' => $loc['notes'] ?? null,
            ]);

            $this->spatialService->syncLocationGeometry($created);
        }
    }

    protected function syncSectorItems(DamageAssessment $assessment, array $items): void
    {
        $assessment->sectorItems()->delete();

        foreach ($items as $item) {
            $category = null;
            if (!empty($item['category_id'])) {
                $category = DamageCategory::find($item['category_id']);
            } elseif (!empty($item['sector_code'])) {
                $category = DamageCategory::where('code', strtoupper((string) $item['sector_code']))->first();
            }

            if (!$category) {
                throw ValidationException::withMessages([
                    'sector_items' => ['Each damage item must reference a valid damage category.'],
                ]);
            }

            $created = DamageSectorItem::create([
                'damage_assessment_id' => $assessment->id,
                'category_id' => $category->id,
                'sector_code' => $category->code,
                'item_title' => $item['item_title'] ?? null,
                'unit_of_measure' => $item['unit_of_measure'] ?? $category->default_unit ?? 'UNITS',
                'fully_damaged_count' => max(0, (int) ($item['fully_damaged_count'] ?? 0)),
                'partially_damaged_count' => max(0, (int) ($item['partially_damaged_count'] ?? 0)),
                'minor_damaged_count' => max(0, (int) ($item['minor_damaged_count'] ?? 0)),
                'damaged_quantity' => max(0, (float) ($item['damaged_quantity'] ?? 0)),
                'affected_households' => max(0, (int) ($item['affected_households'] ?? 0)),
                'affected_population' => max(0, (int) ($item['affected_population'] ?? 0)),
                'estimated_loss_bdt' => max(0, round((float) ($item['estimated_loss_bdt'] ?? 0), 2)),
                'latitude' => $item['latitude'] ?? null,
                'longitude' => $item['longitude'] ?? null,
                'remarks' => $item['remarks'] ?? null,
            ]);

            $this->spatialService->syncSectorItemGeometry($created);
        }
    }

    protected function recordVerificationLog(
        DamageAssessment $assessment,
        string $action,
        ?string $fromStatus,
        string $toStatus,
        User $actor,
        ?string $notes = null
    ): void {
        DamageVerificationLog::create([
            'damage_assessment_id' => $assessment->id,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'actor_id' => $actor->id,
            'actor_name' => $actor->full_name_en ?: $actor->email,
            'actor_role' => $actor->role_code,
            'notes' => $notes,
            'created_at' => now(),
        ]);
    }

    protected function assertUnitInUserScope(User $user, string $unitId, string $context): void
    {
        if ($user->isNationalScope()) {
            return;
        }

        $unit = AdministrativeUnit::findOrFail($unitId);
        if (!$this->scopeService->canAccessScope($user, $unit->ancestry_path)) {
            throw new AuthorizationException(
                "Jurisdiction violation: Target {$context} ({$unit->name_en}) lies outside your administrative scope."
            );
        }
    }

    protected function generateDFormCode(): string
    {
        return 'DF-MODMR-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));
    }

    public function defaultRelations(): array
    {
        return [
            'disasterEvent',
            'administrativeUnit',
            'locations.administrativeUnit',
            'sectorItems.category',
            'attachments.uploader',
            'verificationLogs',
            'creator',
            'submitter',
            'verifier',
            'approver',
            'rejector',
        ];
    }

    protected function sanitizeCsvCell(?string $value): string
    {
        $str = (string) ($value ?? '');
        if ($str !== '' && preg_match('/^[=+\-@\t\r]/', $str)) {
            return "'" . $str;
        }
        return $str;
    }

    public function clearDashboardCache(): void
    {
        $version = (int) Cache::get('damage:dashboard:version', 1);
        Cache::put('damage:dashboard:version', $version + 1, 86400);
    }
}
