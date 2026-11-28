<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for Module 10: Volunteer Management.
     */
    public function up(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';

        if ($isPgsql) {
            DB::statement('CREATE SCHEMA IF NOT EXISTS volunteer;');
        }

        $usersTable = $isPgsql ? 'core.users' : 'users';
        $adminUnitsTable = $isPgsql ? 'core.administrative_units' : 'administrative_units';
        $disasterEventsTable = $isPgsql ? 'disaster.disaster_events' : 'disaster_events';
        $operationsTable = $isPgsql ? 'response.response_operations' : 'response_operations';
        $teamsTable = $isPgsql ? 'response.response_teams' : 'response_teams';
        $nidVerificationsTable = $isPgsql ? 'beneficiary.nid_verifications' : 'nid_verifications';

        $categoriesTable = $isPgsql ? 'volunteer.volunteer_categories' : 'volunteer_categories';
        $organizationsTable = $isPgsql ? 'volunteer.volunteer_organizations' : 'volunteer_organizations';
        $orgCoveragesTable = $isPgsql ? 'volunteer.volunteer_organization_coverages' : 'volunteer_organization_coverages';
        $skillCatalogTable = $isPgsql ? 'volunteer.skill_catalog' : 'skill_catalog';
        $volunteersTable = $isPgsql ? 'volunteer.volunteers' : 'volunteers';
        $skillAssignmentsTable = $isPgsql ? 'volunteer.volunteer_skill_assignments' : 'volunteer_skill_assignments';
        $verificationLogsTable = $isPgsql ? 'volunteer.volunteer_verification_logs' : 'volunteer_verification_logs';
        $deploymentsTable = $isPgsql ? 'volunteer.volunteer_deployments' : 'volunteer_deployments';
        $attendancesTable = $isPgsql ? 'volunteer.volunteer_attendances' : 'volunteer_attendances';
        $programsTable = $isPgsql ? 'volunteer.volunteer_training_programs' : 'volunteer_training_programs';
        $sessionsTable = $isPgsql ? 'volunteer.volunteer_training_sessions' : 'volunteer_training_sessions';
        $enrollmentsTable = $isPgsql ? 'volunteer.volunteer_training_enrollments' : 'volunteer_training_enrollments';

        // 1. Configurable Volunteer Categories
        Schema::create($categoriesTable, function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 60)->unique();
            $table->string('name_en', 120);
            $table->string('name_bn', 160);
            $table->text('description')->nullable();
            $table->string('color_hex', 20)->nullable();
            $table->integer('sort_order')->default(100);
            $table->boolean('is_system')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        // 2. Volunteer Organizations
        Schema::create($organizationsTable, function (Blueprint $table) use ($adminUnitsTable, $usersTable) {
            $table->uuid('id')->primary();
            $table->string('org_code', 50)->unique();
            $table->string('name_en', 180)->index();
            $table->string('name_bn', 220)->nullable();
            $table->string('org_type', 40)->default('CPP')->index(); // CPP, BDRCS, SCOUT, BNCC, NGO, INGO, COMMUNITY, GOVERNMENT, OTHER
            $table->string('registration_no', 80)->nullable();
            $table->uuid('administrative_unit_id')->index();
            $table->string('address', 255)->nullable();
            $table->string('contact_person_name', 150);
            $table->string('contact_person_designation', 120)->nullable();
            $table->string('contact_person_phone', 60);
            $table->string('contact_person_email', 150)->nullable();
            $table->text('description')->nullable();
            $table->string('verification_status', 30)->default('PENDING')->index(); // PENDING, VERIFIED, REJECTED
            $table->uuid('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_remarks')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->uuid('created_by')->nullable()->index();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('administrative_unit_id')->references('id')->on($adminUnitsTable)->onDelete('restrict');
            $table->foreign('verified_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('created_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on($usersTable)->onDelete('set null');
        });

        // 3. Organization Geographic Coverage
        Schema::create($orgCoveragesTable, function (Blueprint $table) use ($organizationsTable, $adminUnitsTable) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->index();
            $table->uuid('administrative_unit_id')->index();
            $table->timestamps();

            $table->unique(['organization_id', 'administrative_unit_id'], 'uq_vol_org_coverage_unit');
            $table->foreign('organization_id')->references('id')->on($organizationsTable)->onDelete('cascade');
            $table->foreign('administrative_unit_id')->references('id')->on($adminUnitsTable)->onDelete('restrict');
        });

        // 4. Configurable Skill & Qualification Catalog
        Schema::create($skillCatalogTable, function (Blueprint $table) use ($categoriesTable) {
            $table->uuid('id')->primary();
            $table->uuid('category_id')->nullable()->index();
            $table->string('skill_code', 60)->unique();
            $table->string('name_en', 120);
            $table->string('name_bn', 160);
            $table->text('description')->nullable();
            $table->boolean('requires_certification')->default(false);
            $table->boolean('is_system')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on($categoriesTable)->onDelete('set null');
        });

        // 5. Master Volunteers Registry
        Schema::create($volunteersTable, function (Blueprint $table) use (
            $adminUnitsTable,
            $organizationsTable,
            $categoriesTable,
            $usersTable,
            $nidVerificationsTable
        ) {
            $table->uuid('id')->primary();
            $table->string('volunteer_code', 50)->unique();
            $table->uuid('administrative_unit_id')->index();
            $table->uuid('organization_id')->nullable()->index();
            $table->uuid('primary_category_id')->nullable()->index();
            $table->uuid('user_id')->nullable()->index();

            $table->string('full_name_en', 150)->index();
            $table->string('full_name_bn', 180)->index();
            $table->string('gender', 20)->nullable()->index(); // MALE, FEMALE, OTHER
            $table->date('date_of_birth')->nullable();
            $table->string('blood_group', 10)->nullable();

            // Protected PII (AES-256 encrypted + HMAC-SHA256 hash + masked defaults)
            $table->char('nid_hash', 64)->nullable()->unique();
            $table->text('nid_encrypted')->nullable();
            $table->string('nid_masked', 30)->nullable();
            $table->char('mobile_hash', 64)->nullable()->index();
            $table->text('mobile_encrypted');
            $table->string('mobile_masked', 30);
            $table->text('email_encrypted')->nullable();
            $table->string('email_masked', 150)->nullable();
            $table->string('present_address', 255)->nullable();
            $table->string('permanent_address', 255)->nullable();
            $table->string('ward_village', 120)->nullable();

            $table->string('emergency_contact_name', 150);
            $table->string('emergency_contact_relation', 60)->nullable();
            $table->text('emergency_contact_encrypted');
            $table->string('emergency_contact_masked', 30);

            // Private Photo Storage
            $table->string('photo_path', 255)->nullable();
            $table->string('photo_original_name', 255)->nullable();
            $table->string('photo_mime', 100)->nullable();
            $table->unsignedBigInteger('photo_size_bytes')->nullable();
            $table->char('photo_sha256', 64)->nullable();

            // 3-Pillar Verification & Approval Workflow
            $table->string('identity_verification_status', 30)->default('UNVERIFIED')->index();
            $table->uuid('nid_verification_id')->nullable()->index();
            $table->string('organization_verification_status', 30)->default('UNVERIFIED')->index();
            $table->string('training_verification_status', 30)->default('UNVERIFIED')->index();
            $table->string('overall_verification_status', 30)->default('DRAFT')->index(); // DRAFT, PENDING_VERIFICATION, VERIFIED, REJECTED
            $table->uuid('submitted_by')->nullable()->index();
            $table->timestamp('submitted_at')->nullable();
            $table->uuid('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->uuid('rejected_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();

            // Availability & Base Coordinates (consent-gated)
            $table->string('availability_status', 30)->default('AVAILABLE')->index(); // AVAILABLE, STANDBY, DEPLOYED, ON_LEAVE, UNAVAILABLE, INACTIVE
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('location_consent_given')->default(false);
            $table->text('notes')->nullable();

            $table->uuid('created_by')->nullable()->index();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('administrative_unit_id')->references('id')->on($adminUnitsTable)->onDelete('restrict');
            $table->foreign('organization_id')->references('id')->on($organizationsTable)->onDelete('set null');
            $table->foreign('primary_category_id')->references('id')->on($categoriesTable)->onDelete('set null');
            $table->foreign('user_id')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('nid_verification_id')->references('id')->on($nidVerificationsTable)->onDelete('set null');
            $table->foreign('submitted_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('verified_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('rejected_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('created_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on($usersTable)->onDelete('set null');
        });

        if ($isPgsql) {
            DB::statement("ALTER TABLE {$volunteersTable} ADD COLUMN location_geom geometry(Point, 4326) NULL;");
            DB::statement("CREATE INDEX idx_volunteers_location_geom ON {$volunteersTable} USING GIST (location_geom);");
        }

        // 6. Volunteer Skill Assignments
        Schema::create($skillAssignmentsTable, function (Blueprint $table) use ($volunteersTable, $skillCatalogTable) {
            $table->uuid('id')->primary();
            $table->uuid('volunteer_id')->index();
            $table->uuid('skill_id')->index();
            $table->string('proficiency_level', 30)->default('BASIC'); // BASIC, INTERMEDIATE, ADVANCED, INSTRUCTOR
            $table->string('certification_level', 60)->nullable();
            $table->string('certified_by', 150)->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('verification_status', 30)->default('PENDING')->index(); // PENDING, VERIFIED, REJECTED
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['volunteer_id', 'skill_id'], 'uq_volunteer_skill');
            $table->foreign('volunteer_id')->references('id')->on($volunteersTable)->onDelete('cascade');
            $table->foreign('skill_id')->references('id')->on($skillCatalogTable)->onDelete('restrict');
        });

        // 7. Immutable Volunteer Verification Audit Logs
        Schema::create($verificationLogsTable, function (Blueprint $table) use ($volunteersTable, $usersTable) {
            $table->uuid('id')->primary();
            $table->uuid('volunteer_id')->index();
            $table->string('pillar', 30)->index(); // IDENTITY, ORGANIZATION, TRAINING, OVERALL
            $table->string('action', 40)->index(); // SUBMITTED, PILLAR_VERIFIED, PILLAR_REJECTED, APPROVED, REJECTED
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->text('remarks')->nullable();
            $table->uuid('actor_user_id')->index();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('volunteer_id')->references('id')->on($volunteersTable)->onDelete('cascade');
            $table->foreign('actor_user_id')->references('id')->on($usersTable)->onDelete('restrict');
        });

        // 8. Volunteer Deployments
        Schema::create($deploymentsTable, function (Blueprint $table) use (
            $volunteersTable,
            $disasterEventsTable,
            $operationsTable,
            $teamsTable,
            $adminUnitsTable,
            $usersTable
        ) {
            $table->uuid('id')->primary();
            $table->string('deployment_no', 50)->unique();
            $table->uuid('volunteer_id')->index();
            $table->uuid('disaster_event_id')->nullable()->index();
            $table->uuid('response_operation_id')->nullable()->index();
            $table->uuid('response_team_id')->nullable()->index();
            $table->uuid('administrative_unit_id')->index();
            $table->string('deployment_location', 255);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('assigned_role', 100);
            $table->text('assigned_duties');
            $table->date('start_date')->index();
            $table->date('end_date')->nullable()->index();
            $table->date('actual_end_date')->nullable();
            $table->uuid('supervisor_user_id')->nullable()->index();
            $table->string('supervisor_name', 150);
            $table->string('supervisor_contact', 60)->nullable();
            $table->string('deployment_status', 30)->default('ASSIGNED')->index(); // ASSIGNED, ACTIVE, COMPLETED, CANCELLED
            $table->uuid('assigned_by')->index();
            $table->uuid('released_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('volunteer_id')->references('id')->on($volunteersTable)->onDelete('restrict');
            $table->foreign('disaster_event_id')->references('id')->on($disasterEventsTable)->onDelete('restrict');
            $table->foreign('response_operation_id')->references('id')->on($operationsTable)->onDelete('set null');
            $table->foreign('response_team_id')->references('id')->on($teamsTable)->onDelete('set null');
            $table->foreign('administrative_unit_id')->references('id')->on($adminUnitsTable)->onDelete('restrict');
            $table->foreign('supervisor_user_id')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('assigned_by')->references('id')->on($usersTable)->onDelete('restrict');
            $table->foreign('released_by')->references('id')->on($usersTable)->onDelete('set null');
        });

        if ($isPgsql) {
            DB::statement("ALTER TABLE {$deploymentsTable} ADD COLUMN location_geom geometry(Point, 4326) NULL;");
            DB::statement("CREATE INDEX idx_volunteer_deployments_geom ON {$deploymentsTable} USING GIST (location_geom);");
        }

        // 9. Volunteer Attendance, Activity Reports & Supervisor Confirmation
        Schema::create($attendancesTable, function (Blueprint $table) use (
            $deploymentsTable,
            $volunteersTable,
            $adminUnitsTable,
            $usersTable
        ) {
            $table->uuid('id')->primary();
            $table->uuid('deployment_id')->index();
            $table->uuid('volunteer_id')->index();
            $table->uuid('administrative_unit_id')->index();
            $table->date('attendance_date')->index();
            $table->timestamp('check_in_at')->index();
            $table->timestamp('check_out_at')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->boolean('gps_consent_given')->default(false);
            $table->decimal('check_in_lat', 10, 7)->nullable();
            $table->decimal('check_in_lng', 10, 7)->nullable();
            $table->decimal('check_out_lat', 10, 7)->nullable();
            $table->decimal('check_out_lng', 10, 7)->nullable();
            $table->text('activity_summary')->nullable();
            $table->text('tasks_completed')->nullable();
            $table->integer('beneficiaries_assisted')->default(0);
            $table->string('evidence_path', 255)->nullable();
            $table->string('evidence_original_name', 255)->nullable();
            $table->string('evidence_mime', 100)->nullable();
            $table->char('evidence_sha256', 64)->nullable();
            $table->string('confirmation_status', 30)->default('PENDING')->index(); // PENDING, CONFIRMED, DISPUTED
            $table->uuid('supervisor_confirmed_by')->nullable();
            $table->timestamp('supervisor_confirmed_at')->nullable();
            $table->text('supervisor_remarks')->nullable();
            $table->uuid('recorded_by')->index();
            $table->timestamps();

            $table->foreign('deployment_id')->references('id')->on($deploymentsTable)->onDelete('cascade');
            $table->foreign('volunteer_id')->references('id')->on($volunteersTable)->onDelete('restrict');
            $table->foreign('administrative_unit_id')->references('id')->on($adminUnitsTable)->onDelete('restrict');
            $table->foreign('supervisor_confirmed_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('recorded_by')->references('id')->on($usersTable)->onDelete('restrict');
        });

        // 10. Volunteer Training Programs
        Schema::create($programsTable, function (Blueprint $table) use (
            $categoriesTable,
            $skillCatalogTable,
            $organizationsTable,
            $usersTable
        ) {
            $table->uuid('id')->primary();
            $table->string('program_code', 50)->unique();
            $table->string('title_en', 180);
            $table->string('title_bn', 220);
            $table->uuid('category_id')->nullable()->index();
            $table->uuid('skill_id')->nullable()->index();
            $table->uuid('provider_organization_id')->nullable()->index();
            $table->string('provider_name', 180);
            $table->integer('duration_hours')->default(8);
            $table->integer('validity_months')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->uuid('created_by')->nullable()->index();
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on($categoriesTable)->onDelete('set null');
            $table->foreign('skill_id')->references('id')->on($skillCatalogTable)->onDelete('set null');
            $table->foreign('provider_organization_id')->references('id')->on($organizationsTable)->onDelete('set null');
            $table->foreign('created_by')->references('id')->on($usersTable)->onDelete('set null');
        });

        // 11. Volunteer Training Schedules / Sessions
        Schema::create($sessionsTable, function (Blueprint $table) use (
            $programsTable,
            $adminUnitsTable,
            $usersTable
        ) {
            $table->uuid('id')->primary();
            $table->string('session_code', 50)->unique();
            $table->uuid('program_id')->index();
            $table->uuid('administrative_unit_id')->index();
            $table->string('venue', 255);
            $table->date('start_date')->index();
            $table->date('end_date')->index();
            $table->string('trainer_name', 150);
            $table->string('trainer_contact', 60)->nullable();
            $table->integer('max_participants')->default(40);
            $table->string('status', 30)->default('SCHEDULED')->index(); // SCHEDULED, ONGOING, COMPLETED, CANCELLED
            $table->text('notes')->nullable();
            $table->uuid('created_by')->nullable()->index();
            $table->timestamps();

            $table->foreign('program_id')->references('id')->on($programsTable)->onDelete('restrict');
            $table->foreign('administrative_unit_id')->references('id')->on($adminUnitsTable)->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on($usersTable)->onDelete('set null');
        });

        // 12. Volunteer Training Attendance & Certificates
        Schema::create($enrollmentsTable, function (Blueprint $table) use (
            $sessionsTable,
            $volunteersTable,
            $usersTable
        ) {
            $table->uuid('id')->primary();
            $table->uuid('session_id')->index();
            $table->uuid('volunteer_id')->index();
            $table->string('enrollment_status', 30)->default('ENROLLED')->index(); // ENROLLED, ATTENDED, ABSENT, DROPPED
            $table->decimal('attendance_hours', 6, 2)->default(0);
            $table->decimal('assessment_score', 5, 2)->nullable();
            $table->boolean('passed')->default(false)->index();
            $table->string('certificate_no', 60)->nullable()->unique();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('certificate_path', 255)->nullable();
            $table->string('certificate_original_name', 255)->nullable();
            $table->string('certificate_mime', 100)->nullable();
            $table->char('certificate_sha256', 64)->nullable();
            $table->text('remarks')->nullable();
            $table->uuid('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['session_id', 'volunteer_id'], 'uq_vol_training_enrollment');
            $table->foreign('session_id')->references('id')->on($sessionsTable)->onDelete('cascade');
            $table->foreign('volunteer_id')->references('id')->on($volunteersTable)->onDelete('cascade');
            $table->foreign('verified_by')->references('id')->on($usersTable)->onDelete('set null');
        });
    }

    public function down(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';

        $tables = [
            $isPgsql ? 'volunteer.volunteer_training_enrollments' : 'volunteer_training_enrollments',
            $isPgsql ? 'volunteer.volunteer_training_sessions' : 'volunteer_training_sessions',
            $isPgsql ? 'volunteer.volunteer_training_programs' : 'volunteer_training_programs',
            $isPgsql ? 'volunteer.volunteer_attendances' : 'volunteer_attendances',
            $isPgsql ? 'volunteer.volunteer_deployments' : 'volunteer_deployments',
            $isPgsql ? 'volunteer.volunteer_verification_logs' : 'volunteer_verification_logs',
            $isPgsql ? 'volunteer.volunteer_skill_assignments' : 'volunteer_skill_assignments',
            $isPgsql ? 'volunteer.volunteers' : 'volunteers',
            $isPgsql ? 'volunteer.skill_catalog' : 'skill_catalog',
            $isPgsql ? 'volunteer.volunteer_organization_coverages' : 'volunteer_organization_coverages',
            $isPgsql ? 'volunteer.volunteer_organizations' : 'volunteer_organizations',
            $isPgsql ? 'volunteer.volunteer_categories' : 'volunteer_categories',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
    }
};
