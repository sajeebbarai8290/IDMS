<?php

namespace App\Modules\Response\Services;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\AuditLog;
use App\Core\Models\User;
use App\Core\Services\AdministrativeScopeService;
use App\Modules\Response\Models\ResourceAsset;
use App\Modules\Response\Models\ResponseCenter;
use App\Modules\Response\Models\ResponseOperation;
use App\Modules\Response\Models\ResponseOperationAgency;
use App\Modules\Response\Models\ResponseResourceAllocation;
use App\Modules\Response\Models\ResponseSituationReport;
use App\Modules\Response\Models\ResponseTask;
use App\Modules\Response\Models\ResponseTaskAttachment;
use App\Modules\Response\Models\ResponseTeam;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResponseManagementService
{
    public function __construct(
        protected AdministrativeScopeService $scopeService
    ) {}

    public function createOperation(array $data, User $actor): ResponseOperation
    {
        $this->assertUnitInUserScope($actor, $data['administrative_unit_id'], 'response operation');

        if (!empty($data['response_center_id'])) {
            $center = ResponseCenter::findOrFail($data['response_center_id']);
            $this->assertUnitInUserScope($actor, $center->administrative_unit_id, 'response center');
        }

        return DB::transaction(function () use ($data, $actor) {
            $operation = ResponseOperation::create([
                'disaster_event_id' => $data['disaster_event_id'],
                'administrative_unit_id' => $data['administrative_unit_id'],
                'response_center_id' => $data['response_center_id'] ?? null,
                'operation_no' => $this->generateRefNo('OP'),
                'title_en' => $data['title_en'],
                'title_bn' => $data['title_bn'] ?? null,
                'objectives' => $data['objectives'] ?? null,
                'description' => $data['description'] ?? null,
                'priority' => $data['priority'] ?? 'HIGH',
                'severity_level' => $data['severity_level'] ?? 'WARNING',
                'status' => ResponseOperation::STATUS_PLANNED,
                'start_date' => $data['start_date'],
                'expected_completion_date' => $data['expected_completion_date'] ?? null,
                'lead_organization' => $data['lead_organization'],
                'commander_name' => $data['commander_name'] ?? null,
                'commander_user_id' => $data['commander_user_id'] ?? null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            AuditLog::log(
                'RESPONSE_OPERATION_CREATED',
                ResponseOperation::class,
                $operation->id,
                null,
                ['operation_no' => $operation->operation_no, 'status' => $operation->status],
                $actor,
                $operation->administrative_unit_id
            );

            return $operation->load(['disasterEvent', 'administrativeUnit', 'responseCenter', 'creator']);
        });
    }

    public function updateOperation(ResponseOperation $operation, array $data, User $actor): ResponseOperation
    {
        $this->assertNotClosed($operation);

        if (!empty($data['administrative_unit_id'])) {
            $this->assertUnitInUserScope($actor, $data['administrative_unit_id'], 'response operation');
        }

        if (!empty($data['response_center_id'])) {
            $center = ResponseCenter::findOrFail($data['response_center_id']);
            $this->assertUnitInUserScope($actor, $center->administrative_unit_id, 'response center');
        }

        $operation->update(array_merge(
            collect($data)->only([
                'disaster_event_id',
                'administrative_unit_id',
                'response_center_id',
                'title_en',
                'title_bn',
                'objectives',
                'description',
                'priority',
                'severity_level',
                'start_date',
                'expected_completion_date',
                'lead_organization',
                'commander_name',
                'commander_user_id',
            ])->toArray(),
            ['updated_by' => $actor->id]
        ));

        AuditLog::log(
            'RESPONSE_OPERATION_UPDATED',
            ResponseOperation::class,
            $operation->id,
            null,
            ['operation_no' => $operation->operation_no],
            $actor,
            $operation->administrative_unit_id
        );

        return $operation->fresh(['disasterEvent', 'administrativeUnit', 'responseCenter']);
    }

    public function transitionLifecycle(
        ResponseOperation $operation,
        string $targetStatus,
        User $actor,
        ?string $notes = null
    ): ResponseOperation {
        $this->assertNotClosed($operation);

        return DB::transaction(function () use ($operation, $targetStatus, $actor, $notes) {
            $oldStatus = $operation->status;
            $updates = [
                'status' => $targetStatus,
                'status_notes' => $notes ?? $operation->status_notes,
                'updated_by' => $actor->id,
            ];

            if ($targetStatus === ResponseOperation::STATUS_ACTIVATED && !$operation->activated_at) {
                $updates['activated_by'] = $actor->id;
                $updates['activated_at'] = now();
            }

            if ($targetStatus === ResponseOperation::STATUS_COMPLETED) {
                $updates['actual_completion_date'] = now()->toDateString();
            }

            if ($targetStatus === ResponseOperation::STATUS_CLOSED) {
                $updates['closed_by'] = $actor->id;
                $updates['closed_at'] = now();
                if (!$operation->actual_completion_date) {
                    $updates['actual_completion_date'] = now()->toDateString();
                }

                // Auto-return any active resource allocations and release assets when operation closes
                $openAllocations = ResponseResourceAllocation::where('response_operation_id', $operation->id)
                    ->whereIn('status', [ResponseResourceAllocation::STATUS_ALLOCATED, ResponseResourceAllocation::STATUS_DEPLOYED])
                    ->get();

                foreach ($openAllocations as $alloc) {
                    $alloc->update(['status' => ResponseResourceAllocation::STATUS_RETURNED]);
                    if ($alloc->resource_asset_id) {
                        $asset = ResourceAsset::whereKey($alloc->resource_asset_id)->lockForUpdate()->first();
                        if ($asset) {
                            $asset->update(['status' => ResourceAsset::STATUS_AVAILABLE]);
                        }
                    }
                }
            }

            $operation->update($updates);

            AuditLog::log(
                'RESPONSE_OPERATION_LIFECYCLE_CHANGED',
                ResponseOperation::class,
                $operation->id,
                ['status' => $oldStatus],
                ['status' => $targetStatus, 'notes' => $notes],
                $actor,
                $operation->administrative_unit_id
            );

            return $operation->fresh(['disasterEvent', 'administrativeUnit', 'responseCenter']);
        });
    }

    public function addAgency(ResponseOperation $operation, array $data, User $actor): ResponseOperationAgency
    {
        $this->assertNotClosed($operation);
        $agency = ResponseOperationAgency::create([
            'response_operation_id' => $operation->id,
            'agency_name' => $data['agency_name'],
            'agency_type' => $data['agency_type'],
            'role_summary' => $data['role_summary'] ?? null,
            'focal_person_name' => $data['focal_person_name'] ?? null,
            'focal_contact' => $data['focal_contact'] ?? null,
            'coordination_status' => $data['coordination_status'] ?? 'ACTIVE',
            'added_by' => $actor->id,
        ]);

        AuditLog::log(
            'RESPONSE_AGENCY_COORDINATED',
            ResponseOperationAgency::class,
            $agency->id,
            null,
            ['operation_no' => $operation->operation_no, 'agency_name' => $agency->agency_name],
            $actor,
            $operation->administrative_unit_id
        );

        return $agency;
    }

    public function addTeam(ResponseOperation $operation, array $data, User $actor): ResponseTeam
    {
        $this->assertNotClosed($operation);
        $unitId = $data['administrative_unit_id'] ?? $operation->administrative_unit_id;
        $this->assertUnitInUserScope($actor, $unitId, 'response team');

        $team = ResponseTeam::create([
            'response_operation_id' => $operation->id,
            'administrative_unit_id' => $unitId,
            'team_code' => $this->generateRefNo('TEAM'),
            'team_name' => $data['team_name'],
            'agency_name' => $data['agency_name'],
            'team_type' => $data['team_type'],
            'leader_name' => $data['leader_name'] ?? null,
            'leader_contact' => $data['leader_contact'] ?? null,
            'leader_user_id' => $data['leader_user_id'] ?? null,
            'member_count' => (int) ($data['member_count'] ?? 1),
            'status' => $data['status'] ?? 'DEPLOYED',
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'last_checkin_at' => now(),
        ]);

        if (DB::getDriverName() === 'pgsql' && $team->latitude !== null && $team->longitude !== null) {
            DB::statement(
                'UPDATE response.response_teams SET location_geom = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?',
                [$team->longitude, $team->latitude, $team->id]
            );
        }

        AuditLog::log(
            'RESPONSE_TEAM_DEPLOYED',
            ResponseTeam::class,
            $team->id,
            null,
            ['team_code' => $team->team_code, 'team_name' => $team->team_name],
            $actor,
            $unitId
        );

        return $team->load('administrativeUnit');
    }

    public function updateTeamStatus(ResponseTeam $team, array $data, User $actor): ResponseTeam
    {
        $team->update([
            'status' => $data['status'],
            'latitude' => $data['latitude'] ?? $team->latitude,
            'longitude' => $data['longitude'] ?? $team->longitude,
            'last_checkin_at' => now(),
        ]);

        if (DB::getDriverName() === 'pgsql' && $team->latitude !== null && $team->longitude !== null) {
            DB::statement(
                'UPDATE response.response_teams SET location_geom = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?',
                [$team->longitude, $team->latitude, $team->id]
            );
        }

        return $team->fresh('administrativeUnit');
    }

    public function createTask(ResponseOperation $operation, array $data, User $actor): ResponseTask
    {
        $this->assertNotClosed($operation);
        if (!empty($data['response_team_id'])) {
            $teamBelongs = ResponseTeam::whereKey($data['response_team_id'])
                ->where('response_operation_id', $operation->id)
                ->exists();
            if (!$teamBelongs) {
                throw ValidationException::withMessages([
                    'response_team_id' => ['Selected team does not belong to this response operation.'],
                ]);
            }
        }

        $task = ResponseTask::create([
            'response_operation_id' => $operation->id,
            'activity_type_id' => $data['activity_type_id'],
            'response_team_id' => $data['response_team_id'] ?? null,
            'assigned_user_id' => $data['assigned_user_id'] ?? null,
            'task_code' => $this->generateRefNo('TSK'),
            'title_en' => $data['title_en'],
            'title_bn' => $data['title_bn'] ?? null,
            'description' => $data['description'] ?? null,
            'assigned_officer_name' => $data['assigned_officer_name'] ?? null,
            'priority' => $data['priority'] ?? 'HIGH',
            'status' => ResponseTask::STATUS_PENDING,
            'progress_percentage' => 0,
            'deadline_at' => $data['deadline_at'] ?? null,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        AuditLog::log(
            'RESPONSE_TASK_CREATED',
            ResponseTask::class,
            $task->id,
            null,
            ['task_code' => $task->task_code, 'operation_no' => $operation->operation_no],
            $actor,
            $operation->administrative_unit_id
        );

        return $task->load(['activityType', 'team', 'attachments']);
    }

    public function updateTaskProgress(ResponseTask $task, array $data, User $actor): ResponseTask
    {
        $task->loadMissing('operation');
        if ($task->operation) {
            $this->assertNotClosed($task->operation);
        }

        $status = $data['status'] ?? $task->status;
        $progress = isset($data['progress_percentage'])
            ? (int) $data['progress_percentage']
            : $task->progress_percentage;

        if ($status === ResponseTask::STATUS_COMPLETED) {
            $progress = 100;
        }

        $task->update([
            'status' => $status,
            'progress_percentage' => max(0, min(100, $progress)),
            'completion_notes' => $data['completion_notes'] ?? $task->completion_notes,
            'completed_at' => $status === ResponseTask::STATUS_COMPLETED ? ($task->completed_at ?? now()) : null,
            'updated_by' => $actor->id,
        ]);

        $task->loadMissing('operation');
        AuditLog::log(
            'RESPONSE_TASK_UPDATED',
            ResponseTask::class,
            $task->id,
            null,
            ['task_code' => $task->task_code, 'status' => $status, 'progress' => $progress],
            $actor,
            $task->operation?->administrative_unit_id
        );

        return $task->fresh(['activityType', 'team', 'attachments']);
    }

    public function attachTaskEvidence(ResponseTask $task, UploadedFile $file, User $actor, ?string $title = null): ResponseTaskAttachment
    {
        $task->loadMissing('operation');
        if ($task->operation) {
            $this->assertNotClosed($task->operation);
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $filename = Str::uuid()->toString() . '.' . $ext;
        $path = $file->storeAs("response/tasks/{$task->id}", $filename, 'local');

        $attachment = ResponseTaskAttachment::create([
            'response_task_id' => $task->id,
            'title' => $title ?: $file->getClientOriginalName(),
            'original_name' => $file->getClientOriginalName(),
            'storage_path' => $path,
            'mime_type' => $file->getMimeType() ?: $file->getClientMimeType() ?: 'application/octet-stream',
            'file_size_bytes' => (int) $file->getSize(),
            'sha256_hash' => hash_file('sha256', Storage::disk('local')->path($path)),
            'uploaded_by' => $actor->id,
        ]);

        $task->loadMissing('operation');
        AuditLog::log(
            'RESPONSE_TASK_EVIDENCE_UPLOADED',
            ResponseTaskAttachment::class,
            $attachment->id,
            null,
            ['task_code' => $task->task_code, 'sha256' => $attachment->sha256_hash],
            $actor,
            $task->operation?->administrative_unit_id
        );

        return $attachment;
    }

    public function addResourceAllocation(ResponseOperation $operation, array $data, User $actor): ResponseResourceAllocation
    {
        $this->assertNotClosed($operation);

        return DB::transaction(function () use ($operation, $data, $actor) {
            $assetId = $data['resource_asset_id'] ?? null;
            $status = $data['status'] ?? ResponseResourceAllocation::STATUS_REQUESTED;
            $qtyAssigned = (float) ($data['quantity_assigned'] ?? 0);

            if ($assetId) {
                /** @var ResourceAsset $asset */
                $asset = ResourceAsset::whereKey($assetId)->lockForUpdate()->firstOrFail();
                $this->assertUnitInUserScope($actor, $asset->administrative_unit_id, 'resource asset');

                if ($asset->status !== ResourceAsset::STATUS_AVAILABLE) {
                    throw ValidationException::withMessages([
                        'resource_asset_id' => ["Resource {$asset->asset_code} is currently {$asset->status} and cannot be linked."],
                    ]);
                }

                $asset->update(['status' => ResourceAsset::STATUS_DEPLOYED]);
                $status = ResponseResourceAllocation::STATUS_DEPLOYED;
                if ($qtyAssigned <= 0) {
                    $qtyAssigned = 1;
                }
            }

            $allocation = ResponseResourceAllocation::create([
                'response_operation_id' => $operation->id,
                'resource_asset_id' => $assetId,
                'requirement_title' => $data['requirement_title'],
                'category' => $data['category'] ?? 'VEHICLE_EQUIPMENT',
                'quantity_needed' => (float) ($data['quantity_needed'] ?? 1),
                'quantity_assigned' => $qtyAssigned,
                'unit' => $data['unit'] ?? 'UNIT',
                'priority' => $data['priority'] ?? 'HIGH',
                'status' => $status,
                'notes' => $data['notes'] ?? null,
                'requested_by' => $actor->id,
            ]);

            AuditLog::log(
                'RESPONSE_RESOURCE_LINKED',
                ResponseResourceAllocation::class,
                $allocation->id,
                null,
                ['operation_no' => $operation->operation_no, 'status' => $allocation->status],
                $actor,
                $operation->administrative_unit_id
            );

            return $allocation->load('asset');
        });
    }

    public function updateResourceAllocationStatus(ResponseResourceAllocation $allocation, string $status, User $actor): ResponseResourceAllocation
    {
        return DB::transaction(function () use ($allocation, $status, $actor) {
            $allocation->update(['status' => $status]);

            if ($allocation->resource_asset_id) {
                $asset = ResourceAsset::whereKey($allocation->resource_asset_id)->lockForUpdate()->first();
                if ($asset) {
                    $assetStatus = $status === ResponseResourceAllocation::STATUS_RETURNED
                        ? ResourceAsset::STATUS_AVAILABLE
                        : ResourceAsset::STATUS_DEPLOYED;
                    $asset->update(['status' => $assetStatus]);
                }
            }

            $allocation->loadMissing('operation');
            AuditLog::log(
                'RESPONSE_RESOURCE_STATUS_UPDATED',
                ResponseResourceAllocation::class,
                $allocation->id,
                null,
                ['status' => $status],
                $actor,
                $allocation->operation?->administrative_unit_id
            );

            return $allocation->fresh('asset');
        });
    }

    public function createSituationReport(ResponseOperation $operation, array $data, User $actor): ResponseSituationReport
    {
        $unitId = $data['administrative_unit_id'] ?? $operation->administrative_unit_id;
        $this->assertUnitInUserScope($actor, $unitId, 'situation report');

        $report = ResponseSituationReport::create([
            'response_operation_id' => $operation->id,
            'disaster_event_id' => $operation->disaster_event_id,
            'administrative_unit_id' => $unitId,
            'report_no' => $this->generateRefNo('SIT'),
            'report_type' => $data['report_type'] ?? 'FIELD_UPDATE',
            'severity_level' => $data['severity_level'] ?? $operation->severity_level,
            'title_en' => $data['title_en'],
            'title_bn' => $data['title_bn'] ?? null,
            'summary' => $data['summary'],
            'casualties_injured' => (int) ($data['casualties_injured'] ?? 0),
            'casualties_missing' => (int) ($data['casualties_missing'] ?? 0),
            'casualties_fatalities' => (int) ($data['casualties_fatalities'] ?? 0),
            'rescued_count' => (int) ($data['rescued_count'] ?? 0),
            'evacuated_count' => (int) ($data['evacuated_count'] ?? 0),
            'emergency_requirements' => $data['emergency_requirements'] ?? null,
            'location_description' => $data['location_description'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'privacy_level' => $data['privacy_level'] ?? ResponseSituationReport::PRIVACY_PUBLIC_AREA,
            'reported_by' => $actor->id,
            'reported_at' => now(),
        ]);

        if (DB::getDriverName() === 'pgsql' && $report->latitude !== null && $report->longitude !== null) {
            DB::statement(
                'UPDATE response.response_situation_reports SET location_geom = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?',
                [$report->longitude, $report->latitude, $report->id]
            );
        }

        AuditLog::log(
            'RESPONSE_SITUATION_REPORT_CREATED',
            ResponseSituationReport::class,
            $report->id,
            null,
            ['report_no' => $report->report_no, 'report_type' => $report->report_type],
            $actor,
            $unitId
        );

        return $report->load(['administrativeUnit', 'operation', 'reporter']);
    }

    public function getDashboardAnalytics(User $user, array $filters = []): array
    {
        $disasterId = $filters['disaster_event_id'] ?? null;

        $opQuery = ResponseOperation::inJurisdiction($user)
            ->when($disasterId, fn ($q) => $q->where('disaster_event_id', $disasterId));

        $opIds = (clone $opQuery)->pluck('id');

        $totalOperations = $opIds->count();
        $activeOperations = (clone $opQuery)
            ->whereIn('status', [ResponseOperation::STATUS_ACTIVATED, ResponseOperation::STATUS_ONGOING])
            ->count();

        $taskTotals = ResponseTask::whereIn('response_operation_id', $opIds)
            ->selectRaw("
                COUNT(*) as total_tasks,
                SUM(CASE WHEN status IN ('PENDING', 'IN_PROGRESS', 'BLOCKED') THEN 1 ELSE 0 END) as pending_tasks,
                SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) as completed_tasks
            ")
            ->first();

        $teamsTotal = ResponseTeam::whereIn('response_operation_id', $opIds)->count();
        $teamsActive = ResponseTeam::whereIn('response_operation_id', $opIds)
            ->whereIn('status', ['DEPLOYED', 'ACTIVE'])
            ->count();

        $sitrepTotals = ResponseSituationReport::whereIn('response_operation_id', $opIds)
            ->selectRaw('
                COUNT(*) as total_reports,
                COALESCE(SUM(rescued_count), 0) as total_rescued,
                COALESCE(SUM(evacuated_count), 0) as total_evacuated,
                COALESCE(SUM(casualties_injured), 0) as total_injured,
                COALESCE(SUM(casualties_missing), 0) as total_missing,
                COALESCE(SUM(casualties_fatalities), 0) as total_fatalities
            ')
            ->first();

        $openRequirements = ResponseResourceAllocation::whereIn('response_operation_id', $opIds)
            ->where('status', ResponseResourceAllocation::STATUS_REQUESTED)
            ->count();

        $adminTable = (new AdministrativeUnit())->getTable();
        $opTable = (new ResponseOperation())->getTable();

        $geographicalCoverage = (clone $opQuery)
            ->join($adminTable, "{$adminTable}.id", '=', "{$opTable}.administrative_unit_id")
            ->selectRaw("
                {$opTable}.administrative_unit_id,
                {$adminTable}.name_en as unit_name_en,
                {$adminTable}.name_bn as unit_name_bn,
                {$adminTable}.tier_level,
                COUNT(*) as operations_count,
                SUM(CASE WHEN {$opTable}.status IN ('ACTIVATED', 'ONGOING') THEN 1 ELSE 0 END) as active_count
            ")
            ->groupBy(
                "{$opTable}.administrative_unit_id",
                "{$adminTable}.name_en",
                "{$adminTable}.name_bn",
                "{$adminTable}.tier_level"
            )
            ->limit(12)
            ->get()
            ->map(fn ($r) => [
                'administrative_unit_id' => $r->administrative_unit_id,
                'unit_name_en' => $r->unit_name_en,
                'unit_name_bn' => $r->unit_name_bn ?: $r->unit_name_en,
                'tier_level' => $r->tier_level,
                'operations_count' => (int) $r->operations_count,
                'active_count' => (int) $r->active_count,
            ])
            ->all();

        $emergencyRequirements = ResponseResourceAllocation::whereIn('response_operation_id', $opIds)
            ->with('operation:id,operation_no,title_en,title_bn')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'requirement_title' => $r->requirement_title,
                'category' => $r->category,
                'quantity_needed' => (float) $r->quantity_needed,
                'quantity_assigned' => (float) $r->quantity_assigned,
                'unit' => $r->unit,
                'priority' => $r->priority,
                'status' => $r->status,
                'operation_no' => $r->operation?->operation_no,
            ])
            ->all();

        return [
            'summary' => [
                'total_operations' => $totalOperations,
                'active_operations' => $activeOperations,
                'pending_tasks' => (int) ($taskTotals->pending_tasks ?? 0),
                'completed_tasks' => (int) ($taskTotals->completed_tasks ?? 0),
                'total_tasks' => (int) ($taskTotals->total_tasks ?? 0),
                'response_teams_total' => $teamsTotal,
                'response_teams_active' => $teamsActive,
                'open_requirements' => $openRequirements,
                'total_reports' => (int) ($sitrepTotals->total_reports ?? 0),
                'total_rescued' => (int) ($sitrepTotals->total_rescued ?? 0),
                'total_evacuated' => (int) ($sitrepTotals->total_evacuated ?? 0),
                'total_injured' => (int) ($sitrepTotals->total_injured ?? 0),
                'total_missing' => (int) ($sitrepTotals->total_missing ?? 0),
                'total_fatalities' => (int) ($sitrepTotals->total_fatalities ?? 0),
            ],
            'geographical_coverage' => $geographicalCoverage,
            'emergency_requirements' => $emergencyRequirements,
        ];
    }

    private function assertNotClosed(ResponseOperation $operation): void
    {
        if ($operation->status === ResponseOperation::STATUS_CLOSED) {
            throw ValidationException::withMessages([
                'status' => ['A CLOSED response operation is finalized and cannot be modified.'],
            ]);
        }
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

    private function generateRefNo(string $prefix): string
    {
        return sprintf('%s-%s-%s', $prefix, now()->format('Ymd'), strtoupper(Str::random(5)));
    }
}
