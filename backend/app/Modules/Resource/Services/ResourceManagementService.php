<?php

namespace App\Modules\Resource\Services;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\AuditLog;
use App\Core\Models\User;
use App\Core\Services\AdministrativeScopeService;
use App\Modules\Disaster\Models\DisasterEvent;
use App\Modules\Resource\Models\HumanResourceDeployment;
use App\Modules\Resource\Models\ResourceAllocation;
use App\Modules\Resource\Models\ResourceAsset;
use App\Modules\Resource\Models\ResourceAttachment;
use App\Modules\Resource\Models\ResourceMaintenance;
use App\Modules\Response\Models\ResponseOperation;
use App\Modules\Response\Models\ResponseResourceAllocation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResourceManagementService
{
    private const CACHE_VERSION_KEY = 'resource:dashboard:version';

    public function __construct(
        protected AdministrativeScopeService $scopeService,
        protected ResourceSpatialService $spatialService
    ) {}

    public function registerAsset(array $data, User $actor): ResourceAsset
    {
        $this->assertUnitInUserScope($actor, $data['administrative_unit_id'], 'resource registration');

        return DB::transaction(function () use ($data, $actor) {
            $asset = ResourceAsset::create([
                'category_id' => $data['category_id'],
                'administrative_unit_id' => $data['administrative_unit_id'],
                'asset_code' => strtoupper(trim($data['asset_code'])),
                'asset_type' => $data['asset_type'] ?? strtoupper(str_replace(' ', '_', $data['resource_type'])),
                'resource_type' => $data['resource_type'],
                'name_en' => $data['name_en'],
                'name_bn' => $data['name_bn'],
                'description' => $data['description'] ?? null,
                'registration_no' => $data['registration_no'] ?? null,
                'capacity_info' => $data['capacity_info'] ?? null,
                'specifications' => $data['specifications'] ?? null,
                'owning_organization' => $data['owning_organization'],
                'current_location' => $data['current_location'],
                'responsible_officer_name' => $data['responsible_officer_name'],
                'responsible_officer_contact' => $data['responsible_officer_contact'] ?? null,
                'responsible_user_id' => $data['responsible_user_id'] ?? null,
                'acquisition_date' => $data['acquisition_date'] ?? null,
                'acquisition_value_bdt' => $data['acquisition_value_bdt'] ?? null,
                'next_maintenance_due_at' => $data['next_maintenance_due_at'] ?? null,
                'status' => $data['status'] ?? ResourceAsset::STATUS_AVAILABLE,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'is_active' => true,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->spatialService->syncAssetGeometry($asset);

            AuditLog::log(
                'RESOURCE_ASSET_REGISTERED',
                ResourceAsset::class,
                $asset->id,
                null,
                ['asset_code' => $asset->asset_code, 'status' => $asset->status],
                $actor,
                $asset->administrative_unit_id
            );

            $this->invalidateDashboardCache();

            return $asset->load(['category', 'administrativeUnit']);
        });
    }

    public function updateAsset(ResourceAsset $asset, array $data, User $actor): ResourceAsset
    {
        if (!empty($data['administrative_unit_id'])) {
            $this->assertUnitInUserScope($actor, $data['administrative_unit_id'], 'resource update');
        }

        return DB::transaction(function () use ($asset, $data, $actor) {
            /** @var ResourceAsset $locked */
            $locked = ResourceAsset::whereKey($asset->id)->lockForUpdate()->firstOrFail();

            if (isset($data['status']) && $data['status'] !== $locked->status) {
                $hasActiveAlloc = ResourceAllocation::where('resource_asset_id', $locked->id)
                    ->whereIn('status', ResourceAllocation::ACTIVE_STATUSES)
                    ->exists();
                $hasActiveResponse = ResponseResourceAllocation::where('resource_asset_id', $locked->id)
                    ->whereIn('status', [ResponseResourceAllocation::STATUS_ALLOCATED, ResponseResourceAllocation::STATUS_DEPLOYED])
                    ->exists();
                $hasActiveHr = HumanResourceDeployment::where('resource_asset_id', $locked->id)
                    ->whereIn('availability_status', HumanResourceDeployment::ACTIVE_STATUSES)
                    ->exists();

                if ($hasActiveAlloc || $hasActiveResponse || $hasActiveHr) {
                    throw ValidationException::withMessages([
                        'status' => ['Cannot manually change status while the resource has an active allocation or deployment; release or return it first.'],
                    ]);
                }
            }

            $oldStatus = $locked->status;
            $payload = collect($data)->only([
                'category_id',
                'administrative_unit_id',
                'asset_code',
                'asset_type',
                'resource_type',
                'name_en',
                'name_bn',
                'description',
                'registration_no',
                'capacity_info',
                'specifications',
                'owning_organization',
                'current_location',
                'responsible_officer_name',
                'responsible_officer_contact',
                'responsible_user_id',
                'acquisition_date',
                'acquisition_value_bdt',
                'next_maintenance_due_at',
                'status',
                'latitude',
                'longitude',
                'is_active',
            ])->toArray();

            if (isset($payload['asset_code'])) {
                $payload['asset_code'] = strtoupper(trim($payload['asset_code']));
            }
            $payload['updated_by'] = $actor->id;

            $locked->update($payload);
            $this->spatialService->syncAssetGeometry($locked);

            AuditLog::log(
                'RESOURCE_ASSET_UPDATED',
                ResourceAsset::class,
                $locked->id,
                ['status' => $oldStatus],
                ['asset_code' => $locked->asset_code, 'status' => $locked->status],
                $actor,
                $locked->administrative_unit_id
            );

            $this->invalidateDashboardCache();

            return $locked->fresh(['category', 'administrativeUnit', 'attachments']);
        });
    }

    public function retireAndDeleteAsset(ResourceAsset $asset, User $actor): void
    {
        DB::transaction(function () use ($asset, $actor) {
            $locked = ResourceAsset::whereKey($asset->id)->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, [
                ResourceAsset::STATUS_ASSIGNED,
                ResourceAsset::STATUS_IN_USE,
                ResourceAsset::STATUS_DEPLOYED,
                ResourceAsset::STATUS_UNDER_MAINTENANCE,
                ResourceAsset::STATUS_MAINTENANCE,
            ], true)) {
                throw ValidationException::withMessages([
                    'status' => ["Resource {$locked->asset_code} cannot be retired/removed while {$locked->status}."],
                ]);
            }

            $locked->update([
                'status' => ResourceAsset::STATUS_RETIRED,
                'is_active' => false,
                'updated_by' => $actor->id,
            ]);
            $locked->delete();

            AuditLog::log(
                'RESOURCE_ASSET_RETIRED',
                ResourceAsset::class,
                $locked->id,
                null,
                ['asset_code' => $locked->asset_code],
                $actor,
                $locked->administrative_unit_id
            );

            $this->invalidateDashboardCache();
        });
    }

    public function attachFile(
        ResourceAsset $asset,
        UploadedFile $file,
        string $kind,
        User $actor,
        ?string $title = null
    ): ResourceAttachment {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $filename = Str::uuid()->toString() . '.' . $ext;
        $path = $file->storeAs("resource/assets/{$asset->id}", $filename, 'local');

        $attachment = ResourceAttachment::create([
            'resource_asset_id' => $asset->id,
            'attachment_kind' => $kind,
            'title' => $title ?: $file->getClientOriginalName(),
            'original_name' => $file->getClientOriginalName(),
            'storage_path' => $path,
            'mime_type' => $file->getMimeType() ?: $file->getClientMimeType() ?: 'application/octet-stream',
            'file_size_bytes' => (int) $file->getSize(),
            'sha256_hash' => hash_file('sha256', Storage::disk('local')->path($path)),
            'uploaded_by' => $actor->id,
        ]);

        AuditLog::log(
            'RESOURCE_ATTACHMENT_UPLOADED',
            ResourceAttachment::class,
            $attachment->id,
            null,
            ['asset_code' => $asset->asset_code, 'kind' => $kind, 'sha256' => $attachment->sha256_hash],
            $actor,
            $asset->administrative_unit_id
        );

        return $attachment;
    }

    /**
     * Transaction-safe resource allocation: locks asset row, rejects unavailable states,
     * creates allocation record, and transitions asset status atomically.
     */
    public function allocateResource(array $data, User $actor): ResourceAllocation
    {
        return DB::transaction(function () use ($data, $actor) {
            /** @var ResourceAsset $asset */
            $asset = ResourceAsset::whereKey($data['resource_asset_id'])->lockForUpdate()->firstOrFail();

            $this->assertUnitInUserScope($actor, $asset->administrative_unit_id, 'resource asset');

            if (!$asset->isAvailableForAllocation()) {
                throw ValidationException::withMessages([
                    'resource_asset_id' => [
                        "Resource {$asset->asset_code} is currently {$asset->status} and unavailable for assignment.",
                    ],
                ]);
            }

            $activeConflict = ResourceAllocation::where('resource_asset_id', $asset->id)
                ->whereIn('status', ResourceAllocation::ACTIVE_STATUSES)
                ->exists()
                || ResponseResourceAllocation::where('resource_asset_id', $asset->id)
                    ->whereIn('status', [ResponseResourceAllocation::STATUS_ALLOCATED, ResponseResourceAllocation::STATUS_DEPLOYED])
                    ->exists();

            if ($activeConflict) {
                throw ValidationException::withMessages([
                    'resource_asset_id' => [
                        "Resource {$asset->asset_code} already has an active deployment or allocation.",
                    ],
                ]);
            }

            $operation = null;
            $disasterId = $data['disaster_event_id'] ?? null;
            $targetUnitId = $data['administrative_unit_id'] ?? null;

            if (!empty($data['response_operation_id'])) {
                $operation = ResponseOperation::findOrFail($data['response_operation_id']);
                $this->assertUnitInUserScope($actor, $operation->administrative_unit_id, 'response operation');

                if ($operation->status === ResponseOperation::STATUS_CLOSED) {
                    throw ValidationException::withMessages([
                        'response_operation_id' => ['Cannot allocate resources to a CLOSED response operation.'],
                    ]);
                }

                $disasterId = $disasterId ?: $operation->disaster_event_id;
                $targetUnitId = $targetUnitId ?: $operation->administrative_unit_id;
            }

            $targetUnitId = $targetUnitId ?: $asset->administrative_unit_id;
            $this->assertUnitInUserScope($actor, $targetUnitId, 'allocation destination');

            $targetStatus = $data['status'] ?? ResourceAllocation::STATUS_ASSIGNED;
            $assetNewStatus = $targetStatus === ResourceAllocation::STATUS_IN_USE
                ? ResourceAsset::STATUS_IN_USE
                : ResourceAsset::STATUS_ASSIGNED;

            $allocation = ResourceAllocation::create([
                'resource_asset_id' => $asset->id,
                'disaster_event_id' => $disasterId,
                'response_operation_id' => $operation?->id,
                'administrative_unit_id' => $targetUnitId,
                'allocation_no' => $this->generateRefNo('RES-ALLOC'),
                'allocation_location' => $data['allocation_location'],
                'latitude' => $data['latitude'] ?? $asset->latitude,
                'longitude' => $data['longitude'] ?? $asset->longitude,
                'assignment_date' => $data['assignment_date'],
                'expected_return_date' => $data['expected_return_date'] ?? null,
                'responsible_officer_name' => $data['responsible_officer_name'],
                'responsible_officer_contact' => $data['responsible_officer_contact'] ?? null,
                'responsible_user_id' => $data['responsible_user_id'] ?? null,
                'status' => $targetStatus,
                'purpose' => $data['purpose'] ?? null,
                'utilization_notes' => $data['utilization_notes'] ?? null,
                'allocated_by' => $actor->id,
            ]);

            $asset->update([
                'status' => $assetNewStatus,
                'current_location' => $data['allocation_location'],
                'updated_by' => $actor->id,
            ]);

            $this->spatialService->syncAllocationGeometry($allocation);

            AuditLog::log(
                'RESOURCE_ALLOCATED',
                ResourceAllocation::class,
                $allocation->id,
                ['asset_status' => ResourceAsset::STATUS_AVAILABLE],
                [
                    'allocation_no' => $allocation->allocation_no,
                    'asset_code' => $asset->asset_code,
                    'asset_status' => $assetNewStatus,
                ],
                $actor,
                $targetUnitId
            );

            $this->invalidateDashboardCache();

            return $allocation->load(['asset.category', 'disasterEvent', 'responseOperation', 'administrativeUnit']);
        });
    }

    public function markAllocationInUse(ResourceAllocation $allocation, User $actor, ?string $notes = null): ResourceAllocation
    {
        return DB::transaction(function () use ($allocation, $actor, $notes) {
            /** @var ResourceAllocation $lockedAlloc */
            $lockedAlloc = ResourceAllocation::whereKey($allocation->id)->lockForUpdate()->firstOrFail();

            if (!in_array($lockedAlloc->status, [ResourceAllocation::STATUS_ASSIGNED, ResourceAllocation::STATUS_IN_USE], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Only ASSIGNED allocations can be marked IN_USE.'],
                ]);
            }

            /** @var ResourceAsset $asset */
            $asset = ResourceAsset::whereKey($lockedAlloc->resource_asset_id)->lockForUpdate()->firstOrFail();

            $lockedAlloc->update([
                'status' => ResourceAllocation::STATUS_IN_USE,
                'utilization_notes' => $notes ?? $lockedAlloc->utilization_notes,
            ]);

            $asset->update([
                'status' => ResourceAsset::STATUS_IN_USE,
                'updated_by' => $actor->id,
            ]);

            AuditLog::log(
                'RESOURCE_IN_USE',
                ResourceAllocation::class,
                $lockedAlloc->id,
                null,
                ['allocation_no' => $lockedAlloc->allocation_no, 'asset_code' => $asset->asset_code],
                $actor,
                $lockedAlloc->administrative_unit_id
            );

            $this->invalidateDashboardCache();

            return $lockedAlloc->fresh(['asset.category', 'disasterEvent', 'responseOperation', 'administrativeUnit']);
        });
    }

    /**
     * Transaction-safe resource release / return.
     */
    public function releaseResource(ResourceAllocation $allocation, array $data, User $actor): ResourceAllocation
    {
        return DB::transaction(function () use ($allocation, $data, $actor) {
            /** @var ResourceAllocation $lockedAlloc */
            $lockedAlloc = ResourceAllocation::whereKey($allocation->id)->lockForUpdate()->firstOrFail();

            if (in_array($lockedAlloc->status, [ResourceAllocation::STATUS_RETURNED, ResourceAllocation::STATUS_CANCELLED], true)) {
                throw ValidationException::withMessages([
                    'status' => ["Allocation {$lockedAlloc->allocation_no} is already {$lockedAlloc->status}."],
                ]);
            }

            /** @var ResourceAsset $asset */
            $asset = ResourceAsset::whereKey($lockedAlloc->resource_asset_id)->lockForUpdate()->firstOrFail();

            $finalAllocStatus = ($data['cancel'] ?? false)
                ? ResourceAllocation::STATUS_CANCELLED
                : ResourceAllocation::STATUS_RETURNED;

            $returnCondition = strtoupper((string) ($data['return_condition'] ?? 'GOOD'));
            $nextAssetStatus = match ($returnCondition) {
                'DAMAGED' => ResourceAsset::STATUS_DAMAGED,
                'MAINTENANCE_REQUIRED' => ResourceAsset::STATUS_UNDER_MAINTENANCE,
                default => ResourceAsset::STATUS_AVAILABLE,
            };

            $lockedAlloc->update([
                'status' => $finalAllocStatus,
                'actual_return_date' => $data['actual_return_date'] ?? now()->toDateString(),
                'return_condition_notes' => $data['return_condition_notes'] ?? null,
                'released_by' => $actor->id,
            ]);

            $assetUpdates = [
                'status' => $nextAssetStatus,
                'updated_by' => $actor->id,
            ];
            if (!empty($data['return_location'])) {
                $assetUpdates['current_location'] = $data['return_location'];
            }
            $asset->update($assetUpdates);

            AuditLog::log(
                'RESOURCE_RELEASED',
                ResourceAllocation::class,
                $lockedAlloc->id,
                ['allocation_status' => $allocation->status],
                [
                    'allocation_no' => $lockedAlloc->allocation_no,
                    'allocation_status' => $finalAllocStatus,
                    'asset_status' => $nextAssetStatus,
                ],
                $actor,
                $lockedAlloc->administrative_unit_id
            );

            $this->invalidateDashboardCache();

            return $lockedAlloc->fresh(['asset.category', 'disasterEvent', 'responseOperation', 'administrativeUnit']);
        });
    }

    public function recordMaintenance(
        ResourceAsset $asset,
        array $data,
        User $actor,
        ?UploadedFile $file = null
    ): ResourceMaintenance {
        return DB::transaction(function () use ($asset, $data, $actor, $file) {
            /** @var ResourceAsset $locked */
            $locked = ResourceAsset::whereKey($asset->id)->lockForUpdate()->firstOrFail();
            $status = $data['status'] ?? ResourceMaintenance::STATUS_SCHEDULED;

            if ($status === ResourceMaintenance::STATUS_IN_PROGRESS && in_array($locked->status, [
                ResourceAsset::STATUS_ASSIGNED,
                ResourceAsset::STATUS_IN_USE,
                ResourceAsset::STATUS_DEPLOYED,
            ], true)) {
                throw ValidationException::withMessages([
                    'status' => ["Resource {$locked->asset_code} is currently {$locked->status}. Release it before starting maintenance."],
                ]);
            }

            $attachmentMeta = $this->storeMaintenanceAttachment($locked, $file);

            $maintenance = ResourceMaintenance::create(array_merge([
                'resource_asset_id' => $locked->id,
                'administrative_unit_id' => $locked->administrative_unit_id,
                'maintenance_no' => $this->generateRefNo('MNT'),
                'record_type' => $data['record_type'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'scheduled_date' => $data['scheduled_date'] ?? null,
                'performed_date' => $data['performed_date'] ?? ($status === ResourceMaintenance::STATUS_COMPLETED ? now()->toDateString() : null),
                'next_due_date' => $data['next_due_date'] ?? null,
                'reminder_days_before' => (int) ($data['reminder_days_before'] ?? 7),
                'inspection_findings' => $data['inspection_findings'] ?? null,
                'inspection_result' => $data['inspection_result'] ?? null,
                'repair_cost_bdt' => (float) ($data['repair_cost_bdt'] ?? 0),
                'vendor_or_workshop' => $data['vendor_or_workshop'] ?? null,
                'status' => $status,
                'performed_by_name' => $data['performed_by_name'] ?? null,
                'recorded_by' => $actor->id,
                'completed_by' => $status === ResourceMaintenance::STATUS_COMPLETED ? $actor->id : null,
            ], $attachmentMeta));

            $assetUpdates = ['updated_by' => $actor->id];
            if ($status === ResourceMaintenance::STATUS_IN_PROGRESS) {
                $assetUpdates['status'] = ResourceAsset::STATUS_UNDER_MAINTENANCE;
            } elseif ($status === ResourceMaintenance::STATUS_COMPLETED) {
                $assetUpdates['status'] = ($data['inspection_result'] ?? null) === ResourceMaintenance::INSPECTION_FAIL
                    ? ResourceAsset::STATUS_DAMAGED
                    : ResourceAsset::STATUS_AVAILABLE;
            }
            if (!empty($data['next_due_date'])) {
                $assetUpdates['next_maintenance_due_at'] = $data['next_due_date'];
            }
            $locked->update($assetUpdates);

            AuditLog::log(
                'RESOURCE_MAINTENANCE_RECORDED',
                ResourceMaintenance::class,
                $maintenance->id,
                null,
                [
                    'maintenance_no' => $maintenance->maintenance_no,
                    'asset_code' => $locked->asset_code,
                    'status' => $maintenance->status,
                    'repair_cost_bdt' => (float) $maintenance->repair_cost_bdt,
                ],
                $actor,
                $locked->administrative_unit_id
            );

            $this->invalidateDashboardCache();

            return $maintenance->load('asset');
        });
    }

    public function updateMaintenance(
        ResourceMaintenance $maintenance,
        array $data,
        User $actor,
        ?UploadedFile $file = null
    ): ResourceMaintenance {
        return DB::transaction(function () use ($maintenance, $data, $actor, $file) {
            /** @var ResourceMaintenance $lockedMnt */
            $lockedMnt = ResourceMaintenance::whereKey($maintenance->id)->lockForUpdate()->firstOrFail();
            /** @var ResourceAsset $asset */
            $asset = ResourceAsset::whereKey($lockedMnt->resource_asset_id)->lockForUpdate()->firstOrFail();

            $newStatus = $data['status'] ?? $lockedMnt->status;
            if ($newStatus === ResourceMaintenance::STATUS_IN_PROGRESS && in_array($asset->status, [
                ResourceAsset::STATUS_ASSIGNED,
                ResourceAsset::STATUS_IN_USE,
                ResourceAsset::STATUS_DEPLOYED,
            ], true)) {
                throw ValidationException::withMessages([
                    'status' => ["Resource {$asset->asset_code} is currently {$asset->status} and cannot enter maintenance."],
                ]);
            }

            $attachmentMeta = $this->storeMaintenanceAttachment($asset, $file);

            $updates = array_merge([
                'status' => $newStatus,
                'performed_date' => $data['performed_date'] ?? ($newStatus === ResourceMaintenance::STATUS_COMPLETED ? ($lockedMnt->performed_date ?? now()->toDateString()) : $lockedMnt->performed_date),
                'next_due_date' => $data['next_due_date'] ?? $lockedMnt->next_due_date,
                'inspection_findings' => $data['inspection_findings'] ?? $lockedMnt->inspection_findings,
                'inspection_result' => $data['inspection_result'] ?? $lockedMnt->inspection_result,
                'repair_cost_bdt' => array_key_exists('repair_cost_bdt', $data) ? (float) $data['repair_cost_bdt'] : $lockedMnt->repair_cost_bdt,
                'vendor_or_workshop' => $data['vendor_or_workshop'] ?? $lockedMnt->vendor_or_workshop,
                'performed_by_name' => $data['performed_by_name'] ?? $lockedMnt->performed_by_name,
                'completed_by' => $newStatus === ResourceMaintenance::STATUS_COMPLETED ? $actor->id : $lockedMnt->completed_by,
            ], $attachmentMeta);

            $lockedMnt->update($updates);

            $assetUpdates = ['updated_by' => $actor->id];
            if ($newStatus === ResourceMaintenance::STATUS_IN_PROGRESS) {
                $assetUpdates['status'] = ResourceAsset::STATUS_UNDER_MAINTENANCE;
            } elseif ($newStatus === ResourceMaintenance::STATUS_COMPLETED) {
                $result = $updates['inspection_result'] ?? null;
                $assetUpdates['status'] = $result === ResourceMaintenance::INSPECTION_FAIL
                    ? ResourceAsset::STATUS_DAMAGED
                    : ResourceAsset::STATUS_AVAILABLE;
            } elseif ($newStatus === ResourceMaintenance::STATUS_CANCELLED && $asset->status === ResourceAsset::STATUS_UNDER_MAINTENANCE) {
                $assetUpdates['status'] = ResourceAsset::STATUS_AVAILABLE;
            }
            if (!empty($updates['next_due_date'])) {
                $assetUpdates['next_maintenance_due_at'] = $updates['next_due_date'];
            }
            $asset->update($assetUpdates);

            AuditLog::log(
                'RESOURCE_MAINTENANCE_UPDATED',
                ResourceMaintenance::class,
                $lockedMnt->id,
                null,
                ['maintenance_no' => $lockedMnt->maintenance_no, 'status' => $newStatus, 'asset_status' => $asset->status],
                $actor,
                $asset->administrative_unit_id
            );

            $this->invalidateDashboardCache();

            return $lockedMnt->fresh('asset');
        });
    }

    public function getMaintenanceReminders(User $user, int $horizonDays = 30): array
    {
        $cutoff = now()->addDays(max(1, min(180, $horizonDays)))->toDateString();

        $dueAssets = ResourceAsset::inJurisdiction($user)
            ->with(['category', 'administrativeUnit'])
            ->where('is_active', true)
            ->where('status', '!=', ResourceAsset::STATUS_RETIRED)
            ->whereNotNull('next_maintenance_due_at')
            ->whereDate('next_maintenance_due_at', '<=', $cutoff)
            ->orderBy('next_maintenance_due_at')
            ->get()
            ->map(fn (ResourceAsset $a) => [
                'resource_asset_id' => $a->id,
                'asset_code' => $a->asset_code,
                'name_en' => $a->name_en,
                'name_bn' => $a->name_bn,
                'status' => $a->status,
                'due_date' => $a->next_maintenance_due_at?->toDateString(),
                'is_overdue' => $a->next_maintenance_due_at?->isPast() ?? false,
                'unit_name_en' => $a->administrativeUnit?->name_en,
                'unit_name_bn' => $a->administrativeUnit?->name_bn,
            ])
            ->values()
            ->all();

        $scheduledTasks = ResourceMaintenance::inJurisdiction($user)
            ->with('asset')
            ->whereIn('status', [ResourceMaintenance::STATUS_SCHEDULED, ResourceMaintenance::STATUS_IN_PROGRESS])
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('scheduled_date')
                    ->orWhereDate('scheduled_date', '<=', $cutoff);
            })
            ->orderBy('scheduled_date')
            ->limit(25)
            ->get()
            ->map(fn (ResourceMaintenance $m) => [
                'maintenance_id' => $m->id,
                'maintenance_no' => $m->maintenance_no,
                'resource_asset_id' => $m->resource_asset_id,
                'asset_code' => $m->asset?->asset_code,
                'asset_name_en' => $m->asset?->name_en,
                'title' => $m->title,
                'record_type' => $m->record_type,
                'status' => $m->status,
                'scheduled_date' => $m->scheduled_date?->toDateString(),
                'is_overdue' => $m->scheduled_date?->isPast() ?? false,
            ])
            ->values()
            ->all();

        return [
            'due_assets' => $dueAssets,
            'scheduled_tasks' => $scheduledTasks,
        ];
    }

    public function assignHumanResource(array $data, User $actor): HumanResourceDeployment
    {
        return DB::transaction(function () use ($data, $actor) {
            $asset = null;
            $operation = null;
            $disasterId = $data['disaster_event_id'] ?? null;
            $unitId = $data['administrative_unit_id'] ?? null;

            if (!empty($data['resource_asset_id'])) {
                /** @var ResourceAsset $asset */
                $asset = ResourceAsset::whereKey($data['resource_asset_id'])->lockForUpdate()->firstOrFail();
                $this->assertUnitInUserScope($actor, $asset->administrative_unit_id, 'human resource');

                if (!$asset->isAvailableForAllocation()) {
                    throw ValidationException::withMessages([
                        'resource_asset_id' => ["Human resource {$asset->asset_code} is currently {$asset->status} and unavailable."],
                    ]);
                }
                $unitId = $unitId ?: $asset->administrative_unit_id;
            }

            if (!empty($data['response_operation_id'])) {
                $operation = ResponseOperation::findOrFail($data['response_operation_id']);
                $this->assertUnitInUserScope($actor, $operation->administrative_unit_id, 'response operation');

                if ($operation->status === ResponseOperation::STATUS_CLOSED) {
                    throw ValidationException::withMessages([
                        'response_operation_id' => ['Cannot deploy personnel to a CLOSED response operation.'],
                    ]);
                }

                $disasterId = $disasterId ?: $operation->disaster_event_id;
                $unitId = $unitId ?: $operation->administrative_unit_id;
            }

            if (!$unitId) {
                throw ValidationException::withMessages([
                    'administrative_unit_id' => ['Administrative unit is required for human resource deployment.'],
                ]);
            }
            $this->assertUnitInUserScope($actor, $unitId, 'human resource deployment');

            if (!empty($data['user_id'])) {
                $userConflict = HumanResourceDeployment::where('user_id', $data['user_id'])
                    ->whereIn('availability_status', HumanResourceDeployment::ACTIVE_STATUSES)
                    ->exists();
                if ($userConflict) {
                    throw ValidationException::withMessages([
                        'user_id' => ['Selected officer already has an active deployment. Release them before reassigning.'],
                    ]);
                }
            }

            if (!empty($data['response_team_id'])) {
                $teamConflict = HumanResourceDeployment::where('response_team_id', $data['response_team_id'])
                    ->whereIn('availability_status', HumanResourceDeployment::ACTIVE_STATUSES)
                    ->exists();
                if ($teamConflict) {
                    throw ValidationException::withMessages([
                        'response_team_id' => ['Selected response team already has an active deployment.'],
                    ]);
                }
            }

            $status = $data['availability_status'] ?? HumanResourceDeployment::STATUS_ASSIGNED;

            $deployment = HumanResourceDeployment::create([
                'resource_asset_id' => $asset?->id,
                'user_id' => $data['user_id'] ?? null,
                'response_team_id' => $data['response_team_id'] ?? null,
                'response_operation_id' => $operation?->id,
                'disaster_event_id' => $disasterId,
                'administrative_unit_id' => $unitId,
                'deployment_no' => $this->generateRefNo('HR-DEP'),
                'personnel_name' => $data['personnel_name'],
                'personnel_designation' => $data['personnel_designation'] ?? null,
                'personnel_contact' => $data['personnel_contact'] ?? null,
                'assigned_duty' => $data['assigned_duty'],
                'deployment_location' => $data['deployment_location'] ?? null,
                'deployment_start_date' => $data['deployment_start_date'],
                'deployment_end_date' => $data['deployment_end_date'] ?? null,
                'availability_status' => $status,
                'notes' => $data['notes'] ?? null,
                'assigned_by' => $actor->id,
            ]);

            if ($asset) {
                $asset->update([
                    'status' => $status === HumanResourceDeployment::STATUS_IN_USE
                        ? ResourceAsset::STATUS_IN_USE
                        : ResourceAsset::STATUS_ASSIGNED,
                    'updated_by' => $actor->id,
                ]);
            }

            AuditLog::log(
                'HUMAN_RESOURCE_DEPLOYED',
                HumanResourceDeployment::class,
                $deployment->id,
                null,
                ['deployment_no' => $deployment->deployment_no, 'personnel_name' => $deployment->personnel_name],
                $actor,
                $unitId
            );

            $this->invalidateDashboardCache();

            return $deployment->load(['asset', 'team', 'responseOperation', 'disasterEvent', 'administrativeUnit']);
        });
    }

    public function releaseHumanResource(HumanResourceDeployment $deployment, array $data, User $actor): HumanResourceDeployment
    {
        return DB::transaction(function () use ($deployment, $data, $actor) {
            /** @var HumanResourceDeployment $locked */
            $locked = HumanResourceDeployment::whereKey($deployment->id)->lockForUpdate()->firstOrFail();

            if (in_array($locked->availability_status, [HumanResourceDeployment::STATUS_COMPLETED, HumanResourceDeployment::STATUS_CANCELLED], true)) {
                throw ValidationException::withMessages([
                    'availability_status' => ['Human resource deployment is already finalized.'],
                ]);
            }

            $finalStatus = ($data['cancel'] ?? false)
                ? HumanResourceDeployment::STATUS_CANCELLED
                : HumanResourceDeployment::STATUS_COMPLETED;

            $locked->update([
                'availability_status' => $finalStatus,
                'actual_return_date' => $data['actual_return_date'] ?? now()->toDateString(),
                'notes' => $data['notes'] ?? $locked->notes,
                'released_by' => $actor->id,
            ]);

            if ($locked->resource_asset_id) {
                $asset = ResourceAsset::whereKey($locked->resource_asset_id)->lockForUpdate()->first();
                if ($asset) {
                    $asset->update([
                        'status' => ResourceAsset::STATUS_AVAILABLE,
                        'updated_by' => $actor->id,
                    ]);
                }
            }

            AuditLog::log(
                'HUMAN_RESOURCE_RELEASED',
                HumanResourceDeployment::class,
                $locked->id,
                null,
                ['deployment_no' => $locked->deployment_no, 'status' => $finalStatus],
                $actor,
                $locked->administrative_unit_id
            );

            $this->invalidateDashboardCache();

            return $locked->fresh(['asset', 'team', 'responseOperation', 'disasterEvent', 'administrativeUnit']);
        });
    }

    public function getDashboardAnalytics(User $user, array $filters = []): array
    {
        $scopePath = $user->isNationalScope() ? 'NATIONAL' : ($user->getAdministrativeScope()['ancestry_path'] ?? '/');
        $version = Cache::get(self::CACHE_VERSION_KEY, 1);
        $cacheKey = 'resource:dash:' . md5($version . '|' . $scopePath . '|' . json_encode($filters));

        return Cache::remember($cacheKey, 60, function () use ($user, $filters) {
            $categoryId = $filters['category_id'] ?? null;
            $disasterId = $filters['disaster_event_id'] ?? null;

            $assetQuery = ResourceAsset::inJurisdiction($user)
                ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId));

            $activeExpr = DB::getDriverName() === 'pgsql' ? 'is_active IS TRUE' : 'is_active = 1';

            $counts = (clone $assetQuery)->selectRaw("
                COUNT(*) as total_resources,
                SUM(CASE WHEN status = 'AVAILABLE' AND {$activeExpr} THEN 1 ELSE 0 END) as available_resources,
                SUM(CASE WHEN status = 'ASSIGNED' THEN 1 ELSE 0 END) as assigned_resources,
                SUM(CASE WHEN status IN ('IN_USE', 'DEPLOYED') THEN 1 ELSE 0 END) as in_use_resources,
                SUM(CASE WHEN status IN ('UNDER_MAINTENANCE', 'MAINTENANCE') THEN 1 ELSE 0 END) as maintenance_resources,
                SUM(CASE WHEN status = 'DAMAGED' THEN 1 ELSE 0 END) as damaged_resources,
                SUM(CASE WHEN status = 'RETIRED' THEN 1 ELSE 0 END) as retired_resources,
                COALESCE(SUM(acquisition_value_bdt), 0) as total_asset_value_bdt
            ")->first();

            $activeAllocationsCount = ResourceAllocation::inJurisdiction($user)
                ->when($disasterId, fn ($q) => $q->where('disaster_event_id', $disasterId))
                ->whereIn('status', ResourceAllocation::ACTIVE_STATUSES)
                ->count();

            $activeHrCount = HumanResourceDeployment::inJurisdiction($user)
                ->when($disasterId, fn ($q) => $q->where('disaster_event_id', $disasterId))
                ->whereIn('availability_status', HumanResourceDeployment::ACTIVE_STATUSES)
                ->count();

            $mntQuery = ResourceMaintenance::inJurisdiction($user);
            $totalRepairCost = (float) (clone $mntQuery)->sum('repair_cost_bdt');
            $reminders = $this->getMaintenanceReminders($user, 30);

            // Utilization by disaster
            $allocTable = (new ResourceAllocation())->getTable();
            $disasterTable = (new DisasterEvent())->getTable();
            $utilizationByDisaster = ResourceAllocation::inJurisdiction($user)
                ->join($disasterTable, "{$disasterTable}.id", '=', "{$allocTable}.disaster_event_id")
                ->when($disasterId, fn ($q) => $q->where("{$allocTable}.disaster_event_id", $disasterId))
                ->selectRaw("
                    {$allocTable}.disaster_event_id,
                    {$disasterTable}.disaster_code,
                    {$disasterTable}.name_en as disaster_name_en,
                    {$disasterTable}.name_bn as disaster_name_bn,
                    COUNT(*) as total_allocations,
                    SUM(CASE WHEN {$allocTable}.status IN ('ASSIGNED', 'IN_USE') THEN 1 ELSE 0 END) as active_allocations,
                    SUM(CASE WHEN {$allocTable}.status = 'RETURNED' THEN 1 ELSE 0 END) as returned_allocations
                ")
                ->groupBy(
                    "{$allocTable}.disaster_event_id",
                    "{$disasterTable}.disaster_code",
                    "{$disasterTable}.name_en",
                    "{$disasterTable}.name_bn"
                )
                ->orderByDesc('total_allocations')
                ->limit(10)
                ->get()
                ->map(fn ($r) => [
                    'disaster_event_id' => $r->disaster_event_id,
                    'disaster_code' => $r->disaster_code,
                    'disaster_name_en' => $r->disaster_name_en,
                    'disaster_name_bn' => $r->disaster_name_bn ?: $r->disaster_name_en,
                    'total_allocations' => (int) $r->total_allocations,
                    'active_allocations' => (int) $r->active_allocations,
                    'returned_allocations' => (int) $r->returned_allocations,
                ])
                ->all();

            // Geographical distribution
            $assetTable = (new ResourceAsset())->getTable();
            $adminTable = (new AdministrativeUnit())->getTable();
            $geoDistribution = ResourceAsset::inJurisdiction($user)
                ->join($adminTable, "{$adminTable}.id", '=', "{$assetTable}.administrative_unit_id")
                ->when($categoryId, fn ($q) => $q->where("{$assetTable}.category_id", $categoryId))
                ->selectRaw("
                    {$assetTable}.administrative_unit_id,
                    {$adminTable}.name_en as unit_name_en,
                    {$adminTable}.name_bn as unit_name_bn,
                    {$adminTable}.tier_level,
                    COUNT(*) as total_resources,
                    SUM(CASE WHEN {$assetTable}.status = 'AVAILABLE' THEN 1 ELSE 0 END) as available_count,
                    SUM(CASE WHEN {$assetTable}.status IN ('ASSIGNED', 'IN_USE', 'DEPLOYED') THEN 1 ELSE 0 END) as assigned_count,
                    SUM(CASE WHEN {$assetTable}.status IN ('UNDER_MAINTENANCE', 'MAINTENANCE') THEN 1 ELSE 0 END) as maintenance_count
                ")
                ->groupBy(
                    "{$assetTable}.administrative_unit_id",
                    "{$adminTable}.name_en",
                    "{$adminTable}.name_bn",
                    "{$adminTable}.tier_level"
                )
                ->orderByDesc('total_resources')
                ->limit(15)
                ->get()
                ->map(fn ($r) => [
                    'administrative_unit_id' => $r->administrative_unit_id,
                    'unit_name_en' => $r->unit_name_en,
                    'unit_name_bn' => $r->unit_name_bn ?: $r->unit_name_en,
                    'tier_level' => $r->tier_level,
                    'total_resources' => (int) $r->total_resources,
                    'available_count' => (int) $r->available_count,
                    'assigned_count' => (int) $r->assigned_count,
                    'maintenance_count' => (int) $r->maintenance_count,
                ])
                ->all();

            $catTable = (new \App\Modules\Resource\Models\ResourceCategory())->getTable();
            $byCategory = ResourceAsset::inJurisdiction($user)
                ->leftJoin($catTable, "{$catTable}.id", '=', "{$assetTable}.category_id")
                ->selectRaw("
                    COALESCE({$catTable}.code, 'UNCATEGORIZED') as code,
                    COALESCE({$catTable}.name_en, 'Uncategorized') as name_en,
                    COALESCE({$catTable}.name_bn, 'অন্যান্য') as name_bn,
                    COALESCE({$catTable}.color_hex, '#6c757d') as color_hex,
                    COUNT(*) as total,
                    SUM(CASE WHEN {$assetTable}.status = 'AVAILABLE' THEN 1 ELSE 0 END) as available,
                    SUM(CASE WHEN {$assetTable}.status IN ('ASSIGNED', 'IN_USE', 'DEPLOYED') THEN 1 ELSE 0 END) as assigned_or_in_use
                ")
                ->groupBy(
                    "{$catTable}.code",
                    "{$catTable}.name_en",
                    "{$catTable}.name_bn",
                    "{$catTable}.color_hex"
                )
                ->orderByDesc('total')
                ->get()
                ->map(fn ($r) => [
                    'code' => $r->code,
                    'name_en' => $r->name_en,
                    'name_bn' => $r->name_bn,
                    'color_hex' => $r->color_hex,
                    'total' => (int) $r->total,
                    'available' => (int) $r->available,
                    'assigned_or_in_use' => (int) $r->assigned_or_in_use,
                ])
                ->all();

            return [
                'summary' => [
                    'total_resources' => (int) ($counts->total_resources ?? 0),
                    'available_resources' => (int) ($counts->available_resources ?? 0),
                    'assigned_resources' => (int) (($counts->assigned_resources ?? 0) + ($counts->in_use_resources ?? 0)),
                    'in_use_resources' => (int) ($counts->in_use_resources ?? 0),
                    'under_maintenance_resources' => (int) ($counts->maintenance_resources ?? 0),
                    'damaged_resources' => (int) ($counts->damaged_resources ?? 0),
                    'retired_resources' => (int) ($counts->retired_resources ?? 0),
                    'active_allocations' => $activeAllocationsCount,
                    'active_human_deployments' => $activeHrCount,
                    'total_asset_value_bdt' => (float) ($counts->total_asset_value_bdt ?? 0),
                    'total_repair_cost_bdt' => $totalRepairCost,
                    'maintenance_reminders_count' => count($reminders['due_assets']) + count($reminders['scheduled_tasks']),
                ],
                'by_category' => $byCategory,
                'utilization_by_disaster' => $utilizationByDisaster,
                'geographical_distribution' => $geoDistribution,
                'maintenance_reminders' => $reminders,
            ];
        });
    }

    public function exportCsv(User $user, array $filters = []): StreamedResponse
    {
        $isExternal = $user->hasRole('ROLE_EXTERNAL_PARTNER');

        $query = ResourceAsset::inJurisdiction($user)
            ->with(['category', 'administrativeUnit'])
            ->when(!empty($filters['category_id']), fn ($q) => $q->where('category_id', $filters['category_id']))
            ->when(!empty($filters['status']), fn ($q) => $q->whereIn('status', ResourceAsset::statusesForFilter((string) $filters['status'])))
            ->orderBy('asset_code');

        $rows = $query->get();

        AuditLog::log(
            'RESOURCE_REGISTER_EXPORTED',
            ResourceAsset::class,
            null,
            null,
            ['exported_count' => $rows->count()],
            $user
        );

        return response()->streamDownload(function () use ($rows, $isExternal) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Asset Code',
                'Category',
                'Resource Type',
                'Name (EN)',
                'Name (BN)',
                'Owning Organization',
                'Administrative Unit',
                'Current Location',
                'Status',
                'Responsible Officer',
                'Acquisition Date',
                'Acquisition Value (BDT)',
            ]);

            foreach ($rows as $r) {
                fputcsv($out, [
                    $this->sanitizeCsvCell($r->asset_code),
                    $this->sanitizeCsvCell($r->category?->name_en ?? 'N/A'),
                    $this->sanitizeCsvCell($r->resource_type ?: $r->asset_type),
                    $this->sanitizeCsvCell($r->name_en),
                    $this->sanitizeCsvCell($r->name_bn),
                    $this->sanitizeCsvCell($r->owning_organization ?? ''),
                    $this->sanitizeCsvCell($r->administrativeUnit?->name_en ?? ''),
                    $this->sanitizeCsvCell($r->current_location ?? ''),
                    $this->sanitizeCsvCell($r->status),
                    $this->sanitizeCsvCell($isExternal ? 'Restricted' : ($r->responsible_officer_name ?? '')),
                    $r->acquisition_date?->toDateString() ?? '',
                    $r->acquisition_value_bdt ?? '',
                ]);
            }
            fclose($out);
        }, 'idms-resource-register.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function storeMaintenanceAttachment(ResourceAsset $asset, ?UploadedFile $file): array
    {
        if (!$file) {
            return [];
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $filename = Str::uuid()->toString() . '.' . $ext;
        $path = $file->storeAs("resource/maintenances/{$asset->id}", $filename, 'local');

        return [
            'attachment_path' => $path,
            'attachment_original_name' => $file->getClientOriginalName(),
            'attachment_mime' => $file->getMimeType() ?: $file->getClientMimeType() ?: 'application/octet-stream',
            'attachment_size_bytes' => (int) $file->getSize(),
            'attachment_sha256' => hash_file('sha256', Storage::disk('local')->path($path)),
        ];
    }

    private function assertUnitInUserScope(User $actor, string $administrativeUnitId, string $context): void
    {
        $unit = AdministrativeUnit::findOrFail($administrativeUnitId);
        if (!$this->scopeService->canAccessScope($actor, $unit->ancestry_path)) {
            throw new AuthorizationException(
                "Jurisdiction access denied: target {$context} unit is outside your administrative hierarchy."
            );
        }
    }

    private function invalidateDashboardCache(): void
    {
        $next = ((int) Cache::get(self::CACHE_VERSION_KEY, 1)) + 1;
        Cache::put(self::CACHE_VERSION_KEY, $next, 86400);
    }

    private function generateRefNo(string $prefix): string
    {
        return sprintf('%s-%s-%s', $prefix, now()->format('Ymd'), strtoupper(Str::random(5)));
    }

    private function sanitizeCsvCell(?string $val): string
    {
        $str = (string) $val;
        if ($str !== '' && in_array($str[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $str;
        }
        return $str;
    }
}
