<?php

namespace App\Modules\Workflow\Services;

use App\Core\Models\AuditLog;
use App\Core\Models\User;
use App\Modules\Workflow\Models\WorkflowDefinition;
use App\Modules\Workflow\Models\WorkflowStage;
use App\Modules\Workflow\Models\WorkflowStageAssignment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WorkflowConfigurationService
{
    public const CACHE_VERSION_KEY = 'workflow:dashboard:version';

    public function listDefinitions(array $filters = [])
    {
        $query = WorkflowDefinition::with(['stages.assignments.user', 'stages.assignments.administrativeUnit'])
            ->withCount('instances');

        if (!empty($filters['module_code'])) {
            $query->where('module_code', strtoupper((string) $filters['module_code']));
        }
        if (!empty($filters['entity_type'])) {
            $query->where('entity_type', strtoupper((string) $filters['entity_type']));
        }
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }
        if (!empty($filters['search'])) {
            $term = '%' . trim((string) $filters['search']) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('code', 'like', $term)
                    ->orWhere('name_en', 'like', $term)
                    ->orWhere('name_bn', 'like', $term);
            });
        }

        return $query->orderBy('module_code')->orderBy('name_en')->get();
    }

    public function createDefinition(array $data, User $actor): WorkflowDefinition
    {
        return DB::transaction(function () use ($data, $actor) {
            $moduleCode = strtoupper(trim($data['module_code']));
            $entityType = strtoupper(trim($data['entity_type']));
            $isDefault = (bool) ($data['is_default'] ?? false);

            if ($isDefault) {
                WorkflowDefinition::where('module_code', $moduleCode)
                    ->where('entity_type', $entityType)
                    ->update(['is_default' => false]);
            }

            $definition = WorkflowDefinition::create([
                'code' => strtoupper(trim($data['code'])),
                'name_en' => $data['name_en'],
                'name_bn' => $data['name_bn'],
                'module_code' => $moduleCode,
                'entity_type' => $entityType,
                'description' => $data['description'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? true),
                'is_default' => $isDefault,
                'is_system' => false,
                'allow_self_approval' => (bool) ($data['allow_self_approval'] ?? false),
                'allow_delegation' => (bool) ($data['allow_delegation'] ?? true),
                'sync_entity_status' => (bool) ($data['sync_entity_status'] ?? false),
                'sla_hours' => (int) ($data['sla_hours'] ?? 48),
                'escalation_enabled' => (bool) ($data['escalation_enabled'] ?? true),
                'version' => 1,
                'config_metadata' => $data['config_metadata'] ?? null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->syncStages($definition, $data['stages'] ?? []);

            AuditLog::log(
                'WORKFLOW_DEFINITION_CREATED',
                WorkflowDefinition::class,
                $definition->id,
                null,
                [
                    'code' => $definition->code,
                    'module_code' => $definition->module_code,
                    'entity_type' => $definition->entity_type,
                    'stages_count' => count($data['stages'] ?? []),
                ],
                $actor
            );

            $this->bumpCacheVersion();

            return $definition->fresh(['stages.assignments.user', 'stages.assignments.administrativeUnit']);
        });
    }

    public function updateDefinition(WorkflowDefinition $definition, array $data, User $actor): WorkflowDefinition
    {
        return DB::transaction(function () use ($definition, $data, $actor) {
            $oldSnapshot = [
                'name_en' => $definition->name_en,
                'is_active' => $definition->is_active,
                'is_default' => $definition->is_default,
                'allow_self_approval' => $definition->allow_self_approval,
                'allow_delegation' => $definition->allow_delegation,
                'sla_hours' => $definition->sla_hours,
                'version' => $definition->version,
            ];

            $moduleCode = isset($data['module_code']) ? strtoupper(trim($data['module_code'])) : $definition->module_code;
            $entityType = isset($data['entity_type']) ? strtoupper(trim($data['entity_type'])) : $definition->entity_type;
            $isDefault = array_key_exists('is_default', $data) ? (bool) $data['is_default'] : $definition->is_default;

            if ($isDefault) {
                WorkflowDefinition::where('module_code', $moduleCode)
                    ->where('entity_type', $entityType)
                    ->where('id', '!=', $definition->id)
                    ->update(['is_default' => false]);
            }

            $definition->update([
                'name_en' => $data['name_en'] ?? $definition->name_en,
                'name_bn' => $data['name_bn'] ?? $definition->name_bn,
                'module_code' => $moduleCode,
                'entity_type' => $entityType,
                'description' => array_key_exists('description', $data) ? $data['description'] : $definition->description,
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $definition->is_active,
                'is_default' => $isDefault,
                'allow_self_approval' => array_key_exists('allow_self_approval', $data)
                    ? (bool) $data['allow_self_approval']
                    : $definition->allow_self_approval,
                'allow_delegation' => array_key_exists('allow_delegation', $data)
                    ? (bool) $data['allow_delegation']
                    : $definition->allow_delegation,
                'sync_entity_status' => array_key_exists('sync_entity_status', $data)
                    ? (bool) $data['sync_entity_status']
                    : $definition->sync_entity_status,
                'sla_hours' => $data['sla_hours'] ?? $definition->sla_hours,
                'escalation_enabled' => array_key_exists('escalation_enabled', $data)
                    ? (bool) $data['escalation_enabled']
                    : $definition->escalation_enabled,
                'version' => $definition->version + 1,
                'config_metadata' => array_key_exists('config_metadata', $data)
                    ? $data['config_metadata']
                    : $definition->config_metadata,
                'updated_by' => $actor->id,
            ]);

            if (isset($data['stages']) && is_array($data['stages'])) {
                $this->syncStages($definition, $data['stages']);
            }

            AuditLog::log(
                'WORKFLOW_DEFINITION_UPDATED',
                WorkflowDefinition::class,
                $definition->id,
                $oldSnapshot,
                [
                    'name_en' => $definition->name_en,
                    'is_active' => $definition->is_active,
                    'is_default' => $definition->is_default,
                    'allow_self_approval' => $definition->allow_self_approval,
                    'allow_delegation' => $definition->allow_delegation,
                    'sla_hours' => $definition->sla_hours,
                    'version' => $definition->version,
                ],
                $actor
            );

            $this->bumpCacheVersion();

            return $definition->fresh(['stages.assignments.user', 'stages.assignments.administrativeUnit']);
        });
    }

    public function toggleStatus(WorkflowDefinition $definition, bool $isActive, User $actor): WorkflowDefinition
    {
        return DB::transaction(function () use ($definition, $isActive, $actor) {
            $oldStatus = $definition->is_active;
            $definition->update([
                'is_active' => $isActive,
                'updated_by' => $actor->id,
            ]);

            AuditLog::log(
                'WORKFLOW_DEFINITION_STATUS_CHANGED',
                WorkflowDefinition::class,
                $definition->id,
                ['is_active' => $oldStatus],
                ['is_active' => $definition->is_active, 'code' => $definition->code],
                $actor
            );

            $this->bumpCacheVersion();

            return $definition->fresh(['stages.assignments.user', 'stages.assignments.administrativeUnit']);
        });
    }

    public function deleteDefinition(WorkflowDefinition $definition, User $actor): void
    {
        if ($definition->is_system) {
            throw ValidationException::withMessages([
                'definition' => ['System workflow definitions cannot be deleted; deactivate them instead.'],
            ]);
        }

        if ($definition->instances()->exists()) {
            throw ValidationException::withMessages([
                'definition' => ['Cannot delete a workflow definition with existing historical instances; deactivate it instead.'],
            ]);
        }

        DB::transaction(function () use ($definition, $actor) {
            AuditLog::log(
                'WORKFLOW_DEFINITION_DELETED',
                WorkflowDefinition::class,
                $definition->id,
                ['code' => $definition->code, 'name_en' => $definition->name_en],
                null,
                $actor
            );

            $definition->delete();
            $this->bumpCacheVersion();
        });
    }

    protected function syncStages(WorkflowDefinition $definition, array $stagesData): void
    {
        if (count($stagesData) === 0) {
            throw ValidationException::withMessages([
                'stages' => ['A workflow definition must contain at least one stage.'],
            ]);
        }

        // Preserve stages referenced by existing instances/tasks/transitions by updating in place when possible
        $existingStages = $definition->stages()->get()->keyBy('stage_code');
        $incomingCodes = [];

        foreach ($stagesData as $index => $stageInput) {
            $stageCode = strtoupper(trim($stageInput['stage_code']));
            if (in_array($stageCode, $incomingCodes, true)) {
                throw ValidationException::withMessages([
                    'stages' => ["Duplicate stage code '{$stageCode}' within workflow definition."],
                ]);
            }
            $incomingCodes[] = $stageCode;

            $stagePayload = [
                'workflow_definition_id' => $definition->id,
                'stage_order' => (int) ($stageInput['stage_order'] ?? ($index + 1)),
                'stage_code' => $stageCode,
                'name_en' => $stageInput['name_en'],
                'name_bn' => $stageInput['name_bn'] ?? $stageInput['name_en'],
                'approval_level' => strtoupper($stageInput['approval_level']),
                'execution_mode' => strtoupper($stageInput['execution_mode'] ?? WorkflowStage::MODE_SEQUENTIAL),
                'required_approvals' => max(1, (int) ($stageInput['required_approvals'] ?? 1)),
                'min_tier_level' => $stageInput['min_tier_level'] ?? null,
                'sla_hours' => max(1, (int) ($stageInput['sla_hours'] ?? 24)),
                'escalation_role_code' => $stageInput['escalation_role_code'] ?? null,
                'allow_delegation' => (bool) ($stageInput['allow_delegation'] ?? true),
                'allow_return_for_correction' => (bool) ($stageInput['allow_return_for_correction'] ?? true),
                'allow_info_request' => (bool) ($stageInput['allow_info_request'] ?? true),
                'allow_forward' => (bool) ($stageInput['allow_forward'] ?? true),
                'is_terminal' => (bool) ($stageInput['is_terminal'] ?? ($index === count($stagesData) - 1)),
                'mapped_entity_status' => $stageInput['mapped_entity_status'] ?? null,
                'description' => $stageInput['description'] ?? null,
            ];

            /** @var WorkflowStage|null $stage */
            $stage = $existingStages->get($stageCode);
            if ($stage) {
                $stage->update($stagePayload);
                $stage->assignments()->delete();
            } else {
                $stage = WorkflowStage::create(array_merge($stagePayload, [
                    'id' => (string) Str::uuid(),
                ]));
            }

            foreach (($stageInput['assignments'] ?? []) as $aIndex => $assign) {
                WorkflowStageAssignment::create([
                    'id' => (string) Str::uuid(),
                    'workflow_stage_id' => $stage->id,
                    'assignee_type' => strtoupper($assign['assignee_type']),
                    'user_id' => $assign['user_id'] ?? null,
                    'role_code' => $assign['role_code'] ?? null,
                    'administrative_unit_id' => $assign['administrative_unit_id'] ?? null,
                    'tier_level' => $assign['tier_level'] ?? null,
                    'authority_type' => strtoupper($assign['authority_type'] ?? WorkflowStageAssignment::AUTHORITY_APPROVER),
                    'can_delegate' => (bool) ($assign['can_delegate'] ?? true),
                    'is_mandatory' => (bool) ($assign['is_mandatory'] ?? true),
                    'sort_order' => (int) ($assign['sort_order'] ?? ($aIndex + 1)),
                ]);
            }
        }

        // Delete removed stages only if not referenced by historical tasks/transitions
        foreach ($existingStages as $code => $oldStage) {
            if (!in_array($code, $incomingCodes, true)) {
                $hasRefs = $definition->instances()->where('current_stage_id', $oldStage->id)->exists();
                if ($hasRefs) {
                    throw ValidationException::withMessages([
                        'stages' => ["Stage '{$code}' has active or historical workflow instances and cannot be removed."],
                    ]);
                }
                $oldStage->delete();
            }
        }
    }

    public function bumpCacheVersion(): void
    {
        $current = (int) Cache::get(self::CACHE_VERSION_KEY, 1);
        Cache::put(self::CACHE_VERSION_KEY, $current + 1, 86400);
    }
}
