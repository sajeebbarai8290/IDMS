<?php

namespace App\Modules\Workflow\Services;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Core\Services\AdministrativeScopeService;
use App\Modules\Beneficiary\Models\Household;
use App\Modules\DamageAssessment\Models\DamageAssessment;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectDisbursement;
use App\Modules\Relief\Models\ReliefAllocation;
use App\Modules\Relief\Models\ReliefRequisition;
use App\Modules\Resource\Models\ResourceAllocation;
use App\Modules\Volunteer\Models\Volunteer;
use App\Modules\Warehouse\Models\WarehouseTransfer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Integrates Module 11 Workflow Engine with IDMS domain modules cleanly without
 * duplicating business tables or breaking module encapsulation.
 */
class WorkflowEntityIntegrationService
{
    public function __construct(
        protected AdministrativeScopeService $scopeService
    ) {}

    /**
     * Resolve and validate a target entity for workflow submission.
     *
     * @return array{
     *   module_code: string,
     *   entity_type: string,
     *   entity_id: string,
     *   entity_reference: string,
     *   administrative_unit_id: string,
     *   title_en: string,
     *   title_bn: string,
     *   metadata: array<string, mixed>
     * }
     */
    public function resolveTargetEntity(
        string $entityType,
        string $entityId,
        User $actor,
        ?string $fallbackAdminUnitId = null,
        ?string $customTitleEn = null,
        ?string $customTitleBn = null,
        ?string $customReference = null
    ): array {
        $normalizedType = strtoupper(trim($entityType));

        if ($normalizedType === 'GENERAL_REQUEST') {
            $unitId = $fallbackAdminUnitId ?: $actor->administrative_unit_id;
            if (!$unitId) {
                throw ValidationException::withMessages([
                    'administrative_unit_id' => ['Administrative unit is required for general workflow requests.'],
                ]);
            }
            $unit = AdministrativeUnit::findOrFail($unitId);
            $this->assertUnitInScope($actor, $unit->ancestry_path);

            return [
                'module_code' => 'GENERAL',
                'entity_type' => 'GENERAL_REQUEST',
                'entity_id' => $entityId,
                'entity_reference' => $customReference ?: ('GEN-' . substr($entityId, 0, 8)),
                'administrative_unit_id' => $unit->id,
                'title_en' => $customTitleEn ?: 'General Administrative Approval Request',
                'title_bn' => $customTitleBn ?: 'সাধারণ প্রশাসনিক অনুমোদন আবেদন',
                'metadata' => ['source_module' => 'GENERAL'],
            ];
        }

        $record = $this->findModel($normalizedType, $entityId);
        if (!$record) {
            throw ValidationException::withMessages([
                'entity_id' => ["Target {$normalizedType} record was not found."],
            ]);
        }

        $adminUnitId = $this->extractAdministrativeUnitId($normalizedType, $record, $fallbackAdminUnitId);
        if (!$adminUnitId) {
            throw ValidationException::withMessages([
                'administrative_unit_id' => ['Unable to determine administrative unit for target entity.'],
            ]);
        }

        $unit = AdministrativeUnit::findOrFail($adminUnitId);
        $this->assertUnitInScope($actor, $unit->ancestry_path);

        return $this->buildSnapshot($normalizedType, $record, $unit->id, $customTitleEn, $customTitleBn);
    }

    /**
     * List real scoped records from an integrated module for workflow initiation UI.
     */
    public function searchTargetEntities(string $entityType, User $actor, ?string $search = null, int $limit = 25): array
    {
        $type = strtoupper(trim($entityType));
        $limit = max(1, min(50, $limit));

        $query = match ($type) {
            'HOUSEHOLD' => Household::query()->with('administrativeUnit')->inJurisdiction($actor),
            'RELIEF_REQUISITION' => ReliefRequisition::query()->with('administrativeUnit')->inJurisdiction($actor),
            'RELIEF_ALLOCATION' => ReliefAllocation::query()->with('administrativeUnit')->inJurisdiction($actor),
            'DAMAGE_ASSESSMENT' => DamageAssessment::query()->with('administrativeUnit')->inJurisdiction($actor),
            'PROJECT' => Project::query()->with('administrativeUnit')->inJurisdiction($actor),
            'PROJECT_DISBURSEMENT' => ProjectDisbursement::query()->with('project.administrativeUnit'),
            'RESOURCE_ALLOCATION' => ResourceAllocation::query()->with(['administrativeUnit', 'asset'])->inJurisdiction($actor),
            'WAREHOUSE_TRANSFER' => WarehouseTransfer::query()->with(['sourceWarehouse.administrativeUnit', 'destinationWarehouse.administrativeUnit']),
            'VOLUNTEER' => Volunteer::query()->with('administrativeUnit')->inJurisdiction($actor),
            default => null,
        };

        if (!$query) {
            return [];
        }

        if ($search && trim($search) !== '') {
            $term = '%' . trim($search) . '%';
            $query->where(function ($q) use ($type, $term) {
                match ($type) {
                    'HOUSEHOLD' => $q->where('household_code', 'like', $term)->orWhere('hoh_name_en', 'like', $term),
                    'RELIEF_REQUISITION' => $q->where('requisition_no', 'like', $term)->orWhere('requesting_organization', 'like', $term),
                    'RELIEF_ALLOCATION' => $q->where('allocation_order_no', 'like', $term),
                    'DAMAGE_ASSESSMENT' => $q->where('d_form_code', 'like', $term)->orWhere('assessing_organization', 'like', $term),
                    'PROJECT' => $q->where('project_code', 'like', $term)->orWhere('title_en', 'like', $term),
                    'PROJECT_DISBURSEMENT' => $q->where('reference_no', 'like', $term),
                    'RESOURCE_ALLOCATION' => $q->where('allocation_no', 'like', $term)->orWhere('allocation_location', 'like', $term),
                    'WAREHOUSE_TRANSFER' => $q->where('transfer_no', 'like', $term),
                    'VOLUNTEER' => $q->where('volunteer_code', 'like', $term)->orWhere('full_name_en', 'like', $term),
                    default => null,
                };
            });
        }

        $items = $query->orderByDesc('created_at')->limit($limit)->get();

        $results = [];
        foreach ($items as $item) {
            if ($type === 'PROJECT_DISBURSEMENT') {
                $unit = $item->project?->administrativeUnit;
                if ($unit && !$this->scopeService->canAccessScope($actor, $unit->ancestry_path)) {
                    continue;
                }
            }
            if ($type === 'WAREHOUSE_TRANSFER') {
                $unit = $item->destinationWarehouse?->administrativeUnit ?? $item->sourceWarehouse?->administrativeUnit;
                if ($unit && !$this->scopeService->canAccessScope($actor, $unit->ancestry_path)) {
                    continue;
                }
            }

            $unitId = $this->extractAdministrativeUnitId($type, $item, $actor->administrative_unit_id);
            if (!$unitId) {
                continue;
            }
            $snap = $this->buildSnapshot($type, $item, $unitId);
            $results[] = [
                'entity_id' => $snap['entity_id'],
                'entity_type' => $snap['entity_type'],
                'module_code' => $snap['module_code'],
                'entity_reference' => $snap['entity_reference'],
                'title_en' => $snap['title_en'],
                'title_bn' => $snap['title_bn'],
                'administrative_unit_id' => $snap['administrative_unit_id'],
                'status' => $snap['metadata']['entity_status'] ?? null,
            ];
        }

        return $results;
    }

    /**
     * Fetch live entity summary for workflow detail view.
     */
    public function getLiveEntitySummary(string $entityType, string $entityId): ?array
    {
        $type = strtoupper($entityType);
        if ($type === 'GENERAL_REQUEST') {
            return null;
        }

        $model = $this->findModel($type, $entityId);
        if (!$model) {
            return null;
        }

        return match ($type) {
            'HOUSEHOLD' => [
                'code' => $model->household_code,
                'status' => $model->verification_status,
                'vulnerability_score' => $model->vulnerability_score,
                'route' => "/beneficiary/households/{$model->id}",
            ],
            'RELIEF_REQUISITION' => [
                'code' => $model->requisition_no,
                'status' => $model->status,
                'priority' => $model->priority,
                'estimated_beneficiaries' => $model->estimated_beneficiaries,
                'route' => "/relief/requisitions/{$model->id}",
            ],
            'RELIEF_ALLOCATION' => [
                'code' => $model->allocation_order_no,
                'status' => $model->status,
                'route' => "/relief/allocations/{$model->id}",
            ],
            'DAMAGE_ASSESSMENT' => [
                'code' => $model->d_form_code,
                'status' => $model->verification_status,
                'severity_level' => $model->severity_level,
                'total_estimated_loss_bdt' => $model->total_estimated_loss_bdt,
                'route' => "/damage/assessments/{$model->id}",
            ],
            'PROJECT' => [
                'code' => $model->project_code,
                'status' => $model->status,
                'approved_budget_bdt' => $model->approved_budget_bdt,
                'route' => "/project/schemes/{$model->id}",
            ],
            'PROJECT_DISBURSEMENT' => [
                'code' => $model->reference_no,
                'status' => $model->approval_status ?? $model->transaction_type,
                'amount_bdt' => $model->amount_bdt,
                'route' => "/project/schemes/{$model->project_id}",
            ],
            'RESOURCE_ALLOCATION' => [
                'code' => $model->allocation_no,
                'status' => $model->status,
                'route' => "/resource/allocations",
            ],
            'WAREHOUSE_TRANSFER' => [
                'code' => $model->transfer_no,
                'status' => $model->status,
                'route' => "/warehouse/transfers",
            ],
            'VOLUNTEER' => [
                'code' => $model->volunteer_code,
                'status' => $model->overall_verification_status,
                'route' => "/volunteer/volunteers/{$model->id}",
            ],
            default => null,
        };
    }

    protected function findModel(string $type, string $entityId): ?Model
    {
        return match ($type) {
            'HOUSEHOLD' => Household::find($entityId),
            'RELIEF_REQUISITION' => ReliefRequisition::find($entityId),
            'RELIEF_ALLOCATION' => ReliefAllocation::find($entityId),
            'DAMAGE_ASSESSMENT' => DamageAssessment::find($entityId),
            'PROJECT' => Project::find($entityId),
            'PROJECT_DISBURSEMENT' => ProjectDisbursement::with('project')->find($entityId),
            'RESOURCE_ALLOCATION' => ResourceAllocation::with('asset')->find($entityId),
            'WAREHOUSE_TRANSFER' => WarehouseTransfer::with(['sourceWarehouse', 'destinationWarehouse'])->find($entityId),
            'VOLUNTEER' => Volunteer::find($entityId),
            default => null,
        };
    }

    protected function extractAdministrativeUnitId(string $type, Model $record, ?string $fallback): ?string
    {
        return match ($type) {
            'PROJECT_DISBURSEMENT' => $record->project?->administrative_unit_id ?? $fallback,
            'WAREHOUSE_TRANSFER' => $record->destinationWarehouse?->administrative_unit_id
                ?? $record->sourceWarehouse?->administrative_unit_id
                ?? $fallback,
            default => $record->administrative_unit_id ?? $fallback,
        };
    }

    protected function buildSnapshot(
        string $type,
        Model $record,
        string $adminUnitId,
        ?string $customTitleEn = null,
        ?string $customTitleBn = null
    ): array {
        return match ($type) {
            'HOUSEHOLD' => [
                'module_code' => 'BENEFICIARY',
                'entity_type' => 'HOUSEHOLD',
                'entity_id' => $record->id,
                'entity_reference' => $record->household_code,
                'administrative_unit_id' => $adminUnitId,
                'title_en' => $customTitleEn ?: "Beneficiary Verification: {$record->household_code} ({$record->hoh_name_en})",
                'title_bn' => $customTitleBn ?: "উপকারভোগী যাচাই: {$record->household_code}",
                'metadata' => ['entity_status' => $record->verification_status, 'vulnerability_score' => $record->vulnerability_score],
            ],
            'RELIEF_REQUISITION' => [
                'module_code' => 'RELIEF',
                'entity_type' => 'RELIEF_REQUISITION',
                'entity_id' => $record->id,
                'entity_reference' => $record->requisition_no,
                'administrative_unit_id' => $adminUnitId,
                'title_en' => $customTitleEn ?: "Relief Requisition: {$record->requisition_no} ({$record->requesting_organization})",
                'title_bn' => $customTitleBn ?: "ত্রাণ চাহিদাপত্র: {$record->requisition_no}",
                'metadata' => ['entity_status' => $record->status, 'priority' => $record->priority],
            ],
            'RELIEF_ALLOCATION' => [
                'module_code' => 'RELIEF',
                'entity_type' => 'RELIEF_ALLOCATION',
                'entity_id' => $record->id,
                'entity_reference' => $record->allocation_order_no,
                'administrative_unit_id' => $adminUnitId,
                'title_en' => $customTitleEn ?: "Relief Allocation Order: {$record->allocation_order_no}",
                'title_bn' => $customTitleBn ?: "ত্রাণ বরাদ্দ আদেশ: {$record->allocation_order_no}",
                'metadata' => ['entity_status' => $record->status],
            ],
            'DAMAGE_ASSESSMENT' => [
                'module_code' => 'DAMAGE',
                'entity_type' => 'DAMAGE_ASSESSMENT',
                'entity_id' => $record->id,
                'entity_reference' => $record->d_form_code,
                'administrative_unit_id' => $adminUnitId,
                'title_en' => $customTitleEn ?: "D-Form Damage Assessment: {$record->d_form_code}",
                'title_bn' => $customTitleBn ?: "ডি-ফর্ম ক্ষয়ক্ষতি নিরূপণ: {$record->d_form_code}",
                'metadata' => ['entity_status' => $record->verification_status, 'severity_level' => $record->severity_level],
            ],
            'PROJECT' => [
                'module_code' => 'PROJECT',
                'entity_type' => 'PROJECT',
                'entity_id' => $record->id,
                'entity_reference' => $record->project_code,
                'administrative_unit_id' => $adminUnitId,
                'title_en' => $customTitleEn ?: "Project Approval: {$record->project_code} - {$record->title_en}",
                'title_bn' => $customTitleBn ?: "প্রকল্প অনুমোদন: {$record->project_code}",
                'metadata' => ['entity_status' => $record->status, 'approved_budget_bdt' => $record->approved_budget_bdt],
            ],
            'PROJECT_DISBURSEMENT' => [
                'module_code' => 'PROJECT',
                'entity_type' => 'PROJECT_DISBURSEMENT',
                'entity_id' => $record->id,
                'entity_reference' => $record->reference_no,
                'administrative_unit_id' => $adminUnitId,
                'title_en' => $customTitleEn ?: "Project Financial Voucher: {$record->reference_no}",
                'title_bn' => $customTitleBn ?: "প্রকল্প আর্থিক ভাউচার: {$record->reference_no}",
                'metadata' => ['entity_status' => $record->approval_status ?? $record->transaction_type, 'amount_bdt' => $record->amount_bdt],
            ],
            'RESOURCE_ALLOCATION' => [
                'module_code' => 'RESOURCE',
                'entity_type' => 'RESOURCE_ALLOCATION',
                'entity_id' => $record->id,
                'entity_reference' => $record->allocation_no,
                'administrative_unit_id' => $adminUnitId,
                'title_en' => $customTitleEn ?: "Resource Allocation: {$record->allocation_no} ({$record->allocation_location})",
                'title_bn' => $customTitleBn ?: "সম্পদ বরাদ্দ অনুমোদন: {$record->allocation_no}",
                'metadata' => ['entity_status' => $record->status],
            ],
            'WAREHOUSE_TRANSFER' => [
                'module_code' => 'WAREHOUSE',
                'entity_type' => 'WAREHOUSE_TRANSFER',
                'entity_id' => $record->id,
                'entity_reference' => $record->transfer_no,
                'administrative_unit_id' => $adminUnitId,
                'title_en' => $customTitleEn ?: "Inter-Warehouse Transfer: {$record->transfer_no}",
                'title_bn' => $customTitleBn ?: "আন্তঃগুদাম স্থানান্তর: {$record->transfer_no}",
                'metadata' => ['entity_status' => $record->status],
            ],
            'VOLUNTEER' => [
                'module_code' => 'VOLUNTEER',
                'entity_type' => 'VOLUNTEER',
                'entity_id' => $record->id,
                'entity_reference' => $record->volunteer_code,
                'administrative_unit_id' => $adminUnitId,
                'title_en' => $customTitleEn ?: "Volunteer Verification: {$record->volunteer_code} ({$record->full_name_en})",
                'title_bn' => $customTitleBn ?: "স্বেচ্ছাসেবক যাচাই: {$record->volunteer_code}",
                'metadata' => ['entity_status' => $record->overall_verification_status],
            ],
        };
    }

    protected function assertUnitInScope(User $actor, string $ancestryPath): void
    {
        if (!$this->scopeService->canAccessScope($actor, $ancestryPath)) {
            throw new AuthorizationException('Target entity lies outside your administrative jurisdiction.');
        }
    }
}
