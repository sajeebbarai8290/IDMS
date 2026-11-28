<?php

namespace App\Modules\Document\Services;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Core\Services\AdministrativeScopeService;
use App\Modules\Beneficiary\Models\Household;
use App\Modules\DamageAssessment\Models\DamageAssessment;
use App\Modules\Disaster\Models\DisasterEvent;
use App\Modules\Document\Models\Document;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectDisbursement;
use App\Modules\Relief\Models\DistributionVoucher;
use App\Modules\Relief\Models\ReliefAllocation;
use App\Modules\Relief\Models\ReliefRequisition;
use App\Modules\Workflow\Models\WorkflowInstance;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Integrates Module 14 Document Management with IDMS domain modules cleanly
 * without modifying unrelated modules or duplicating business tables.
 */
class DocumentEntityIntegrationService
{
    public const ENTITY_TYPES = [
        'HOUSEHOLD',
        'DISASTER_EVENT',
        'DAMAGE_ASSESSMENT',
        'RELIEF_REQUISITION',
        'RELIEF_ALLOCATION',
        'DISTRIBUTION_VOUCHER',
        'PROJECT',
        'PROJECT_DISBURSEMENT',
        'WORKFLOW_INSTANCE',
    ];

    public function __construct(
        protected AdministrativeScopeService $scopeService
    ) {}

    /**
     * Resolve and validate a target entity (or GENERAL standalone document).
     *
     * @return array{
     *   module_code: string,
     *   entity_type: ?string,
     *   entity_id: ?string,
     *   entity_reference: ?string,
     *   administrative_unit_id: string
     * }
     */
    public function resolveTarget(
        User $actor,
        ?string $entityType,
        ?string $entityId,
        ?string $explicitModuleCode = null,
        ?string $explicitAdminUnitId = null
    ): array {
        $normalizedType = $entityType ? strtoupper(trim($entityType)) : null;

        if (!$normalizedType || $normalizedType === 'GENERAL' || !$entityId) {
            $unitId = $explicitAdminUnitId ?: $actor->administrative_unit_id;
            if (!$unitId) {
                $unitId = AdministrativeUnit::where('tier_level', 'NATIONAL')->value('id');
            }
            if (!$unitId) {
                throw ValidationException::withMessages([
                    'administrative_unit_id' => ['Administrative unit is required for document registration.'],
                ]);
            }

            $unit = AdministrativeUnit::findOrFail($unitId);
            $this->assertUnitInScope($actor, $unit->ancestry_path);

            $moduleCode = $explicitModuleCode ? strtoupper(trim($explicitModuleCode)) : Document::MODULE_GENERAL;
            if (!in_array($moduleCode, Document::SUPPORTED_MODULES, true)) {
                $moduleCode = Document::MODULE_GENERAL;
            }

            return [
                'module_code' => $moduleCode,
                'entity_type' => null,
                'entity_id' => null,
                'entity_reference' => null,
                'administrative_unit_id' => $unit->id,
            ];
        }

        if (!in_array($normalizedType, self::ENTITY_TYPES, true)) {
            throw ValidationException::withMessages([
                'entity_type' => ["Unsupported related entity type '{$normalizedType}'."],
            ]);
        }

        $record = $this->findModel($normalizedType, $entityId);
        if (!$record) {
            throw ValidationException::withMessages([
                'entity_id' => ["Related {$normalizedType} record was not found."],
            ]);
        }

        $resolvedUnitId = $this->extractAdministrativeUnitId(
            $normalizedType,
            $record,
            $explicitAdminUnitId ?: $actor->administrative_unit_id
        );

        if (!$resolvedUnitId) {
            $resolvedUnitId = AdministrativeUnit::where('tier_level', 'NATIONAL')->value('id');
        }

        $unit = AdministrativeUnit::findOrFail($resolvedUnitId);
        if ($normalizedType === 'DISASTER_EVENT') {
            // Regional users may attach documents to national disaster events or events intersecting their scope
            if (!$record->is_national) {
                $this->assertUnitInScope($actor, $unit->ancestry_path);
            }
        } else {
            $this->assertUnitInScope($actor, $unit->ancestry_path);
        }

        return [
            'module_code' => $this->mapModuleCode($normalizedType),
            'entity_type' => $normalizedType,
            'entity_id' => $record->id,
            'entity_reference' => $this->extractReference($normalizedType, $record),
            'administrative_unit_id' => $unit->id,
        ];
    }

    /**
     * Search scoped live records across integrated IDMS modules for attach-document UI.
     */
    public function searchTargetEntities(string $entityType, User $actor, ?string $search = null, int $limit = 25): array
    {
        $type = strtoupper(trim($entityType));
        $limit = max(1, min(50, $limit));

        $query = match ($type) {
            'HOUSEHOLD' => Household::query()->with('administrativeUnit')->inJurisdiction($actor),
            'DISASTER_EVENT' => DisasterEvent::query()->with('locations.administrativeUnit')->inJurisdiction($actor),
            'DAMAGE_ASSESSMENT' => DamageAssessment::query()->with('administrativeUnit')->inJurisdiction($actor),
            'RELIEF_REQUISITION' => ReliefRequisition::query()->with('administrativeUnit')->inJurisdiction($actor),
            'RELIEF_ALLOCATION' => ReliefAllocation::query()->with('administrativeUnit')->inJurisdiction($actor),
            'DISTRIBUTION_VOUCHER' => DistributionVoucher::query()->with('household.administrativeUnit'),
            'PROJECT' => Project::query()->with('administrativeUnit')->inJurisdiction($actor),
            'PROJECT_DISBURSEMENT' => ProjectDisbursement::query()->with('project.administrativeUnit'),
            'WORKFLOW_INSTANCE' => WorkflowInstance::query()->with('administrativeUnit')->inJurisdiction($actor),
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
                    'DISASTER_EVENT' => $q->where('disaster_code', 'like', $term)->orWhere('name_en', 'like', $term)->orWhere('name_bn', 'like', $term),
                    'DAMAGE_ASSESSMENT' => $q->where('d_form_code', 'like', $term)->orWhere('assessing_organization', 'like', $term),
                    'RELIEF_REQUISITION' => $q->where('requisition_no', 'like', $term)->orWhere('requesting_organization', 'like', $term),
                    'RELIEF_ALLOCATION' => $q->where('allocation_order_no', 'like', $term),
                    'DISTRIBUTION_VOUCHER' => $q->where('voucher_no', 'like', $term),
                    'PROJECT' => $q->where('project_code', 'like', $term)->orWhere('title_en', 'like', $term),
                    'PROJECT_DISBURSEMENT' => $q->where('reference_no', 'like', $term),
                    'WORKFLOW_INSTANCE' => $q->where('instance_no', 'like', $term)->orWhere('title_en', 'like', $term),
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
            if ($type === 'DISTRIBUTION_VOUCHER') {
                $unit = $item->household?->administrativeUnit;
                if ($unit && !$this->scopeService->canAccessScope($actor, $unit->ancestry_path)) {
                    continue;
                }
            }

            $unitId = $this->extractAdministrativeUnitId($type, $item, $actor->administrative_unit_id);
            $results[] = [
                'entity_id' => $item->id,
                'entity_type' => $type,
                'module_code' => $this->mapModuleCode($type),
                'entity_reference' => $this->extractReference($type, $item),
                'label_en' => $this->extractLabelEn($type, $item),
                'label_bn' => $this->extractLabelBn($type, $item),
                'administrative_unit_id' => $unitId,
            ];
        }

        return $results;
    }

    /**
     * Fetch live target summary for Document Detail UI.
     */
    public function getLiveSummary(?string $entityType, ?string $entityId): ?array
    {
        if (!$entityType || !$entityId) {
            return null;
        }

        $type = strtoupper($entityType);
        $model = $this->findModel($type, $entityId);
        if (!$model) {
            return null;
        }

        return [
            'entity_type' => $type,
            'entity_id' => $model->id,
            'module_code' => $this->mapModuleCode($type),
            'reference' => $this->extractReference($type, $model),
            'label_en' => $this->extractLabelEn($type, $model),
            'label_bn' => $this->extractLabelBn($type, $model),
            'route' => $this->extractRoute($type, $model),
        ];
    }

    protected function findModel(string $type, string $entityId): ?Model
    {
        return match ($type) {
            'HOUSEHOLD' => Household::find($entityId),
            'DISASTER_EVENT' => DisasterEvent::with('locations')->find($entityId),
            'DAMAGE_ASSESSMENT' => DamageAssessment::find($entityId),
            'RELIEF_REQUISITION' => ReliefRequisition::find($entityId),
            'RELIEF_ALLOCATION' => ReliefAllocation::find($entityId),
            'DISTRIBUTION_VOUCHER' => DistributionVoucher::with('household')->find($entityId),
            'PROJECT' => Project::find($entityId),
            'PROJECT_DISBURSEMENT' => ProjectDisbursement::with('project')->find($entityId),
            'WORKFLOW_INSTANCE' => WorkflowInstance::find($entityId),
            default => null,
        };
    }

    protected function mapModuleCode(string $type): string
    {
        return match ($type) {
            'HOUSEHOLD' => Document::MODULE_BENEFICIARY,
            'DISASTER_EVENT' => Document::MODULE_DISASTER,
            'DAMAGE_ASSESSMENT' => Document::MODULE_DAMAGE,
            'RELIEF_REQUISITION', 'RELIEF_ALLOCATION', 'DISTRIBUTION_VOUCHER' => Document::MODULE_RELIEF,
            'PROJECT', 'PROJECT_DISBURSEMENT' => Document::MODULE_PROJECT,
            'WORKFLOW_INSTANCE' => Document::MODULE_WORKFLOW,
            default => Document::MODULE_GENERAL,
        };
    }

    protected function extractAdministrativeUnitId(string $type, Model $record, ?string $fallback): ?string
    {
        return match ($type) {
            'DISASTER_EVENT' => $record->locations->first()?->administrative_unit_id ?? $fallback,
            'DISTRIBUTION_VOUCHER' => $record->household?->administrative_unit_id ?? $fallback,
            'PROJECT_DISBURSEMENT' => $record->project?->administrative_unit_id ?? $fallback,
            default => $record->administrative_unit_id ?? $fallback,
        };
    }

    protected function extractReference(string $type, Model $record): string
    {
        return (string) match ($type) {
            'HOUSEHOLD' => $record->household_code,
            'DISASTER_EVENT' => $record->disaster_code,
            'DAMAGE_ASSESSMENT' => $record->d_form_code,
            'RELIEF_REQUISITION' => $record->requisition_no,
            'RELIEF_ALLOCATION' => $record->allocation_order_no,
            'DISTRIBUTION_VOUCHER' => $record->voucher_no,
            'PROJECT' => $record->project_code,
            'PROJECT_DISBURSEMENT' => $record->reference_no,
            'WORKFLOW_INSTANCE' => $record->instance_no,
            default => $record->id,
        };
    }

    protected function extractLabelEn(string $type, Model $record): string
    {
        return match ($type) {
            'HOUSEHOLD' => "{$record->household_code} — {$record->hoh_name_en}",
            'DISASTER_EVENT' => "{$record->disaster_code} — {$record->name_en}",
            'DAMAGE_ASSESSMENT' => "{$record->d_form_code} — {$record->assessing_organization}",
            'RELIEF_REQUISITION' => "{$record->requisition_no} — {$record->requesting_organization}",
            'RELIEF_ALLOCATION' => "Allocation {$record->allocation_order_no}",
            'DISTRIBUTION_VOUCHER' => "Voucher {$record->voucher_no}",
            'PROJECT' => "{$record->project_code} — {$record->title_en}",
            'PROJECT_DISBURSEMENT' => "Disbursement {$record->reference_no}",
            'WORKFLOW_INSTANCE' => "{$record->instance_no} — {$record->title_en}",
            default => (string) $record->id,
        };
    }

    protected function extractLabelBn(string $type, Model $record): string
    {
        return match ($type) {
            'HOUSEHOLD' => "{$record->household_code} — " . ($record->hoh_name_bn ?: $record->hoh_name_en),
            'DISASTER_EVENT' => "{$record->disaster_code} — " . ($record->name_bn ?: $record->name_en),
            'DAMAGE_ASSESSMENT' => "ডি-ফর্ম {$record->d_form_code}",
            'RELIEF_REQUISITION' => "চাহিদাপত্র {$record->requisition_no}",
            'RELIEF_ALLOCATION' => "বরাদ্দ {$record->allocation_order_no}",
            'DISTRIBUTION_VOUCHER' => "ভাউচার {$record->voucher_no}",
            'PROJECT' => "{$record->project_code} — " . ($record->title_bn ?: $record->title_en),
            'PROJECT_DISBURSEMENT' => "ভাউচার {$record->reference_no}",
            'WORKFLOW_INSTANCE' => "{$record->instance_no} — " . ($record->title_bn ?: $record->title_en),
            default => (string) $record->id,
        };
    }

    protected function extractRoute(string $type, Model $record): string
    {
        return match ($type) {
            'HOUSEHOLD' => "/beneficiary/households/{$record->id}",
            'DISASTER_EVENT' => "/disaster/events/{$record->id}",
            'DAMAGE_ASSESSMENT' => "/damage/assessments/{$record->id}",
            'RELIEF_REQUISITION' => "/relief/requisitions/{$record->id}",
            'RELIEF_ALLOCATION' => "/relief/allocations/{$record->id}",
            'DISTRIBUTION_VOUCHER' => '/relief/distributions',
            'PROJECT' => "/project/schemes/{$record->id}",
            'PROJECT_DISBURSEMENT' => "/project/schemes/{$record->project_id}",
            'WORKFLOW_INSTANCE' => "/workflow/instances/{$record->id}",
            default => '/documents',
        };
    }

    protected function assertUnitInScope(User $actor, string $ancestryPath): void
    {
        if (!$this->scopeService->canAccessScope($actor, $ancestryPath)) {
            throw new AuthorizationException('Target administrative unit or record lies outside your jurisdiction.');
        }
    }
}
