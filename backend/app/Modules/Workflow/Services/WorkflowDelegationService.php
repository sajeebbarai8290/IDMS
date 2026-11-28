<?php

namespace App\Modules\Workflow\Services;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\AuditLog;
use App\Core\Models\User;
use App\Core\Services\AdministrativeScopeService;
use App\Modules\Workflow\Models\WorkflowDefinition;
use App\Modules\Workflow\Models\WorkflowDelegation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WorkflowDelegationService
{
    public function __construct(
        protected AdministrativeScopeService $scopeService
    ) {}

    public function listDelegations(User $actor, array $filters = [])
    {
        $query = WorkflowDelegation::with([
            'definition:id,code,name_en,name_bn',
            'delegator:id,full_name_en,full_name_bn,role_code',
            'delegate:id,full_name_en,full_name_bn,role_code',
            'administrativeUnit:id,name_en,name_bn,tier_level,ancestry_path',
        ]);

        if (!$actor->isNationalScope()) {
            $query->where(function ($q) use ($actor) {
                $q->where('delegator_user_id', $actor->id)
                    ->orWhere('delegate_user_id', $actor->id)
                    ->orWhere(function ($sq) use ($actor) {
                        $sq->inJurisdiction($actor);
                    });
            });
        }

        if (isset($filters['active_only']) && filter_var($filters['active_only'], FILTER_VALIDATE_BOOLEAN)) {
            $query->currentlyValid();
        }

        return $query->orderByDesc('created_at')->paginate((int) ($filters['per_page'] ?? 20));
    }

    public function createDelegation(array $data, User $actor): WorkflowDelegation
    {
        $delegatorId = $data['delegator_user_id'] ?? $actor->id;

        // Non-super-admin officers can only delegate their own authority
        if (!$actor->hasRole('ROLE_SUPER_ADMIN') && $delegatorId !== $actor->id) {
            throw new AuthorizationException('You may only delegate your own workflow approval authority.');
        }

        $delegator = User::findOrFail($delegatorId);
        $delegate = User::findOrFail($data['delegate_user_id']);

        if ($delegator->id === $delegate->id) {
            throw ValidationException::withMessages([
                'delegate_user_id' => ['Self-delegation is prohibited.'],
            ]);
        }

        if (!$delegate->is_active) {
            throw ValidationException::withMessages([
                'delegate_user_id' => ['Target delegate user account is inactive.'],
            ]);
        }

        if (in_array($delegate->role_code, ['ROLE_SUPER_ADMIN', 'ROLE_EXTERNAL_PARTNER', 'ROLE_NATIONAL_AUDITOR'], true)) {
            throw ValidationException::withMessages([
                'delegate_user_id' => ["Users with role {$delegate->role_code} cannot receive operational approval delegations."],
            ]);
        }

        if (!empty($data['workflow_definition_id'])) {
            $def = WorkflowDefinition::findOrFail($data['workflow_definition_id']);
            if (!$def->allow_delegation) {
                throw ValidationException::withMessages([
                    'workflow_definition_id' => ['Delegation is disabled by policy for the selected workflow definition.'],
                ]);
            }
        }

        $adminUnitId = $data['administrative_unit_id'] ?? $delegator->administrative_unit_id;
        if ($adminUnitId) {
            $unit = AdministrativeUnit::findOrFail($adminUnitId);
            if (!$this->scopeService->canAccessScope($actor, $unit->ancestry_path)) {
                throw new AuthorizationException('Delegation administrative unit is outside your jurisdiction.');
            }
            if (!$delegate->isNationalScope()) {
                $delegateUnit = $delegate->administrative_unit_id
                    ? AdministrativeUnit::find($delegate->administrative_unit_id)
                    : null;
                if ($delegateUnit) {
                    $delPath = rtrim($delegateUnit->ancestry_path, '/') . '/';
                    $targetPath = rtrim($unit->ancestry_path, '/') . '/';
                    if ($delPath !== '/' && !str_starts_with($targetPath, $delPath) && $this->scopeService->arePeersIsolated($delPath, $targetPath)) {
                        throw ValidationException::withMessages([
                            'delegate_user_id' => ['Delegate officer belongs to an isolated peer jurisdiction and cannot act on this administrative unit.'],
                        ]);
                    }
                }
            }
        }

        return DB::transaction(function () use ($data, $delegator, $delegate, $adminUnitId, $actor) {
            $delegation = WorkflowDelegation::create([
                'id' => (string) Str::uuid(),
                'workflow_definition_id' => $data['workflow_definition_id'] ?? null,
                'delegator_user_id' => $delegator->id,
                'delegate_user_id' => $delegate->id,
                'administrative_unit_id' => $adminUnitId,
                'delegated_role_code' => $data['delegated_role_code'] ?? $delegator->role_code,
                'valid_from' => $data['valid_from'],
                'valid_until' => $data['valid_until'],
                'reason' => $data['reason'],
                'is_active' => true,
                'approved_by' => $actor->id,
                'created_by' => $actor->id,
            ]);

            AuditLog::log(
                'WORKFLOW_DELEGATION_CREATED',
                WorkflowDelegation::class,
                $delegation->id,
                null,
                [
                    'delegator_user_id' => $delegation->delegator_user_id,
                    'delegate_user_id' => $delegation->delegate_user_id,
                    'delegated_role_code' => $delegation->delegated_role_code,
                    'valid_from' => $delegation->valid_from?->toIso8601String(),
                    'valid_until' => $delegation->valid_until?->toIso8601String(),
                ],
                $actor,
                $adminUnitId
            );

            return $delegation->fresh(['definition', 'delegator', 'delegate', 'administrativeUnit']);
        });
    }

    public function revokeDelegation(WorkflowDelegation $delegation, User $actor): WorkflowDelegation
    {
        if (!$delegation->is_active || $delegation->revoked_at !== null) {
            throw ValidationException::withMessages([
                'delegation' => ['Delegation is already inactive or revoked.'],
            ]);
        }

        return DB::transaction(function () use ($delegation, $actor) {
            $delegation->update([
                'is_active' => false,
                'revoked_by' => $actor->id,
                'revoked_at' => now(),
            ]);

            AuditLog::log(
                'WORKFLOW_DELEGATION_REVOKED',
                WorkflowDelegation::class,
                $delegation->id,
                ['is_active' => true],
                ['is_active' => false, 'revoked_by' => $actor->id],
                $actor,
                $delegation->administrative_unit_id
            );

            return $delegation->fresh(['definition', 'delegator', 'delegate', 'administrativeUnit']);
        });
    }
}
