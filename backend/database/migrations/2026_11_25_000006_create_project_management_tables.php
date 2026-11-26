<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for Module 06: Project Management.
     */
    public function up(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';

        if ($isPgsql) {
            DB::statement('CREATE SCHEMA IF NOT EXISTS project;');
        }

        $usersTable = $isPgsql ? 'core.users' : 'users';
        $adminUnitsTable = $isPgsql ? 'core.administrative_units' : 'administrative_units';
        $disasterEventsTable = $isPgsql ? 'disaster.disaster_events' : 'disaster_events';

        $categoriesTable = $isPgsql ? 'project.project_categories' : 'project_categories';
        $projectsTable = $isPgsql ? 'project.projects' : 'projects';
        $locationsTable = $isPgsql ? 'project.project_locations' : 'project_locations';
        $milestonesTable = $isPgsql ? 'project.project_milestones' : 'project_milestones';
        $disbursementsTable = $isPgsql ? 'project.project_disbursements' : 'project_disbursements';
        $monitoringTable = $isPgsql ? 'project.project_monitoring_reports' : 'project_monitoring_reports';
        $risksTable = $isPgsql ? 'project.project_risks' : 'project_risks';

        // 1. Configurable Project Categories
        Schema::create($categoriesTable, function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();
            $table->string('name_en', 120);
            $table->string('name_bn', 160);
            $table->text('description')->nullable();
            $table->string('color_hex', 20)->nullable();
            $table->boolean('is_system')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        // 2. Master Projects Registry
        Schema::create($projectsTable, function (Blueprint $table) use (
            $categoriesTable,
            $disasterEventsTable,
            $adminUnitsTable,
            $usersTable
        ) {
            $table->uuid('id')->primary();
            $table->uuid('category_id')->index();
            $table->uuid('disaster_event_id')->nullable()->index();
            $table->uuid('administrative_unit_id')->index();
            $table->string('project_code', 50)->unique();
            $table->string('reference_no', 80)->nullable()->unique();
            $table->string('title_en', 220);
            $table->string('title_bn', 260);
            $table->text('description')->nullable();
            $table->text('objectives')->nullable();
            $table->string('implementing_organization', 180);
            $table->string('project_manager_name', 150);
            $table->string('project_manager_contact', 60)->nullable();
            $table->uuid('project_manager_user_id')->nullable()->index();
            $table->string('funding_source', 150);
            $table->decimal('approved_budget_bdt', 15, 2);
            $table->decimal('revised_budget_bdt', 15, 2)->nullable();
            $table->decimal('released_funds_bdt', 15, 2)->default(0);
            $table->decimal('expenditure_bdt', 15, 2)->default(0);
            $table->decimal('physical_progress_pct', 5, 2)->default(0);
            $table->decimal('financial_progress_pct', 5, 2)->default(0);
            $table->date('start_date')->index();
            $table->date('expected_end_date')->index();
            $table->date('actual_end_date')->nullable();
            $table->string('ward_no', 40)->nullable();
            $table->string('location_summary', 255)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('boundary_geojson')->nullable();
            $table->string('status', 30)->default('PROPOSED')->index();
            $table->text('status_notes')->nullable();
            $table->boolean('is_delayed')->default(false)->index();
            $table->integer('delay_days')->default(0);
            $table->text('delay_reason')->nullable();
            $table->uuid('created_by')->index();
            $table->uuid('updated_by')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->uuid('closed_by')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('category_id')->references('id')->on($categoriesTable)->onDelete('restrict');
            $table->foreign('disaster_event_id')->references('id')->on($disasterEventsTable)->onDelete('restrict');
            $table->foreign('administrative_unit_id')->references('id')->on($adminUnitsTable)->onDelete('restrict');
            $table->foreign('project_manager_user_id')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('created_by')->references('id')->on($usersTable)->onDelete('restrict');
            $table->foreign('updated_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('reviewed_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('closed_by')->references('id')->on($usersTable)->onDelete('set null');
        });

        if ($isPgsql) {
            DB::statement("ALTER TABLE {$projectsTable} ADD COLUMN site_geom geometry(Point, 4326) NULL;");
            DB::statement("ALTER TABLE {$projectsTable} ADD COLUMN boundary_geom geometry(MultiPolygon, 4326) NULL;");
            DB::statement("CREATE INDEX idx_projects_site_gist ON {$projectsTable} USING GIST (site_geom);");
            DB::statement("CREATE INDEX idx_projects_boundary_gist ON {$projectsTable} USING GIST (boundary_geom);");
        }

        // 3. Multiple Geographical Locations per Project
        Schema::create($locationsTable, function (Blueprint $table) use ($projectsTable, $adminUnitsTable) {
            $table->uuid('id')->primary();
            $table->uuid('project_id')->index();
            $table->uuid('administrative_unit_id')->index();
            $table->string('ward_no', 40)->nullable();
            $table->string('site_name_en', 180)->nullable();
            $table->string('site_name_bn', 200)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('boundary_geojson')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on($projectsTable)->onDelete('cascade');
            $table->foreign('administrative_unit_id')->references('id')->on($adminUnitsTable)->onDelete('restrict');
        });

        if ($isPgsql) {
            DB::statement("ALTER TABLE {$locationsTable} ADD COLUMN location_geom geometry(Point, 4326) NULL;");
            DB::statement("CREATE INDEX idx_project_locations_gist ON {$locationsTable} USING GIST (location_geom);");
        }

        // 4. Project WBS Activities & Milestones
        Schema::create($milestonesTable, function (Blueprint $table) use ($projectsTable, $usersTable) {
            $table->uuid('id')->primary();
            $table->uuid('project_id')->index();
            $table->uuid('parent_milestone_id')->nullable()->index();
            $table->string('wbs_code', 40);
            $table->string('milestone_title', 200);
            $table->string('milestone_title_bn', 240)->nullable();
            $table->string('item_type', 20)->default('MILESTONE')->index();
            $table->text('deliverables')->nullable();
            $table->string('responsible_organization', 180)->nullable();
            $table->string('assigned_officer_name', 150)->nullable();
            $table->uuid('assigned_officer_user_id')->nullable()->index();
            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->decimal('weight_percentage', 5, 2)->default(10.00);
            $table->decimal('target_percentage', 5, 2)->default(100.00);
            $table->decimal('completion_percentage', 5, 2)->default(0.00);
            $table->string('approval_status', 30)->default('PENDING')->index();
            $table->text('delay_reason')->nullable();
            $table->text('certification_notes')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('certified_by')->nullable();
            $table->timestamp('certified_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'wbs_code'], 'uq_project_wbs_code');
            $table->foreign('project_id')->references('id')->on($projectsTable)->onDelete('cascade');
            $table->foreign('assigned_officer_user_id')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('certified_by')->references('id')->on($usersTable)->onDelete('set null');
        });

        // Self-referencing FK added after table creation for cross-driver compatibility
        Schema::table($milestonesTable, function (Blueprint $table) use ($milestonesTable) {
            $table->foreign('parent_milestone_id')->references('id')->on($milestonesTable)->onDelete('set null');
        });

        // 5. Financial Tracking Ledger (Budget Revisions, Fund Releases, Expenditures)
        Schema::create($disbursementsTable, function (Blueprint $table) use ($projectsTable, $milestonesTable, $usersTable) {
            $table->uuid('id')->primary();
            $table->uuid('project_id')->index();
            $table->uuid('project_milestone_id')->nullable()->index();
            $table->string('record_type', 30)->default('EXPENDITURE')->index();
            $table->string('voucher_no', 60)->unique();
            $table->string('fiscal_year', 20)->nullable();
            $table->decimal('amount_bdt', 15, 2);
            $table->string('payment_status', 30)->default('PENDING')->index();
            $table->text('description')->nullable();
            $table->string('supporting_doc_path', 255)->nullable();
            $table->string('supporting_doc_name', 255)->nullable();
            $table->string('supporting_doc_mime', 100)->nullable();
            $table->unsignedBigInteger('supporting_doc_size')->nullable();
            $table->string('supporting_doc_sha256', 64)->nullable();
            $table->timestamp('disbursed_at')->index();
            $table->uuid('created_by')->index();
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on($projectsTable)->onDelete('cascade');
            $table->foreign('project_milestone_id')->references('id')->on($milestonesTable)->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on($usersTable)->onDelete('restrict');
            $table->foreign('approved_by')->references('id')->on($usersTable)->onDelete('set null');
        });

        // 6. Monitoring Visit Reports (with geo-tagged field evidence)
        Schema::create($monitoringTable, function (Blueprint $table) use ($projectsTable, $adminUnitsTable, $usersTable) {
            $table->uuid('id')->primary();
            $table->uuid('project_id')->index();
            $table->uuid('administrative_unit_id')->nullable()->index();
            $table->string('report_no', 50)->unique();
            $table->date('visit_date')->index();
            $table->string('inspector_name', 150);
            $table->string('inspector_designation', 120)->nullable();
            $table->uuid('inspector_user_id')->index();
            $table->decimal('observed_physical_pct', 5, 2)->default(0);
            $table->decimal('observed_financial_pct', 5, 2)->nullable();
            $table->boolean('delay_observed')->default(false)->index();
            $table->integer('delay_days')->default(0);
            $table->text('delay_reason')->nullable();
            $table->text('findings');
            $table->text('recommendations')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('attachment_path', 255)->nullable();
            $table->string('attachment_original_name', 255)->nullable();
            $table->string('attachment_mime', 100)->nullable();
            $table->unsignedBigInteger('attachment_size_bytes')->nullable();
            $table->string('attachment_sha256', 64)->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on($projectsTable)->onDelete('cascade');
            $table->foreign('administrative_unit_id')->references('id')->on($adminUnitsTable)->onDelete('set null');
            $table->foreign('inspector_user_id')->references('id')->on($usersTable)->onDelete('restrict');
        });

        // 7. Project Risks & Issues Register
        Schema::create($risksTable, function (Blueprint $table) use ($projectsTable, $usersTable) {
            $table->uuid('id')->primary();
            $table->uuid('project_id')->index();
            $table->string('risk_code', 40);
            $table->string('record_kind', 20)->default('RISK')->index();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('severity', 20)->default('MEDIUM')->index();
            $table->string('likelihood', 20)->default('MEDIUM');
            $table->text('mitigation_plan')->nullable();
            $table->string('owner_name', 150)->nullable();
            $table->string('status', 20)->default('OPEN')->index();
            $table->date('identified_date')->index();
            $table->date('resolved_date')->nullable();
            $table->uuid('created_by')->index();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'risk_code'], 'uq_project_risk_code');
            $table->foreign('project_id')->references('id')->on($projectsTable)->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on($usersTable)->onDelete('restrict');
            $table->foreign('updated_by')->references('id')->on($usersTable)->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';

        Schema::dropIfExists($isPgsql ? 'project.project_risks' : 'project_risks');
        Schema::dropIfExists($isPgsql ? 'project.project_monitoring_reports' : 'project_monitoring_reports');
        Schema::dropIfExists($isPgsql ? 'project.project_disbursements' : 'project_disbursements');
        Schema::dropIfExists($isPgsql ? 'project.project_milestones' : 'project_milestones');
        Schema::dropIfExists($isPgsql ? 'project.project_locations' : 'project_locations');
        Schema::dropIfExists($isPgsql ? 'project.projects' : 'projects');
        Schema::dropIfExists($isPgsql ? 'project.project_categories' : 'project_categories');
    }
};
