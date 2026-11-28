<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for Module 13: Notification & Alert Management.
     * Supports PostgreSQL (notification schema) and SQLite (testing fallback).
     */
    public function up(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';

        if ($isPgsql) {
            DB::statement('CREATE SCHEMA IF NOT EXISTS notification;');
        }

        $usersTable = $isPgsql ? 'core.users' : 'users';
        $adminUnitsTable = $isPgsql ? 'core.administrative_units' : 'administrative_units';

        $templatesTable = $isPgsql ? 'notification.notification_templates' : 'notification_templates';
        $notificationsTable = $isPgsql ? 'notification.notifications' : 'notifications';
        $dispatchesTable = $isPgsql ? 'notification.notification_dispatches' : 'notification_dispatches';
        $alertRulesTable = $isPgsql ? 'notification.alert_rules' : 'alert_rules';
        $alertsTable = $isPgsql ? 'notification.alerts' : 'alerts';
        $preferencesTable = $isPgsql ? 'notification.notification_preferences' : 'notification_preferences';
        $devicesTable = $isPgsql ? 'notification.notification_devices' : 'notification_devices';

        // 1. Bilingual Notification Templates
        Schema::create($templatesTable, function (Blueprint $table) use ($usersTable) {
            $table->uuid('id')->primary();
            $table->string('template_code', 80)->unique();
            $table->string('category', 50)->index()
                ->comment('DISASTER_WARNING, EMERGENCY_ALERT, RELIEF_APPROVAL, BENEFICIARY_VERIFICATION, WORKFLOW_APPROVAL, INVENTORY_ALERT, PROJECT_MONITORING, SYSTEM_ANNOUNCEMENT');
            $table->string('channel', 30)->default('MULTI')->index()
                ->comment('IN_APP, EMAIL, SMS, PUSH, MULTI');
            $table->string('default_severity', 20)->default('INFO')->index()
                ->comment('INFO, WARNING, HIGH, CRITICAL');
            $table->string('title_bn', 250);
            $table->string('title_en', 250);
            $table->string('subject_bn', 250)->nullable();
            $table->string('subject_en', 250)->nullable();
            $table->text('body_bn');
            $table->text('body_en');
            $table->json('variables')->nullable();
            $table->boolean('is_system')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on($usersTable)->onDelete('set null');
        });

        // 2. Notification Campaigns & Scheduled Notices
        Schema::create($notificationsTable, function (Blueprint $table) use ($templatesTable, $adminUnitsTable, $usersTable) {
            $table->uuid('id')->primary();
            $table->string('notification_no', 50)->unique();
            $table->uuid('template_id')->nullable()->index();
            $table->string('category', 50)->index();
            $table->string('severity', 20)->default('INFO')->index()
                ->comment('INFO, WARNING, HIGH, CRITICAL');
            $table->string('priority', 20)->default('NORMAL')->index()
                ->comment('LOW, NORMAL, HIGH, URGENT');
            $table->json('channels');
            $table->string('title_bn', 250);
            $table->string('title_en', 250);
            $table->text('message_bn');
            $table->text('message_en');
            $table->string('target_type', 30)->default('USER')->index()
                ->comment('USER, ROLE, ORGANIZATION, GEOGRAPHY, ALL');
            $table->json('target_criteria')->nullable();
            $table->uuid('administrative_unit_id')->nullable()->index();
            $table->string('related_entity_type', 80)->nullable()->index();
            $table->uuid('related_entity_id')->nullable()->index();
            $table->string('status', 30)->default('QUEUED')->index()
                ->comment('DRAFT, SCHEDULED, QUEUED, PROCESSING, DISPATCHED, PARTIALLY_FAILED, FAILED, CANCELLED');
            $table->string('idempotency_key', 120)->nullable()->unique();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('dispatched_at')->nullable()->index();
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->json('metadata')->nullable();
            $table->uuid('created_by')->nullable()->index();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('template_id')->references('id')->on($templatesTable)->onDelete('set null');
            $table->foreign('administrative_unit_id')->references('id')->on($adminUnitsTable)->onDelete('set null');
            $table->foreign('created_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on($usersTable)->onDelete('set null');
        });

        // 3. Per-Recipient, Per-Channel Delivery Logs & In-App Inbox
        Schema::create($dispatchesTable, function (Blueprint $table) use ($notificationsTable, $templatesTable, $usersTable, $adminUnitsTable) {
            $table->uuid('id')->primary();
            $table->uuid('notification_id')->index();
            $table->uuid('template_id')->nullable()->index();
            $table->uuid('recipient_user_id')->nullable()->index();
            $table->uuid('administrative_unit_id')->nullable()->index();
            $table->string('channel', 20)->index()
                ->comment('IN_APP, EMAIL, SMS, PUSH');
            $table->string('destination_address', 255)->nullable();
            $table->string('title_bn', 250)->nullable();
            $table->string('title_en', 250)->nullable();
            $table->text('message_body');
            $table->text('message_body_bn')->nullable();
            $table->text('message_body_en')->nullable();
            $table->string('delivery_status', 30)->default('QUEUED')->index()
                ->comment('QUEUED, RETRYING, SENT, DELIVERED, FAILED, SKIPPED');
            $table->string('provider_name', 60)->nullable();
            $table->string('provider_reference', 120)->nullable()->index();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->text('last_error')->nullable();
            $table->string('dedup_hash', 64)->nullable()->unique();
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamp('dispatched_at')->nullable()->index();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['recipient_user_id', 'channel', 'read_at'], 'idx_notif_dispatch_inbox');
            $table->foreign('notification_id')->references('id')->on($notificationsTable)->onDelete('cascade');
            $table->foreign('template_id')->references('id')->on($templatesTable)->onDelete('set null');
            $table->foreign('recipient_user_id')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('administrative_unit_id')->references('id')->on($adminUnitsTable)->onDelete('set null');
        });

        // 4. Configurable Alert Rules & Escalation Policies
        Schema::create($alertRulesTable, function (Blueprint $table) use ($adminUnitsTable, $usersTable) {
            $table->uuid('id')->primary();
            $table->string('rule_code', 80)->unique();
            $table->string('name_bn', 220);
            $table->string('name_en', 220);
            $table->text('description_bn')->nullable();
            $table->text('description_en')->nullable();
            $table->string('category', 50)->index();
            $table->string('event_type', 80)->index();
            $table->string('severity', 20)->default('HIGH')->index()
                ->comment('INFO, WARNING, HIGH, CRITICAL');
            $table->json('condition_config')->nullable();
            $table->json('channels');
            $table->json('target_roles')->nullable();
            $table->unsignedInteger('escalation_delay_minutes')->default(30);
            $table->string('escalation_role_code', 60)->nullable();
            $table->unsignedInteger('cooldown_minutes')->default(15);
            $table->uuid('administrative_unit_id')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('administrative_unit_id')->references('id')->on($adminUnitsTable)->onDelete('set null');
            $table->foreign('created_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on($usersTable)->onDelete('set null');
        });

        // 5. Triggered Operational & Emergency Alerts
        Schema::create($alertsTable, function (Blueprint $table) use ($alertRulesTable, $adminUnitsTable, $usersTable) {
            $table->uuid('id')->primary();
            $table->string('alert_no', 50)->unique();
            $table->uuid('alert_rule_id')->nullable()->index();
            $table->string('category', 50)->index();
            $table->string('severity', 20)->default('HIGH')->index()
                ->comment('INFO, WARNING, HIGH, CRITICAL');
            $table->string('status', 30)->default('ACTIVE')->index()
                ->comment('ACTIVE, ACKNOWLEDGED, ESCALATED, RESOLVED');
            $table->string('title_bn', 250);
            $table->string('title_en', 250);
            $table->text('message_bn');
            $table->text('message_en');
            $table->string('source_module', 60)->nullable()->index();
            $table->string('source_entity_type', 80)->nullable()->index();
            $table->uuid('source_entity_id')->nullable()->index();
            $table->json('metric_payload')->nullable();
            $table->uuid('administrative_unit_id')->nullable()->index();
            $table->string('idempotency_key', 120)->nullable()->unique();
            $table->timestamp('triggered_at')->useCurrent()->index();
            $table->uuid('acknowledged_by')->nullable()->index();
            $table->timestamp('acknowledged_at')->nullable();
            $table->text('acknowledgement_notes')->nullable();
            $table->timestamp('escalated_at')->nullable()->index();
            $table->string('escalated_to_role', 60)->nullable();
            $table->uuid('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('alert_rule_id')->references('id')->on($alertRulesTable)->onDelete('set null');
            $table->foreign('administrative_unit_id')->references('id')->on($adminUnitsTable)->onDelete('set null');
            $table->foreign('acknowledged_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('resolved_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('created_by')->references('id')->on($usersTable)->onDelete('set null');
        });

        // 6. User Notification Channel & Category Preferences
        Schema::create($preferencesTable, function (Blueprint $table) use ($usersTable) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->index();
            $table->string('category', 50)->index();
            $table->boolean('in_app_enabled')->default(true);
            $table->boolean('email_enabled')->default(true);
            $table->boolean('sms_enabled')->default(false);
            $table->boolean('push_enabled')->default(true);
            $table->string('quiet_hours_start', 5)->nullable();
            $table->string('quiet_hours_end', 5)->nullable();
            $table->boolean('policy_locked')->default(false)->index();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'category'], 'uq_notif_pref_user_category');
            $table->foreign('user_id')->references('id')->on($usersTable)->onDelete('cascade');
            $table->foreign('updated_by')->references('id')->on($usersTable)->onDelete('set null');
        });

        // 7. Future Flutter & Mobile Push Device Registrations
        Schema::create($devicesTable, function (Blueprint $table) use ($usersTable) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->index();
            $table->string('device_uuid', 120);
            $table->string('platform', 20)->default('ANDROID')->index()
                ->comment('ANDROID, IOS, WEB');
            $table->string('push_provider', 20)->default('FCM')->index()
                ->comment('FCM, APNS, WEB_PUSH');
            $table->text('push_token_encrypted');
            $table->char('push_token_hash', 64)->index();
            $table->string('device_name', 150)->nullable();
            $table->string('app_version', 40)->nullable();
            $table->string('locale', 10)->default('bn');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['user_id', 'device_uuid'], 'uq_notif_device_user_uuid');
            $table->foreign('user_id')->references('id')->on($usersTable)->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';

        $devicesTable = $isPgsql ? 'notification.notification_devices' : 'notification_devices';
        $preferencesTable = $isPgsql ? 'notification.notification_preferences' : 'notification_preferences';
        $alertsTable = $isPgsql ? 'notification.alerts' : 'alerts';
        $alertRulesTable = $isPgsql ? 'notification.alert_rules' : 'alert_rules';
        $dispatchesTable = $isPgsql ? 'notification.notification_dispatches' : 'notification_dispatches';
        $notificationsTable = $isPgsql ? 'notification.notifications' : 'notifications';
        $templatesTable = $isPgsql ? 'notification.notification_templates' : 'notification_templates';

        Schema::dropIfExists($devicesTable);
        Schema::dropIfExists($preferencesTable);
        Schema::dropIfExists($alertsTable);
        Schema::dropIfExists($alertRulesTable);
        Schema::dropIfExists($dispatchesTable);
        Schema::dropIfExists($notificationsTable);
        Schema::dropIfExists($templatesTable);
    }
};
