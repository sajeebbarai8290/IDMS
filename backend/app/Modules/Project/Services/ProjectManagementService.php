<?php

namespace App\Modules\Project\Services;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\AuditLog;
use App\Core\Models\User;
use App\Core\Services\AdministrativeScopeService;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectDisbursement;
use App\Modules\Project\Models\ProjectLocation;
use App\Modules\Project\Models\ProjectMilestone;
use App\Modules\Project\Models\ProjectMonitoringReport;
use App\Modules\Project\Models\ProjectRisk;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectManagementService
{
    public function __construct(
        protected AdministrativeScopeService $scopeService,
        protected ProjectSpatialService $spatialService
    ) {}

    /**
     * Calculate weighted physical progress percentage across a project's milestones/activities.
     */
    public function computeWeightedPhysicalProgress(iterable $milestones): float
    {
        $totalWeight = 0.0;
        $weightedSum = 0.0;
        $count = 0;
        $unweightedSum = 0.0;

        foreach ($milestones as $milestone) {
            $weight = max(0.0, (float) $milestone->weight_percentage);
            $completion = min(100.0, max(0.0, (float) $milestone->completion_percentage));
            $totalWeight += $weight;
            $weightedSum += ($weight * $completion);
            $unweightedSum += $completion;
            $count++;
        }

        if ($count === 0) {
            return 0.0;
        }

        if ($totalWeight > 0.0) {
            return round(min(100.0, $weightedSum / $totalWeight), 2);
        }

        return round(min(100.0, $unweightedSum / $count), 2);
    }

    /**
     * Calculate financial metrics (effective budget, financial progress %, remaining balance).
     */
    public function calculateFinancialSummary(
        float $approvedBudget,
        ?float $revisedBudget,
        float $releasedFunds,
        float $expenditure
    ): array {
        $effectiveBudget = ($revisedBudget !== null && $revisedBudget > 0) ? $revisedBudget : $approvedBudget;
        $financialPct = $effectiveBudget > 0
            ? round(min(100.0, max(0.0, ($expenditure / $effectiveBudget) * 100.0)), 2)
            : 0.0;

        return [
            'effective_budget_bdt' => round($effectiveBudget, 2),
            'remaining_balance_bdt' => round(max(0.0, $effectiveBudget - $expenditure), 2),
            'unreleased_budget_bdt' => round(max(0.0, $effectiveBudget - $releasedFunds), 2),
            'unspent_released_bdt' => round(max(0.0, $releasedFunds - $expenditure), 2),
            'financial_progress_pct' => $financialPct,
        ];
    }

    public function createProject(array $data, User $actor): Project
    {
        $this->assertUnitInUserScope($actor, $data['administrative_unit_id'], 'project jurisdiction');

        $locations = $data['locations'] ?? [];
        foreach ($locations as $loc) {
            if (!empty($loc['administrative_unit_id'])) {
                $this->assertUnitInUserScope($actor, $loc['administrative_unit_id'], 'project location');
            }
        }

        return DB::transaction(function () use ($data, $locations, $actor) {
            $normalizedBoundary = !empty($data['boundary_geojson'])
                ? $this->spatialService->normalizeToPolygonGeometry($data['boundary_geojson'])
                : null;

            $projectCode = !empty($data['project_code'])
                ? strtoupper(trim($data['project_code']))
                : $this->generateRefNo('PRJ');

            $project = Project::create([
                'category_id' => $data['category_id'],
                'disaster_event_id' => $data['disaster_event_id'] ?? null,
                'administrative_unit_id' => $data['administrative_unit_id'],
                'project_code' => $projectCode,
                'reference_no' => $data['reference_no'] ?? null,
                'title_en' => $data['title_en'],
                'title_bn' => $data['title_bn'],
                'description' => $data['description'] ?? null,
                'objectives' => $data['objectives'] ?? null,
                'implementing_organization' => $data['implementing_organization'],
                'project_manager_name' => $data['project_manager_name'],
                'project_manager_contact' => $data['project_manager_contact'] ?? null,
                'project_manager_user_id' => $data['project_manager_user_id'] ?? null,
                'funding_source' => $data['funding_source'],
                'approved_budget_bdt' => $data['approved_budget_bdt'],
                'revised_budget_bdt' => $data['revised_budget_bdt'] ?? null,
                'released_funds_bdt' => 0,
                'expenditure_bdt' => 0,
                'physical_progress_pct' => 0,
                'financial_progress_pct' => 0,
                'start_date' => $data['start_date'],
                'expected_end_date' => $data['expected_end_date'],
                'ward_no' => $data['ward_no'] ?? null,
                'location_summary' => $data['location_summary'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'boundary_geojson' => $normalizedBoundary,
                'status' => Project::STATUS_PROPOSED,
                'status_notes' => $data['status_notes'] ?? null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->syncLocations($project, $locations);
            $this->spatialService->syncProjectGeometry($project);

            AuditLog::log(
                'PROJECT_CREATED',
                Project::class,
                $project->id,
                null,
                [
                    'project_code' => $project->project_code,
                    'status' => $project->status,
                    'approved_budget_bdt' => (float) $project->approved_budget_bdt,
                ],
                $actor,
                $project->administrative_unit_id
            );

            $this->clearDashboardCache();

            return $project->fresh(['category', 'disasterEvent', 'administrativeUnit', 'locations.administrativeUnit', 'creator']);
        });
    }

    public function updateProject(Project $project, array $data, User $actor): Project
    {
        $this->assertNotClosed($project);

        if (!empty($data['administrative_unit_id'])) {
            $this->assertUnitInUserScope($actor, $data['administrative_unit_id'], 'project jurisdiction');
        }

        if (array_key_exists('locations', $data) && is_array($data['locations'])) {
            foreach ($data['locations'] as $loc) {
                if (!empty($loc['administrative_unit_id'])) {
                    $this->assertUnitInUserScope($actor, $loc['administrative_unit_id'], 'project location');
                }
            }
        }

        return DB::transaction(function () use ($project, $data, $actor) {
            $oldSnapshot = [
                'title_en' => $project->title_en,
                'approved_budget_bdt' => (float) $project->approved_budget_bdt,
                'expected_end_date' => $project->expected_end_date?->toDateString(),
            ];

            $payload = collect($data)->only([
                'category_id',
                'disaster_event_id',
                'administrative_unit_id',
                'reference_no',
                'title_en',
                'title_bn',
                'description',
                'objectives',
                'implementing_organization',
                'project_manager_name',
                'project_manager_contact',
                'project_manager_user_id',
                'funding_source',
                'approved_budget_bdt',
                'revised_budget_bdt',
                'start_date',
                'expected_end_date',
                'ward_no',
                'location_summary',
                'latitude',
                'longitude',
                'is_delayed',
                'delay_days',
                'delay_reason',
                'status_notes',
            ])->toArray();

            if (array_key_exists('boundary_geojson', $data)) {
                $payload['boundary_geojson'] = !empty($data['boundary_geojson'])
                    ? $this->spatialService->normalizeToPolygonGeometry($data['boundary_geojson'])
                    : null;
            }

            $payload['updated_by'] = $actor->id;

            // Validate that revised/approved budget does not drop below already recorded expenditure
            $newApproved = (float) ($payload['approved_budget_bdt'] ?? $project->approved_budget_bdt);
            $newRevised = array_key_exists('revised_budget_bdt', $payload)
                ? ($payload['revised_budget_bdt'] !== null ? (float) $payload['revised_budget_bdt'] : null)
                : ($project->revised_budget_bdt !== null ? (float) $project->revised_budget_bdt : null);
            $effective = ($newRevised !== null && $newRevised > 0) ? $newRevised : $newApproved;

            if ($effective < (float) $project->expenditure_bdt) {
                throw ValidationException::withMessages([
                    'approved_budget_bdt' => 'Effective budget cannot be less than already recorded project expenditure.',
                ]);
            }

            $fin = $this->calculateFinancialSummary(
                $newApproved,
                $newRevised,
                (float) $project->released_funds_bdt,
                (float) $project->expenditure_bdt
            );
            $payload['financial_progress_pct'] = $fin['financial_progress_pct'];

            $project->update($payload);

            if (array_key_exists('locations', $data) && is_array($data['locations'])) {
                $this->syncLocations($project, $data['locations']);
            }

            $this->spatialService->syncProjectGeometry($project);

            AuditLog::log(
                'PROJECT_UPDATED',
                Project::class,
                $project->id,
                $oldSnapshot,
                [
                    'project_code' => $project->project_code,
                    'approved_budget_bdt' => (float) $project->approved_budget_bdt,
                ],
                $actor,
                $project->administrative_unit_id
            );

            $this->clearDashboardCache();

            return $project->fresh(['category', 'disasterEvent', 'administrativeUnit', 'locations.administrativeUnit']);
        });
    }

    public function deleteProject(Project $project, User $actor): void
    {
        if (!in_array($project->status, [Project::STATUS_PROPOSED, Project::STATUS_UNDER_REVIEW], true)) {
            throw ValidationException::withMessages([
                'status' => 'Only PROPOSED or UNDER_REVIEW projects can be deleted.',
            ]);
        }

        $project->delete();

        AuditLog::log(
            'PROJECT_DELETED',
            Project::class,
            $project->id,
            ['status' => $project->status],
            ['deleted' => true],
            $actor,
            $project->administrative_unit_id
        );

        $this->clearDashboardCache();
    }

    public function transitionLifecycle(
        Project $project,
        string $targetStatus,
        User $actor,
        ?string $notes = null,
        ?string $delayReason = null
    ): Project {
        $this->assertNotClosed($project);

        $allowed = Project::ALLOWED_TRANSITIONS[$project->status] ?? [];
        if (!in_array($targetStatus, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "Invalid project lifecycle transition from {$project->status} to {$targetStatus}.",
            ]);
        }

        if ($targetStatus === Project::STATUS_APPROVED && $project->created_by === $actor->id) {
            throw ValidationException::withMessages([
                'status' => '4-Eye Separation of Duties: Project creator cannot approve their own project proposal.',
            ]);
        }

        return DB::transaction(function () use ($project, $targetStatus, $actor, $notes, $delayReason) {
            $oldStatus = $project->status;
            $updates = [
                'status' => $targetStatus,
                'status_notes' => $notes ?? $project->status_notes,
                'updated_by' => $actor->id,
            ];

            if ($targetStatus === Project::STATUS_UNDER_REVIEW) {
                $updates['reviewed_by'] = $actor->id;
                $updates['reviewed_at'] = now();
            }

            if ($targetStatus === Project::STATUS_APPROVED) {
                $updates['approved_by'] = $actor->id;
                $updates['approved_at'] = now();
            }

            if ($targetStatus === Project::STATUS_SUSPENDED && $delayReason) {
                $updates['is_delayed'] = true;
                $updates['delay_reason'] = $delayReason;
            }

            if ($targetStatus === Project::STATUS_COMPLETED && !$project->actual_end_date) {
                $updates['actual_end_date'] = now()->toDateString();
            }

            if ($targetStatus === Project::STATUS_CLOSED) {
                $updates['closed_by'] = $actor->id;
                $updates['closed_at'] = now();
                if (!$project->actual_end_date) {
                    $updates['actual_end_date'] = now()->toDateString();
                }
            }

            $project->update($updates);

            AuditLog::log(
                'PROJECT_LIFECYCLE_TRANSITION',
                Project::class,
                $project->id,
                ['status' => $oldStatus],
                ['status' => $targetStatus, 'notes' => $notes],
                $actor,
                $project->administrative_unit_id
            );

            $this->clearDashboardCache();

            return $project->fresh(['category', 'disasterEvent', 'administrativeUnit', 'approver', 'reviewer']);
        });
    }

    public function uploadBoundary(
        Project $project,
        array $geojson,
        ?float $lat,
        ?float $lng,
        User $actor
    ): Project {
        $this->assertNotClosed($project);

        $normalized = $this->spatialService->normalizeToPolygonGeometry($geojson);
        if (!$normalized) {
            throw ValidationException::withMessages([
                'boundary_geojson' => 'Uploaded boundary does not contain valid Polygon or MultiPolygon geometries.',
            ]);
        }

        return DB::transaction(function () use ($project, $normalized, $lat, $lng, $actor) {
            $updates = [
                'boundary_geojson' => $normalized,
                'updated_by' => $actor->id,
            ];
            if ($lat !== null && $lng !== null) {
                $updates['latitude'] = $lat;
                $updates['longitude'] = $lng;
            }

            $project->update($updates);
            $this->spatialService->syncProjectGeometry($project);

            AuditLog::log(
                'PROJECT_BOUNDARY_UPDATED',
                Project::class,
                $project->id,
                null,
                ['geometry_type' => $normalized['type']],
                $actor,
                $project->administrative_unit_id
            );

            $this->clearDashboardCache();

            return $project->fresh(['category', 'administrativeUnit', 'locations.administrativeUnit']);
        });
    }

    public function createMilestone(Project $project, array $data, User $actor): ProjectMilestone
    {
        $this->assertNotClosed($project);

        if (!empty($data['parent_milestone_id'])) {
            $parent = ProjectMilestone::where('project_id', $project->id)->find($data['parent_milestone_id']);
            if (!$parent) {
                throw ValidationException::withMessages([
                    'parent_milestone_id' => 'Parent WBS item must belong to the same project.',
                ]);
            }
        }

        return DB::transaction(function () use ($project, $data, $actor) {
            $milestone = ProjectMilestone::create([
                'project_id' => $project->id,
                'parent_milestone_id' => $data['parent_milestone_id'] ?? null,
                'wbs_code' => trim($data['wbs_code']),
                'milestone_title' => $data['milestone_title'],
                'milestone_title_bn' => $data['milestone_title_bn'] ?? null,
                'item_type' => $data['item_type'] ?? ProjectMilestone::TYPE_MILESTONE,
                'deliverables' => $data['deliverables'] ?? null,
                'responsible_organization' => $data['responsible_organization'] ?? $project->implementing_organization,
                'assigned_officer_name' => $data['assigned_officer_name'] ?? null,
                'assigned_officer_user_id' => $data['assigned_officer_user_id'] ?? null,
                'planned_start_date' => $data['planned_start_date'] ?? null,
                'planned_end_date' => $data['planned_end_date'] ?? null,
                'weight_percentage' => $data['weight_percentage'] ?? 10.00,
                'target_percentage' => $data['target_percentage'] ?? 100.00,
                'completion_percentage' => 0.00,
                'approval_status' => ProjectMilestone::APPROVAL_PENDING,
                'updated_by' => $actor->id,
            ]);

            $this->recalculatePhysicalProgress($project);

            AuditLog::log(
                'PROJECT_MILESTONE_CREATED',
                ProjectMilestone::class,
                $milestone->id,
                null,
                ['project_id' => $project->id, 'wbs_code' => $milestone->wbs_code],
                $actor,
                $project->administrative_unit_id
            );

            $this->clearDashboardCache();

            return $milestone;
        });
    }

    public function updateMilestoneProgress(ProjectMilestone $milestone, array $data, User $actor): ProjectMilestone
    {
        $project = $milestone->project;
        $this->assertNotClosed($project);

        if ($milestone->approval_status === ProjectMilestone::APPROVAL_APPROVED_PIO) {
            throw ValidationException::withMessages([
                'approval_status' => 'Certified PIO-approved milestones cannot be directly modified.',
            ]);
        }

        return DB::transaction(function () use ($milestone, $project, $data, $actor) {
            $oldPct = (float) $milestone->completion_percentage;
            $completion = (float) $data['completion_percentage'];

            $milestone->update([
                'completion_percentage' => $completion,
                'actual_start_date' => $data['actual_start_date'] ?? $milestone->actual_start_date ?? now()->toDateString(),
                'actual_end_date' => $completion >= 100.0
                    ? ($data['actual_end_date'] ?? $milestone->actual_end_date ?? now()->toDateString())
                    : ($data['actual_end_date'] ?? $milestone->actual_end_date),
                'delay_reason' => $data['delay_reason'] ?? $milestone->delay_reason,
                'approval_status' => $completion > 0 ? ProjectMilestone::APPROVAL_SUBMITTED : ProjectMilestone::APPROVAL_PENDING,
                'updated_by' => $actor->id,
            ]);

            if (!empty($data['delay_reason'])) {
                $project->update([
                    'is_delayed' => true,
                    'delay_reason' => $data['delay_reason'],
                    'updated_by' => $actor->id,
                ]);
            }

            $this->recalculatePhysicalProgress($project);

            AuditLog::log(
                'PROJECT_MILESTONE_PROGRESS_UPDATED',
                ProjectMilestone::class,
                $milestone->id,
                ['completion_percentage' => $oldPct],
                ['completion_percentage' => $completion],
                $actor,
                $project->administrative_unit_id
            );

            $this->clearDashboardCache();

            return $milestone->fresh(['certifier']);
        });
    }

    public function certifyMilestone(ProjectMilestone $milestone, string $approvalStatus, ?string $notes, User $actor): ProjectMilestone
    {
        $project = $milestone->project;
        $this->assertNotClosed($project);

        if ($milestone->updated_by && $milestone->updated_by === $actor->id) {
            throw ValidationException::withMessages([
                'approval_status' => '4-Eye Separation of Duties: Officer who updated milestone progress cannot certify it.',
            ]);
        }

        return DB::transaction(function () use ($milestone, $project, $approvalStatus, $notes, $actor) {
            $oldStatus = $milestone->approval_status;
            $milestone->update([
                'approval_status' => $approvalStatus,
                'certification_notes' => $notes,
                'certified_by' => $actor->id,
                'certified_at' => now(),
            ]);

            $this->recalculatePhysicalProgress($project);

            AuditLog::log(
                'PROJECT_MILESTONE_CERTIFIED',
                ProjectMilestone::class,
                $milestone->id,
                ['approval_status' => $oldStatus],
                ['approval_status' => $approvalStatus, 'notes' => $notes],
                $actor,
                $project->administrative_unit_id
            );

            $this->clearDashboardCache();

            return $milestone->fresh(['certifier']);
        });
    }

    /**
     * Record a financial tracking transaction (FUND_RELEASE, EXPENDITURE, BUDGET_REVISION)
     * with supporting documentation. Does NOT execute unrestricted bank/payment transfers.
     */
    public function recordDisbursement(
        Project $project,
        array $data,
        ?UploadedFile $supportingDoc,
        User $actor
    ): ProjectDisbursement {
        $this->assertNotClosed($project);

        if (!in_array($project->status, [Project::STATUS_APPROVED, Project::STATUS_ONGOING, Project::STATUS_SUSPENDED, Project::STATUS_COMPLETED], true)) {
            throw ValidationException::withMessages([
                'status' => 'Financial transactions can only be recorded after a project is APPROVED.',
            ]);
        }

        $recordType = $data['record_type'] ?? ProjectDisbursement::TYPE_EXPENDITURE;
        $amount = (float) $data['amount_bdt'];

        if (!empty($data['project_milestone_id'])) {
            $belongs = ProjectMilestone::where('project_id', $project->id)
                ->where('id', $data['project_milestone_id'])
                ->exists();
            if (!$belongs) {
                throw ValidationException::withMessages([
                    'project_milestone_id' => 'Referenced milestone does not belong to this project.',
                ]);
            }
        }

        $effectiveBudget = $project->effective_budget_bdt;

        if ($recordType === ProjectDisbursement::TYPE_EXPENDITURE) {
            $committedExp = (float) $project->disbursements()
                ->where('record_type', ProjectDisbursement::TYPE_EXPENDITURE)
                ->where('payment_status', '!=', ProjectDisbursement::STATUS_REJECTED)
                ->sum('amount_bdt');
            if (($committedExp + $amount) > $effectiveBudget) {
                throw ValidationException::withMessages([
                    'amount_bdt' => 'Recorded expenditure exceeds effective approved/revised project budget.',
                ]);
            }
        }

        if ($recordType === ProjectDisbursement::TYPE_FUND_RELEASE) {
            $committedRel = (float) $project->disbursements()
                ->where('record_type', ProjectDisbursement::TYPE_FUND_RELEASE)
                ->where('payment_status', '!=', ProjectDisbursement::STATUS_REJECTED)
                ->sum('amount_bdt');
            if (($committedRel + $amount) > $effectiveBudget) {
                throw ValidationException::withMessages([
                    'amount_bdt' => 'Total released funds cannot exceed effective approved/revised project budget.',
                ]);
            }
        }

        if ($recordType === ProjectDisbursement::TYPE_BUDGET_REVISION) {
            if ($amount < (float) $project->expenditure_bdt || $amount < (float) $project->released_funds_bdt) {
                throw ValidationException::withMessages([
                    'amount_bdt' => 'Revised budget cannot be lower than already released funds or recorded expenditure.',
                ]);
            }
        }

        $docMeta = [
            'supporting_doc_path' => null,
            'supporting_doc_name' => null,
            'supporting_doc_mime' => null,
            'supporting_doc_size' => null,
            'supporting_doc_sha256' => null,
        ];

        if ($supportingDoc) {
            $stored = $this->storePrivateAttachment($supportingDoc, "private/project-financials/{$project->id}", 'pdf');
            $docMeta = [
                'supporting_doc_path' => $stored['path'],
                'supporting_doc_name' => $stored['original_name'],
                'supporting_doc_mime' => $stored['mime'],
                'supporting_doc_size' => $stored['size'],
                'supporting_doc_sha256' => $stored['sha256'],
            ];
        }

        try {
            return DB::transaction(function () use ($project, $data, $recordType, $amount, $docMeta, $actor) {
                $disbursement = ProjectDisbursement::create(array_merge([
                    'project_id' => $project->id,
                    'project_milestone_id' => $data['project_milestone_id'] ?? null,
                    'record_type' => $recordType,
                    'voucher_no' => !empty($data['voucher_no']) ? strtoupper(trim($data['voucher_no'])) : $this->generateRefNo('FIN'),
                    'fiscal_year' => $data['fiscal_year'] ?? null,
                    'amount_bdt' => $amount,
                    'payment_status' => ProjectDisbursement::STATUS_PENDING,
                    'description' => $data['description'] ?? null,
                    'disbursed_at' => $data['disbursed_at'] ?? now(),
                    'created_by' => $actor->id,
                ], $docMeta));

                AuditLog::log(
                    'PROJECT_FINANCIAL_RECORDED',
                    ProjectDisbursement::class,
                    $disbursement->id,
                    null,
                    [
                        'project_id' => $project->id,
                        'record_type' => $recordType,
                        'voucher_no' => $disbursement->voucher_no,
                        'amount_bdt' => $amount,
                    ],
                    $actor,
                    $project->administrative_unit_id
                );

                $this->clearDashboardCache();

                return $disbursement->fresh(['milestone', 'creator']);
            });
        } catch (\Throwable $e) {
            if ($docMeta['supporting_doc_path']) {
                Storage::disk('local')->delete($docMeta['supporting_doc_path']);
            }
            throw $e;
        }
    }

    /**
     * Authorize/approve/reconcile/reject a financial tracking entry (4-Eye SoD enforced).
     */
    public function approveDisbursement(
        ProjectDisbursement $disbursement,
        string $targetStatus,
        ?string $notes,
        User $actor
    ): ProjectDisbursement {
        $project = $disbursement->project;
        $this->assertNotClosed($project);

        if ($disbursement->created_by === $actor->id) {
            throw ValidationException::withMessages([
                'payment_status' => '4-Eye Separation of Duties: Financial preparer cannot authorize their own voucher.',
            ]);
        }

        $allowedTransitions = [
            ProjectDisbursement::STATUS_PENDING => [
                ProjectDisbursement::STATUS_APPROVED,
                ProjectDisbursement::STATUS_RELEASED,
                ProjectDisbursement::STATUS_RECONCILED,
                ProjectDisbursement::STATUS_REJECTED,
            ],
            ProjectDisbursement::STATUS_APPROVED => [
                ProjectDisbursement::STATUS_RELEASED,
                ProjectDisbursement::STATUS_RECONCILED,
            ],
            ProjectDisbursement::STATUS_RELEASED => [
                ProjectDisbursement::STATUS_RECONCILED,
            ],
            ProjectDisbursement::STATUS_RECONCILED => [],
            ProjectDisbursement::STATUS_REJECTED => [],
        ];

        $allowed = $allowedTransitions[$disbursement->payment_status] ?? [];
        if (!in_array($targetStatus, $allowed, true)) {
            throw ValidationException::withMessages([
                'payment_status' => "Invalid financial voucher status transition from {$disbursement->payment_status} to {$targetStatus}.",
            ]);
        }

        return DB::transaction(function () use ($disbursement, $project, $targetStatus, $notes, $actor) {
            $lockedProject = Project::whereKey($project->id)->lockForUpdate()->firstOrFail();
            $lockedDisbursement = ProjectDisbursement::whereKey($disbursement->id)->lockForUpdate()->firstOrFail();

            if (
                $lockedDisbursement->record_type === ProjectDisbursement::TYPE_BUDGET_REVISION &&
                $targetStatus !== ProjectDisbursement::STATUS_REJECTED &&
                ((float) $lockedDisbursement->amount_bdt < (float) $lockedProject->expenditure_bdt ||
                    (float) $lockedDisbursement->amount_bdt < (float) $lockedProject->released_funds_bdt)
            ) {
                throw ValidationException::withMessages([
                    'payment_status' => 'Revised budget cannot be lower than already released funds or recorded expenditure.',
                ]);
            }

            $oldStatus = $lockedDisbursement->payment_status;

            $lockedDisbursement->update([
                'payment_status' => $targetStatus,
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'approval_notes' => $notes,
            ]);

            $this->recalculateFinancialTotals($lockedProject);

            AuditLog::log(
                'PROJECT_FINANCIAL_STATUS_UPDATED',
                ProjectDisbursement::class,
                $lockedDisbursement->id,
                ['payment_status' => $oldStatus],
                ['payment_status' => $targetStatus, 'notes' => $notes],
                $actor,
                $lockedProject->administrative_unit_id
            );

            $this->clearDashboardCache();

            return $lockedDisbursement->fresh(['milestone', 'creator', 'approver']);
        });
    }

    public function downloadFinancialDocument(ProjectDisbursement $disbursement): StreamedResponse
    {
        if (!$disbursement->supporting_doc_path || !Storage::disk('local')->exists($disbursement->supporting_doc_path)) {
            abort(404, 'Supporting financial document not found.');
        }

        return Storage::disk('local')->download(
            $disbursement->supporting_doc_path,
            $disbursement->supporting_doc_name ?: 'financial-document'
        );
    }

    public function createMonitoringReport(
        Project $project,
        array $data,
        ?UploadedFile $attachment,
        User $actor
    ): ProjectMonitoringReport {
        $this->assertNotClosed($project);

        if (!empty($data['administrative_unit_id'])) {
            $this->assertUnitInUserScope($actor, $data['administrative_unit_id'], 'monitoring report unit');
        }

        $attMeta = [
            'attachment_path' => null,
            'attachment_original_name' => null,
            'attachment_mime' => null,
            'attachment_size_bytes' => null,
            'attachment_sha256' => null,
        ];

        if ($attachment) {
            $stored = $this->storePrivateAttachment($attachment, "private/project-monitoring/{$project->id}", 'bin');
            $attMeta = [
                'attachment_path' => $stored['path'],
                'attachment_original_name' => $stored['original_name'],
                'attachment_mime' => $stored['mime'],
                'attachment_size_bytes' => $stored['size'],
                'attachment_sha256' => $stored['sha256'],
            ];
        }

        try {
            return DB::transaction(function () use ($project, $data, $attMeta, $actor) {
                $delayObserved = (bool) ($data['delay_observed'] ?? false);
                $delayDays = $delayObserved ? (int) ($data['delay_days'] ?? 0) : 0;

                $report = ProjectMonitoringReport::create(array_merge([
                    'project_id' => $project->id,
                    'administrative_unit_id' => $data['administrative_unit_id'] ?? $project->administrative_unit_id,
                    'report_no' => $this->generateRefNo('MON'),
                    'visit_date' => $data['visit_date'],
                    'inspector_name' => $data['inspector_name'] ?? $actor->full_name_en,
                    'inspector_designation' => $data['inspector_designation'] ?? $actor->designation,
                    'inspector_user_id' => $actor->id,
                    'observed_physical_pct' => $data['observed_physical_pct'],
                    'observed_financial_pct' => $data['observed_financial_pct'] ?? $project->financial_progress_pct,
                    'delay_observed' => $delayObserved,
                    'delay_days' => $delayDays,
                    'delay_reason' => $data['delay_reason'] ?? null,
                    'findings' => $data['findings'],
                    'recommendations' => $data['recommendations'] ?? null,
                    'latitude' => $data['latitude'] ?? null,
                    'longitude' => $data['longitude'] ?? null,
                ], $attMeta));

                if ($delayObserved) {
                    $project->update([
                        'is_delayed' => true,
                        'delay_days' => max((int) $project->delay_days, $delayDays),
                        'delay_reason' => $data['delay_reason'] ?? $project->delay_reason,
                        'updated_by' => $actor->id,
                    ]);
                }

                AuditLog::log(
                    'PROJECT_MONITORING_REPORT_CREATED',
                    ProjectMonitoringReport::class,
                    $report->id,
                    null,
                    [
                        'project_id' => $project->id,
                        'report_no' => $report->report_no,
                        'delay_observed' => $delayObserved,
                    ],
                    $actor,
                    $project->administrative_unit_id
                );

                $this->clearDashboardCache();

                return $report->fresh(['administrativeUnit', 'inspector']);
            });
        } catch (\Throwable $e) {
            if ($attMeta['attachment_path']) {
                Storage::disk('local')->delete($attMeta['attachment_path']);
            }
            throw $e;
        }
    }

    public function downloadMonitoringAttachment(ProjectMonitoringReport $report): StreamedResponse
    {
        if (!$report->attachment_path || !Storage::disk('local')->exists($report->attachment_path)) {
            abort(404, 'Monitoring attachment not found.');
        }

        return Storage::disk('local')->download(
            $report->attachment_path,
            $report->attachment_original_name ?: 'monitoring-attachment'
        );
    }

    public function createRisk(Project $project, array $data, User $actor): ProjectRisk
    {
        $this->assertNotClosed($project);

        return DB::transaction(function () use ($project, $data, $actor) {
            $risk = ProjectRisk::create([
                'project_id' => $project->id,
                'risk_code' => !empty($data['risk_code'])
                    ? strtoupper(trim($data['risk_code']))
                    : 'RSK-' . strtoupper(Str::random(5)),
                'record_kind' => $data['record_kind'] ?? ProjectRisk::KIND_RISK,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'severity' => $data['severity'] ?? ProjectRisk::SEVERITY_MEDIUM,
                'likelihood' => $data['likelihood'] ?? 'MEDIUM',
                'mitigation_plan' => $data['mitigation_plan'] ?? null,
                'owner_name' => $data['owner_name'] ?? null,
                'status' => ProjectRisk::STATUS_OPEN,
                'identified_date' => $data['identified_date'] ?? now()->toDateString(),
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            AuditLog::log(
                'PROJECT_RISK_CREATED',
                ProjectRisk::class,
                $risk->id,
                null,
                ['project_id' => $project->id, 'risk_code' => $risk->risk_code, 'severity' => $risk->severity],
                $actor,
                $project->administrative_unit_id
            );

            $this->clearDashboardCache();

            return $risk;
        });
    }

    public function updateRisk(ProjectRisk $risk, array $data, User $actor): ProjectRisk
    {
        $project = $risk->project;
        $this->assertNotClosed($project);

        $oldStatus = $risk->status;
        $updates = collect($data)->only([
            'title',
            'description',
            'severity',
            'likelihood',
            'mitigation_plan',
            'owner_name',
            'status',
            'resolved_date',
        ])->toArray();

        if (($updates['status'] ?? null) === ProjectRisk::STATUS_RESOLVED && empty($updates['resolved_date'])) {
            $updates['resolved_date'] = now()->toDateString();
        }
        $updates['updated_by'] = $actor->id;

        $risk->update($updates);

        AuditLog::log(
            'PROJECT_RISK_UPDATED',
            ProjectRisk::class,
            $risk->id,
            ['status' => $oldStatus],
            ['status' => $risk->status],
            $actor,
            $project->administrative_unit_id
        );

        $this->clearDashboardCache();

        return $risk->fresh();
    }

    /**
     * Dashboard KPIs, financial rollups, category breakdown, and progress by location.
     */
    public function getDashboardSummary(User $user, array $filters = []): array
    {
        $scope = $user->getAdministrativeScope();
        $scopeKey = $user->isNationalScope() ? 'NATIONAL' : md5($scope['ancestry_path'] ?? '/');
        $version = (int) Cache::get('project:dashboard:version', 1);
        $filterKey = md5(json_encode($filters));
        $cacheKey = "project:dashboard:v{$version}:{$scopeKey}:{$filterKey}";

        return Cache::remember($cacheKey, 60, function () use ($user, $filters) {
            $query = Project::inJurisdiction($user)->with(['category', 'administrativeUnit']);

            if (!empty($filters['category_id'])) {
                $query->where('category_id', $filters['category_id']);
            }
            if (!empty($filters['disaster_event_id'])) {
                $query->where('disaster_event_id', $filters['disaster_event_id']);
            }
            if (!empty($filters['status'])) {
                $query->where('status', strtoupper((string) $filters['status']));
            }

            $projects = $query->get();
            $projectIds = $projects->pluck('id');

            $totalApprovedBudget = (float) $projects->sum('approved_budget_bdt');
            $totalEffectiveBudget = (float) $projects->sum(fn (Project $p) => $p->effective_budget_bdt);
            $totalReleasedFunds = (float) $projects->sum('released_funds_bdt');
            $totalExpenditure = (float) $projects->sum('expenditure_bdt');
            $overallFinancialPct = $totalEffectiveBudget > 0
                ? round(min(100.0, ($totalExpenditure / $totalEffectiveBudget) * 100.0), 2)
                : 0.0;
            $avgPhysicalPct = $projects->count() > 0
                ? round((float) $projects->avg('physical_progress_pct'), 2)
                : 0.0;

            $byStatus = $projects->groupBy('status')->map->count()->toArray();

            $byCategory = $projects->groupBy(fn (Project $p) => $p->category?->code ?? 'OTHER')
                ->map(function ($group, $code) {
                    $first = $group->first();
                    return [
                        'code' => $code,
                        'name_en' => $first->category?->name_en ?? $code,
                        'name_bn' => $first->category?->name_bn ?? $code,
                        'color_hex' => $first->category?->color_hex ?? '#6c757d',
                        'count' => $group->count(),
                        'approved_budget_bdt' => round((float) $group->sum('approved_budget_bdt'), 2),
                        'expenditure_bdt' => round((float) $group->sum('expenditure_bdt'), 2),
                    ];
                })->values()->toArray();

            $byLocation = $projects->groupBy('administrative_unit_id')
                ->map(function ($group) {
                    $unit = $group->first()->administrativeUnit;
                    $eff = (float) $group->sum(fn (Project $p) => $p->effective_budget_bdt);
                    $exp = (float) $group->sum('expenditure_bdt');
                    return [
                        'administrative_unit_id' => $unit?->id,
                        'name_en' => $unit?->name_en ?? 'Unknown',
                        'name_bn' => $unit?->name_bn ?? 'অজানা',
                        'tier_level' => $unit?->tier_level ?? '',
                        'project_count' => $group->count(),
                        'delayed_count' => $group->where('is_delayed', true)->count(),
                        'avg_physical_progress_pct' => round((float) $group->avg('physical_progress_pct'), 2),
                        'financial_progress_pct' => $eff > 0 ? round(min(100.0, ($exp / $eff) * 100.0), 2) : 0.0,
                        'approved_budget_bdt' => round((float) $group->sum('approved_budget_bdt'), 2),
                        'expenditure_bdt' => round($exp, 2),
                    ];
                })->values()->toArray();

            $openRisksCount = $projectIds->isNotEmpty()
                ? ProjectRisk::whereIn('project_id', $projectIds)
                    ->whereIn('status', [ProjectRisk::STATUS_OPEN, ProjectRisk::STATUS_MITIGATING])
                    ->count()
                : 0;

            $monitoringReportsCount = $projectIds->isNotEmpty()
                ? ProjectMonitoringReport::whereIn('project_id', $projectIds)->count()
                : 0;

            return [
                'summary' => [
                    'total_projects' => $projects->count(),
                    'active_projects' => $projects->whereIn('status', Project::ACTIVE_STATUSES)->count(),
                    'completed_projects' => $projects->whereIn('status', [Project::STATUS_COMPLETED, Project::STATUS_CLOSED])->count(),
                    'delayed_projects' => $projects->where('is_delayed', true)->count(),
                    'proposed_or_review' => $projects->whereIn('status', [Project::STATUS_PROPOSED, Project::STATUS_UNDER_REVIEW])->count(),
                    'total_approved_budget_bdt' => round($totalApprovedBudget, 2),
                    'total_effective_budget_bdt' => round($totalEffectiveBudget, 2),
                    'total_released_funds_bdt' => round($totalReleasedFunds, 2),
                    'total_expenditure_bdt' => round($totalExpenditure, 2),
                    'remaining_balance_bdt' => round(max(0.0, $totalEffectiveBudget - $totalExpenditure), 2),
                    'financial_progress_pct' => $overallFinancialPct,
                    'avg_physical_progress_pct' => $avgPhysicalPct,
                    'open_risks_count' => $openRisksCount,
                    'monitoring_reports_count' => $monitoringReportsCount,
                ],
                'by_status' => $byStatus,
                'by_category' => $byCategory,
                'progress_by_location' => $byLocation,
            ];
        });
    }

    /**
     * Generate structured analytical reports: summary, financial, physical, milestones, monitoring.
     */
    public function getReports(User $user, string $reportType = 'summary', array $filters = []): array
    {
        $query = Project::inJurisdiction($user)
            ->with(['category', 'administrativeUnit', 'disasterEvent', 'milestones', 'disbursements', 'monitoringReports', 'risks']);

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', strtoupper((string) $filters['status']));
        }
        if (isset($filters['is_delayed']) && $filters['is_delayed'] !== '') {
            $query->where('is_delayed', filter_var($filters['is_delayed'], FILTER_VALIDATE_BOOLEAN));
        }

        $projects = $query->orderByDesc('created_at')->get();

        $rows = match ($reportType) {
            'financial' => $projects->map(fn (Project $p) => [
                'id' => $p->id,
                'project_code' => $p->project_code,
                'title_en' => $p->title_en,
                'title_bn' => $p->title_bn,
                'funding_source' => $p->funding_source,
                'approved_budget_bdt' => (float) $p->approved_budget_bdt,
                'revised_budget_bdt' => $p->revised_budget_bdt !== null ? (float) $p->revised_budget_bdt : null,
                'effective_budget_bdt' => $p->effective_budget_bdt,
                'released_funds_bdt' => (float) $p->released_funds_bdt,
                'expenditure_bdt' => (float) $p->expenditure_bdt,
                'remaining_balance_bdt' => $p->remaining_balance_bdt,
                'financial_progress_pct' => (float) $p->financial_progress_pct,
                'voucher_count' => $p->disbursements->count(),
            ])->values()->toArray(),
            'physical' => $projects->map(fn (Project $p) => [
                'id' => $p->id,
                'project_code' => $p->project_code,
                'title_en' => $p->title_en,
                'title_bn' => $p->title_bn,
                'status' => $p->status,
                'administrative_unit' => $p->administrativeUnit?->name_en,
                'physical_progress_pct' => (float) $p->physical_progress_pct,
                'start_date' => $p->start_date?->toDateString(),
                'expected_end_date' => $p->expected_end_date?->toDateString(),
                'is_delayed' => (bool) $p->is_delayed,
                'delay_days' => (int) $p->delay_days,
                'delay_reason' => $p->delay_reason,
            ])->values()->toArray(),
            'milestones' => $projects->flatMap(function (Project $p) {
                return $p->milestones->map(fn (ProjectMilestone $m) => [
                    'project_id' => $p->id,
                    'project_code' => $p->project_code,
                    'project_title_en' => $p->title_en,
                    'wbs_code' => $m->wbs_code,
                    'milestone_title' => $m->milestone_title,
                    'item_type' => $m->item_type,
                    'weight_percentage' => (float) $m->weight_percentage,
                    'completion_percentage' => (float) $m->completion_percentage,
                    'approval_status' => $m->approval_status,
                    'planned_end_date' => $m->planned_end_date?->toDateString(),
                    'responsible_organization' => $m->responsible_organization,
                ]);
            })->values()->toArray(),
            'monitoring' => $projects->flatMap(function (Project $p) use ($user) {
                $isExternal = $user->hasRole('ROLE_EXTERNAL_PARTNER');
                return $p->monitoringReports->map(fn (ProjectMonitoringReport $r) => [
                    'project_id' => $p->id,
                    'project_code' => $p->project_code,
                    'project_title_en' => $p->title_en,
                    'report_no' => $r->report_no,
                    'visit_date' => $r->visit_date?->toDateString(),
                    'inspector_name' => $isExternal ? 'MoDMR Inspector' : $r->inspector_name,
                    'observed_physical_pct' => (float) $r->observed_physical_pct,
                    'observed_financial_pct' => $r->observed_financial_pct !== null ? (float) $r->observed_financial_pct : null,
                    'delay_observed' => (bool) $r->delay_observed,
                    'delay_days' => (int) $r->delay_days,
                    'findings' => $r->findings,
                ]);
            })->values()->toArray(),
            default => $projects->map(fn (Project $p) => [
                'id' => $p->id,
                'project_code' => $p->project_code,
                'reference_no' => $p->reference_no,
                'title_en' => $p->title_en,
                'title_bn' => $p->title_bn,
                'category_en' => $p->category?->name_en,
                'administrative_unit' => $p->administrativeUnit?->name_en,
                'status' => $p->status,
                'approved_budget_bdt' => (float) $p->approved_budget_bdt,
                'expenditure_bdt' => (float) $p->expenditure_bdt,
                'physical_progress_pct' => (float) $p->physical_progress_pct,
                'financial_progress_pct' => (float) $p->financial_progress_pct,
                'is_delayed' => (bool) $p->is_delayed,
                'open_risks' => $p->risks->whereIn('status', [ProjectRisk::STATUS_OPEN, ProjectRisk::STATUS_MITIGATING])->count(),
            ])->values()->toArray(),
        };

        return [
            'report_type' => $reportType,
            'generated_at' => now()->toIso8601String(),
            'total_rows' => count($rows),
            'rows' => $rows,
        ];
    }

    /**
     * Export project portfolio CSV with UTF-8 BOM for clean Bangla rendering in Excel.
     */
    public function exportCsv(User $user, array $filters = []): StreamedResponse
    {
        $query = Project::inJurisdiction($user)->with(['category', 'administrativeUnit', 'disasterEvent']);

        if (!empty($filters['status'])) {
            $query->where('status', strtoupper((string) $filters['status']));
        }
        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        $projects = $query->orderByDesc('created_at')->get();

        AuditLog::log(
            'PROJECT_PORTFOLIO_EXPORTED',
            Project::class,
            null,
            null,
            ['count' => $projects->count(), 'filters' => $filters],
            $user
        );

        $filename = 'idms-projects-export-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($projects) {
            $handle = fopen('php://output', 'w');
            // UTF-8 BOM for Bangla Unicode support in Excel
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Project Code',
                'Reference No',
                'Title (EN)',
                'Title (BN)',
                'Category',
                'Jurisdiction',
                'Status',
                'Implementing Organization',
                'Funding Source',
                'Approved Budget (BDT)',
                'Effective Budget (BDT)',
                'Released Funds (BDT)',
                'Expenditure (BDT)',
                'Remaining Balance (BDT)',
                'Physical Progress (%)',
                'Financial Progress (%)',
                'Start Date',
                'Expected End Date',
                'Delayed',
            ]);

            foreach ($projects as $p) {
                fputcsv($handle, [
                    $this->sanitizeCsvCell($p->project_code),
                    $this->sanitizeCsvCell($p->reference_no ?? ''),
                    $this->sanitizeCsvCell($p->title_en),
                    $this->sanitizeCsvCell($p->title_bn),
                    $this->sanitizeCsvCell($p->category?->name_en ?? ''),
                    $this->sanitizeCsvCell($p->administrativeUnit?->name_en ?? ''),
                    $this->sanitizeCsvCell($p->status),
                    $this->sanitizeCsvCell($p->implementing_organization),
                    $this->sanitizeCsvCell($p->funding_source),
                    number_format((float) $p->approved_budget_bdt, 2, '.', ''),
                    number_format($p->effective_budget_bdt, 2, '.', ''),
                    number_format((float) $p->released_funds_bdt, 2, '.', ''),
                    number_format((float) $p->expenditure_bdt, 2, '.', ''),
                    number_format($p->remaining_balance_bdt, 2, '.', ''),
                    number_format((float) $p->physical_progress_pct, 2, '.', ''),
                    number_format((float) $p->financial_progress_pct, 2, '.', ''),
                    $p->start_date?->toDateString() ?? '',
                    $p->expected_end_date?->toDateString() ?? '',
                    $p->is_delayed ? 'YES' : 'NO',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function sanitizeCsvCell(?string $value): string
    {
        $str = (string) ($value ?? '');
        if ($str !== '' && preg_match('/^[=+\-@\t\r]/', $str)) {
            return "'" . $str;
        }
        return $str;
    }

    protected function storePrivateAttachment(UploadedFile $file, string $directory, string $defaultExt = 'bin'): array
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: $defaultExt);
        $storedFilename = (string) Str::uuid() . '.' . $ext;
        $path = $file->storeAs($directory, $storedFilename, 'local');

        return [
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType() ?: $file->getClientMimeType(),
            'size' => $file->getSize(),
            'sha256' => hash_file('sha256', $file->getRealPath()),
        ];
    }

    protected function syncLocations(Project $project, array $locations): void
    {
        $project->locations()->delete();

        if (empty($locations)) {
            return;
        }

        foreach ($locations as $loc) {
            if (empty($loc['administrative_unit_id'])) {
                continue;
            }
            $location = ProjectLocation::create([
                'project_id' => $project->id,
                'administrative_unit_id' => $loc['administrative_unit_id'],
                'ward_no' => $loc['ward_no'] ?? null,
                'site_name_en' => $loc['site_name_en'] ?? null,
                'site_name_bn' => $loc['site_name_bn'] ?? null,
                'latitude' => $loc['latitude'] ?? null,
                'longitude' => $loc['longitude'] ?? null,
                'boundary_geojson' => !empty($loc['boundary_geojson'])
                    ? $this->spatialService->normalizeToPolygonGeometry($loc['boundary_geojson'])
                    : null,
                'notes' => $loc['notes'] ?? null,
            ]);
            $this->spatialService->syncLocationGeometry($location);
        }
    }

    protected function recalculatePhysicalProgress(Project $project): void
    {
        $milestones = $project->milestones()->get();
        $pct = $this->computeWeightedPhysicalProgress($milestones);
        $project->update(['physical_progress_pct' => $pct]);
    }

    protected function recalculateFinancialTotals(Project $project): void
    {
        $records = $project->disbursements()
            ->whereIn('payment_status', [
                ProjectDisbursement::STATUS_APPROVED,
                ProjectDisbursement::STATUS_RELEASED,
                ProjectDisbursement::STATUS_RECONCILED,
            ])
            ->get();

        $approvedRevisions = $records->where('record_type', ProjectDisbursement::TYPE_BUDGET_REVISION);
        $latestRevision = $approvedRevisions->sortByDesc('approved_at')->first();
        $revisedBudget = $latestRevision ? (float) $latestRevision->amount_bdt : ($project->revised_budget_bdt !== null ? (float) $project->revised_budget_bdt : null);

        $released = (float) $records
            ->where('record_type', ProjectDisbursement::TYPE_FUND_RELEASE)
            ->sum('amount_bdt');

        $expenditure = (float) $records
            ->where('record_type', ProjectDisbursement::TYPE_EXPENDITURE)
            ->sum('amount_bdt');

        $summary = $this->calculateFinancialSummary(
            (float) $project->approved_budget_bdt,
            $revisedBudget,
            $released,
            $expenditure
        );

        $project->update([
            'revised_budget_bdt' => $revisedBudget,
            'released_funds_bdt' => round($released, 2),
            'expenditure_bdt' => round($expenditure, 2),
            'financial_progress_pct' => $summary['financial_progress_pct'],
        ]);
    }

    protected function assertNotClosed(Project $project): void
    {
        if ($project->status === Project::STATUS_CLOSED) {
            throw ValidationException::withMessages([
                'status' => 'Closed projects are immutable and cannot be modified.',
            ]);
        }
    }

    protected function assertUnitInUserScope(User $user, string $unitId, string $context): void
    {
        $unit = AdministrativeUnit::findOrFail($unitId);
        if (!$this->scopeService->canAccessScope($user, $unit->ancestry_path)) {
            throw new AuthorizationException("Target {$context} is outside your administrative jurisdiction.");
        }
    }

    protected function generateRefNo(string $prefix): string
    {
        return $prefix . '-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));
    }

    public function clearDashboardCache(): void
    {
        $version = (int) Cache::get('project:dashboard:version', 1);
        Cache::put('project:dashboard:version', $version + 1, 86400);
    }
}
