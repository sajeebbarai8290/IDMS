<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for Module 11: Workflow Management.
     */
    public function up(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';

        if ($isPgsql) {
            DB::statement('CREATE SCHEMA IF NOT EXISTS workflow;');
        }

        $usersTable = $isPgsql ? 'core.users' : 'users';
        $adminUnitsTable = $isPgsql ? 'core.administrative_units' : 'administrative_units';

        $definitionsTable = $isPgsql ? 'workflow.workflow_definitions' : 'workflow_definitions';
        $stagesTable = $isPgsql ? 'workflow.workflow_stages' : 'workflow_stages';
        $assignmentsTable = $isPgsql ? 'workflow.workflow_stage_assignments' : 'workflow_stage_assignments';
        $delegationsTable = $isPgsql ? 'workflow.workflow_delegations' : 'workflow_delegations';
        $instancesTable = $isPgsql ? 'workflow.workflow_instances' : 'workflow_instances';
        $tasksTable = $isPgsql ? 'workflow.workflow_stage_tasks' : 'workflow_stage_tasks';
        $transitionsTable = $isPgsql ? 'workflow.workflow_transitions' : 'workflow_transitions';
        $attachmentsTable = $isPgsql ? 'workflow.workflow_attachments' : 'workflow_attachments';
        $notificationsTable = $isPgsql ? 'workflow.workflow_notifications' : 'workflow_notifications';

        // 1. Configurable Workflow Definitions
        Schema::create($definitionsTable, function (Blueprint $table) use ($usersTable) {
            $table->uuid('id')->primary();
            $table->string('code', 60)->unique();
            $table->string('name_en', 150);
            $table->string('name_bn', 200);
            $table->string('module_code', 40)->index();
            $table->string('entity_type', 100)->index();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_system')->default(false);
            $table->boolean('allow_self_approval')->default(false);
            $table->boolean('allow_delegation')->default(true);
            $table->boolean('sync_entity_status')->default(false);
            $table->integer('sla_hours')->default(48);
            $table->boolean('escalation_enabled')->default(true);
            $table->integer('version')->default(1);
            $table->json('config_metadata')->nullable();
            $table->uuid('created_by')->nullable()->index();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on($usersTable)->onDelete('set null');
        });

        // 2. Configurable Workflow Stages (Sequential & Parallel Gates)
        Schema::create($stagesTable, function (Blueprint $table) use ($definitionsTable) {
            $table->uuid('id')->primary();
            $table->uuid('workflow_definition_id')->index();
            $table->integer('stage_order')->index();
            $table->string('stage_code', 60);
            $table->string('name_en', 150);
            $table->string('name_bn', 200);
            $table->string('approval_level', 40)->index();
            $table->string('execution_mode', 30)->default('SEQUENTIAL');
            $table->integer('required_approvals')->default(1);
            $table->string('min_tier_level', 30)->nullable();
            $table->integer('sla_hours')->default(24);
            $table->string('escalation_role_code', 60)->nullable();
            $table->uuid('escalation_stage_id')->nullable()->index();
            $table->boolean('allow_delegation')->default(true);
            $table->boolean('allow_return_for_correction')->default(true);
            $table->boolean('allow_info_request')->default(true);
            $table->boolean('allow_forward')->default(true);
            $table->boolean('is_terminal')->default(false);
            $table->string('mapped_entity_status', 50)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['workflow_definition_id', 'stage_code'], 'uq_wf_stage_def_code');
            $table->foreign('workflow_definition_id')->references('id')->on($definitionsTable)->onDelete('cascade');
        });

        // 3. Stage Assignments (Users, Roles, Administrative Units)
        Schema::create($assignmentsTable, function (Blueprint $table) use ($stagesTable, $usersTable, $adminUnitsTable) {
            $table->uuid('id')->primary();
            $table->uuid('workflow_stage_id')->index();
            $table->string('assignee_type', 30)->index();
            $table->uuid('user_id')->nullable()->index();
            $table->string('role_code', 60)->nullable()->index();
            $table->uuid('administrative_unit_id')->nullable()->index();
            $table->string('tier_level', 30)->nullable();
            $table->string('authority_type', 30)->default('APPROVER');
            $table->boolean('can_delegate')->default(true);
            $table->boolean('is_mandatory')->default(true);
            $table->integer('sort_order')->default(1);
            $table->timestamps();

            $table->foreign('workflow_stage_id')->references('id')->on($stagesTable)->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('administrative_unit_id')->references('id')->on($adminUnitsTable)->onDelete('set null');
        });

        // 4. Authorized Workflow Delegations
        Schema::create($delegationsTable, function (Blueprint $table) use ($definitionsTable, $usersTable, $adminUnitsTable) {
            $table->uuid('id')->primary();
            $table->uuid('workflow_definition_id')->nullable()->index();
            $table->uuid('delegator_user_id')->index();
            $table->uuid('delegate_user_id')->index();
            $table->uuid('administrative_unit_id')->nullable()->index();
            $table->string('delegated_role_code', 60)->nullable();
            $table->timestamp('valid_from')->index();
            $table->timestamp('valid_until')->index();
            $table->text('reason');
            $table->boolean('is_active')->default(true)->index();
            $table->uuid('approved_by')->nullable();
            $table->uuid('revoked_by')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('workflow_definition_id')->references('id')->on($definitionsTable)->onDelete('cascade');
            $table->foreign('delegator_user_id')->references('id')->on($usersTable)->onDelete('cascade');
            $table->foreign('delegate_user_id')->references('id')->on($usersTable)->onDelete('cascade');
            $table->foreign('administrative_unit_id')->references('id')->on($adminUnitsTable)->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('revoked_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('created_by')->references('id')->on($usersTable)->onDelete('set null');
        });

        // 5. Workflow Instances (Runtime State Machines)
        Schema::create($instancesTable, function (Blueprint $table) use ($definitionsTable, $stagesTable, $adminUnitsTable, $usersTable) {
            $table->uuid('id')->primary();
            $table->string('instance_no', 50)->unique();
            $table->uuid('workflow_definition_id')->index();
            $table->string('module_code', 40)->index();
            $table->string('entity_type', 100)->index();
            $table->uuid('entity_id')->index();
            $table->string('entity_reference', 120)->nullable()->index();
            $table->string('title_en', 255);
            $table->string('title_bn', 255)->nullable();
            $table->uuid('administrative_unit_id')->index();
            $table->uuid('current_stage_id')->nullable()->index();
            $table->string('current_state', 50)->index();
            $table->string('priority', 20)->default('NORMAL')->index();
            $table->uuid('submitted_by')->index();
            $table->timestamp('submitted_at')->index();
            $table->uuid('assigned_to_user_id')->nullable()->index();
            $table->string('assigned_role_code', 60)->nullable()->index();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamp('escalated_at')->nullable()->index();
            $table->integer('escalation_count')->default(0);
            $table->timestamp('completed_at')->nullable()->index();
            $table->uuid('last_action_by')->nullable();
            $table->timestamp('last_action_at')->nullable();
            $table->text('submission_notes')->nullable();
            $table->text('final_remarks')->nullable();
            $table->json('metadata')->nullable();
            $table->uuid('created_by')->index();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['entity_type', 'entity_id'], 'idx_wf_instance_entity');
            $table->foreign('workflow_definition_id')->references('id')->on($definitionsTable)->onDelete('restrict');
            $table->foreign('administrative_unit_id')->references('id')->on($adminUnitsTable)->onDelete('restrict');
            $table->foreign('current_stage_id')->references('id')->on($stagesTable)->onDelete('set null');
            $table->foreign('submitted_by')->references('id')->on($usersTable)->onDelete('restrict');
            $table->foreign('assigned_to_user_id')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('last_action_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('created_by')->references('id')->on($usersTable)->onDelete('restrict');
            $table->foreign('updated_by')->references('id')->on($usersTable)->onDelete('set null');
        });

        // 6. Stage Approval Tasks (Sequential & Parallel Step Execution)
        Schema::create($tasksTable, function (Blueprint $table) use ($instancesTable, $stagesTable, $usersTable, $adminUnitsTable) {
            $table->uuid('id')->primary();
            $table->uuid('workflow_instance_id')->index();
            $table->uuid('workflow_stage_id')->index();
            $table->integer('step_sequence')->default(1);
            $table->string('assignee_type', 30);
            $table->uuid('assignee_user_id')->nullable()->index();
            $table->string('assignee_role_code', 60)->nullable()->index();
            $table->uuid('administrative_unit_id')->nullable()->index();
            $table->uuid('delegated_from_user_id')->nullable();
            $table->string('authority_type', 30)->default('APPROVER');
            $table->string('status', 30)->default('PENDING')->index();
            $table->timestamp('due_at')->nullable()->index();
            $table->uuid('acted_by_user_id')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->text('comments')->nullable();
            $table->timestamps();

            $table->foreign('workflow_instance_id')->references('id')->on($instancesTable)->onDelete('cascade');
            $table->foreign('workflow_stage_id')->references('id')->on($stagesTable)->onDelete('restrict');
            $table->foreign('assignee_user_id')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('administrative_unit_id')->references('id')->on($adminUnitsTable)->onDelete('set null');
            $table->foreign('delegated_from_user_id')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('acted_by_user_id')->references('id')->on($usersTable)->onDelete('set null');
        });

        // 7. Immutable Workflow Transition History (Permanent Audit Ledger)
        Schema::create($transitionsTable, function (Blueprint $table) use ($instancesTable, $tasksTable, $stagesTable, $usersTable) {
            $table->uuid('id')->primary();
            $table->uuid('workflow_instance_id')->index();
            $table->uuid('workflow_stage_task_id')->nullable()->index();
            $table->uuid('from_stage_id')->nullable();
            $table->uuid('to_stage_id')->nullable();
            $table->string('action', 40)->index();
            $table->string('from_state', 50);
            $table->string('to_state', 50)->index();
            $table->uuid('action_by_user_id')->index();
            $table->uuid('acted_on_behalf_of_user_id')->nullable();
            $table->string('actor_name', 150);
            $table->string('actor_role', 60)->nullable();
            $table->text('remarks')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('transitioned_at')->useCurrent()->index();

            $table->foreign('workflow_instance_id')->references('id')->on($instancesTable)->onDelete('restrict');
            $table->foreign('workflow_stage_task_id')->references('id')->on($tasksTable)->onDelete('set null');
            $table->foreign('from_stage_id')->references('id')->on($stagesTable)->onDelete('set null');
            $table->foreign('to_stage_id')->references('id')->on($stagesTable)->onDelete('set null');
            $table->foreign('action_by_user_id')->references('id')->on($usersTable)->onDelete('restrict');
            $table->foreign('acted_on_behalf_of_user_id')->references('id')->on($usersTable)->onDelete('set null');
        });

        // 8. Workflow Supporting Attachments (Private Storage + SHA-256)
        Schema::create($attachmentsTable, function (Blueprint $table) use ($instancesTable, $transitionsTable, $usersTable) {
            $table->uuid('id')->primary();
            $table->uuid('workflow_instance_id')->index();
            $table->uuid('workflow_transition_id')->nullable()->index();
            $table->string('title', 180)->nullable();
            $table->string('original_name', 255);
            $table->string('storage_path', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size_bytes');
            $table->string('sha256_hash', 64);
            $table->uuid('uploaded_by')->index();
            $table->timestamps();

            $table->foreign('workflow_instance_id')->references('id')->on($instancesTable)->onDelete('cascade');
            $table->foreign('workflow_transition_id')->references('id')->on($transitionsTable)->onDelete('set null');
            $table->foreign('uploaded_by')->references('id')->on($usersTable)->onDelete('restrict');
        });

        // 9. Workflow Notifications, Reminders & Escalation Dispatches
        Schema::create($notificationsTable, function (Blueprint $table) use ($instancesTable, $tasksTable, $usersTable, $adminUnitsTable) {
            $table->uuid('id')->primary();
            $table->uuid('workflow_instance_id')->index();
            $table->uuid('workflow_stage_task_id')->nullable()->index();
            $table->uuid('recipient_user_id')->nullable()->index();
            $table->string('recipient_role_code', 60)->nullable()->index();
            $table->uuid('administrative_unit_id')->nullable()->index();
            $table->string('notification_type', 40)->index();
            $table->string('channel', 30)->default('IN_APP');
            $table->string('title_en', 200);
            $table->string('title_bn', 250);
            $table->text('message_en');
            $table->text('message_bn');
            $table->string('delivery_status', 25)->default('QUEUED')->index();
            $table->string('provider_reference', 100)->nullable();
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamps();

            $table->foreign('workflow_instance_id')->references('id')->on($instancesTable)->onDelete('cascade');
            $table->foreign('workflow_stage_task_id')->references('id')->on($tasksTable)->onDelete('set null');
            $table->foreign('recipient_user_id')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('administrative_unit_id')->references('id')->on($adminUnitsTable)->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';

        $notificationsTable = $isPgsql ? 'workflow.workflow_notifications' : 'workflow_notifications';
        $attachmentsTable = $isPgsql ? 'workflow.workflow_attachments' : 'workflow_attachments';
        $transitionsTable = $isPgsql ? 'workflow.workflow_transitions' : 'workflow_transitions';
        $tasksTable = $isPgsql ? 'workflow.workflow_stage_tasks' : 'workflow_stage_tasks';
        $instancesTable = $isPgsql ? 'workflow.workflow_instances' : 'workflow_instances';
        $delegationsTable = $isPgsql ? 'workflow.workflow_delegations' : 'workflow_delegations';
        $assignmentsTable = $isPgsql ? 'workflow.workflow_stage_assignments' : 'workflow_stage_assignments';
        $stagesTable = $isPgsql ? 'workflow.workflow_stages' : 'workflow_stages';
        $definitionsTable = $isPgsql ? 'workflow.workflow_definitions' : 'workflow_definitions';

        Schema::dropIfExists($notificationsTable);
        Schema::dropIfExists($attachmentsTable);
        Schema::dropIfExists($transitionsTable);
        Schema::dropIfExists($tasksTable);
        Schema::dropIfExists($instancesTable);
        Schema::dropIfExists($delegationsTable);
        Schema::dropIfExists($assignmentsTable);
        Schema::dropIfExists($stagesTable);
        Schema::dropIfExists($definitionsTable);
    }
};
