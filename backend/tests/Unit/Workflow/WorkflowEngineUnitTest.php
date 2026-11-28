<?php

namespace Tests\Unit\Workflow;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Modules\Workflow\Models\WorkflowDefinition;
use App\Modules\Workflow\Models\WorkflowDelegation;
use App\Modules\Workflow\Models\WorkflowInstance;
use App\Modules\Workflow\Models\WorkflowTransition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class WorkflowEngineUnitTest extends TestCase
{
    use RefreshDatabase;

    public function test_workflow_transitions_are_strictly_immutable(): void
    {
        $unit = AdministrativeUnit::create([
            'id' => (string) Str::uuid(),
            'code' => '00',
            'name_en' => 'National',
            'name_bn' => 'জাতীয়',
            'tier_level' => 'NATIONAL',
            'ancestry_path' => '/',
            'is_active' => true,
        ]);

        $user = User::create([
            'id' => (string) Str::uuid(),
            'keycloak_sub' => (string) Str::uuid(),
            'email' => 'pio.unit@modmr.gov.bd',
            'full_name_en' => 'Test Officer',
            'role_code' => 'ROLE_PIO',
            'administrative_unit_id' => $unit->id,
            'is_active' => true,
        ]);

        $def = WorkflowDefinition::create([
            'id' => (string) Str::uuid(),
            'code' => 'WF_UNIT_TEST',
            'name_en' => 'Unit Test Workflow',
            'name_bn' => 'ইউনিট টেস্ট কর্মপ্রবাহ',
            'module_code' => 'GENERAL',
            'entity_type' => 'GENERAL_REQUEST',
            'is_active' => true,
        ]);

        $instance = WorkflowInstance::create([
            'id' => (string) Str::uuid(),
            'instance_no' => 'WF-UNIT-001',
            'workflow_definition_id' => $def->id,
            'module_code' => 'GENERAL',
            'entity_type' => 'GENERAL_REQUEST',
            'entity_id' => (string) Str::uuid(),
            'title_en' => 'Test Request',
            'administrative_unit_id' => $unit->id,
            'current_state' => WorkflowInstance::STATE_SUBMITTED,
            'submitted_by' => $user->id,
            'submitted_at' => now(),
            'created_by' => $user->id,
        ]);

        $transition = WorkflowTransition::create([
            'id' => (string) Str::uuid(),
            'workflow_instance_id' => $instance->id,
            'action' => WorkflowTransition::ACTION_SUBMIT,
            'from_state' => WorkflowInstance::STATE_SUBMITTED,
            'to_state' => WorkflowInstance::STATE_INITIAL_REVIEW,
            'action_by_user_id' => $user->id,
            'actor_name' => $user->full_name_en,
            'actor_role' => $user->role_code,
            'remarks' => 'Immutable decision record',
            'transitioned_at' => now(),
        ]);

        $this->expectException(LogicException::class);
        $transition->update(['remarks' => 'Tampered']);
    }

    public function test_workflow_transition_cannot_be_deleted(): void
    {
        $unit = AdministrativeUnit::create([
            'id' => (string) Str::uuid(),
            'code' => '01',
            'name_en' => 'National',
            'name_bn' => 'জাতীয়',
            'tier_level' => 'NATIONAL',
            'ancestry_path' => '/',
            'is_active' => true,
        ]);

        $user = User::create([
            'id' => (string) Str::uuid(),
            'keycloak_sub' => (string) Str::uuid(),
            'email' => 'drro.unit@modmr.gov.bd',
            'full_name_en' => 'Test DRRO',
            'role_code' => 'ROLE_DRRO',
            'administrative_unit_id' => $unit->id,
            'is_active' => true,
        ]);

        $def = WorkflowDefinition::create([
            'id' => (string) Str::uuid(),
            'code' => 'WF_UNIT_DEL',
            'name_en' => 'Unit Delete Guard',
            'name_bn' => 'ইউনিট গার্ড',
            'module_code' => 'GENERAL',
            'entity_type' => 'GENERAL_REQUEST',
            'is_active' => true,
        ]);

        $instance = WorkflowInstance::create([
            'id' => (string) Str::uuid(),
            'instance_no' => 'WF-UNIT-002',
            'workflow_definition_id' => $def->id,
            'module_code' => 'GENERAL',
            'entity_type' => 'GENERAL_REQUEST',
            'entity_id' => (string) Str::uuid(),
            'title_en' => 'Delete Guard Test',
            'administrative_unit_id' => $unit->id,
            'current_state' => WorkflowInstance::STATE_SUBMITTED,
            'submitted_by' => $user->id,
            'submitted_at' => now(),
            'created_by' => $user->id,
        ]);

        $transition = WorkflowTransition::create([
            'id' => (string) Str::uuid(),
            'workflow_instance_id' => $instance->id,
            'action' => WorkflowTransition::ACTION_SUBMIT,
            'from_state' => WorkflowInstance::STATE_SUBMITTED,
            'to_state' => WorkflowInstance::STATE_INITIAL_REVIEW,
            'action_by_user_id' => $user->id,
            'actor_name' => $user->full_name_en,
            'transitioned_at' => now(),
        ]);

        $this->expectException(LogicException::class);
        $transition->delete();
    }

    public function test_instance_overdue_and_delegation_validity_helpers(): void
    {
        $activeOverdue = new WorkflowInstance([
            'current_state' => WorkflowInstance::STATE_DISTRICT_APPROVAL,
            'due_at' => now()->subHours(2),
        ]);
        $completedOverdue = new WorkflowInstance([
            'current_state' => WorkflowInstance::STATE_FINAL_APPROVED,
            'due_at' => now()->subHours(2),
        ]);
        $onTime = new WorkflowInstance([
            'current_state' => WorkflowInstance::STATE_INITIAL_REVIEW,
            'due_at' => now()->addHours(4),
        ]);

        $this->assertTrue($activeOverdue->isOverdue());
        $this->assertFalse($completedOverdue->isOverdue());
        $this->assertFalse($onTime->isOverdue());
        $this->assertTrue($completedOverdue->isTerminal());

        $unit = AdministrativeUnit::create([
            'id' => (string) Str::uuid(),
            'code' => '02',
            'name_en' => 'Dhaka',
            'name_bn' => 'ঢাকা',
            'tier_level' => 'DIVISION',
            'ancestry_path' => '/30/',
            'is_active' => true,
        ]);

        $u1 = User::create([
            'id' => (string) Str::uuid(),
            'keycloak_sub' => (string) Str::uuid(),
            'email' => 'u1@modmr.gov.bd',
            'full_name_en' => 'U1',
            'role_code' => 'ROLE_DRRO',
            'administrative_unit_id' => $unit->id,
            'is_active' => true,
        ]);
        $u2 = User::create([
            'id' => (string) Str::uuid(),
            'keycloak_sub' => (string) Str::uuid(),
            'email' => 'u2@modmr.gov.bd',
            'full_name_en' => 'U2',
            'role_code' => 'ROLE_UNO',
            'administrative_unit_id' => $unit->id,
            'is_active' => true,
        ]);

        WorkflowDelegation::create([
            'id' => (string) Str::uuid(),
            'delegator_user_id' => $u1->id,
            'delegate_user_id' => $u2->id,
            'valid_from' => now()->subHour(),
            'valid_until' => now()->addHour(),
            'reason' => 'Active leave delegation',
            'is_active' => true,
        ]);

        WorkflowDelegation::create([
            'id' => (string) Str::uuid(),
            'delegator_user_id' => $u1->id,
            'delegate_user_id' => $u2->id,
            'valid_from' => now()->subDays(5),
            'valid_until' => now()->subDays(1),
            'reason' => 'Expired delegation',
            'is_active' => true,
        ]);

        $this->assertSame(1, WorkflowDelegation::currentlyValid()->count());
    }
}
