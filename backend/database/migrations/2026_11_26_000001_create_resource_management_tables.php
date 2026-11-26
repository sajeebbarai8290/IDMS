<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for Module 07: Resource Management.
     * Extends the shared resource.resource_assets table created in Module 05
     * without breaking existing response coordination integrations.
     */
    public function up(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';

        if ($isPgsql) {
            DB::statement('CREATE SCHEMA IF NOT EXISTS resource;');
        }

        $usersTable = $isPgsql ? 'core.users' : 'users';
        $adminUnitsTable = $isPgsql ? 'core.administrative_units' : 'administrative_units';
        $disasterEventsTable = $isPgsql ? 'disaster.disaster_events' : 'disaster_events';
        $operationsTable = $isPgsql ? 'response.response_operations' : 'response_operations';
        $teamsTable = $isPgsql ? 'response.response_teams' : 'response_teams';

        $categoriesTable = $isPgsql ? 'resource.resource_categories' : 'resource_categories';
        $assetsTable = $isPgsql ? 'resource.resource_assets' : 'resource_assets';
        $attachmentsTable = $isPgsql ? 'resource.resource_attachments' : 'resource_attachments';
        $allocationsTable = $isPgsql ? 'resource.resource_allocations' : 'resource_allocations';
        $maintenancesTable = $isPgsql ? 'resource.resource_maintenances' : 'resource_maintenances';
        $humanDeploymentsTable = $isPgsql ? 'resource.human_resource_deployments' : 'human_resource_deployments';

        // 1. Configurable Resource Categories
        Schema::create($categoriesTable, function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 60)->unique();
            $table->string('name_en', 120);
            $table->string('name_bn', 160);
            $table->text('description')->nullable();
            $table->string('icon', 60)->nullable();
            $table->string('color_hex', 20)->nullable();
            $table->boolean('is_human_resource')->default(false)->index();
            $table->boolean('is_system')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        // 2. Extend existing resource.resource_assets with full Module 07 registration metadata
        Schema::table($assetsTable, function (Blueprint $table) use ($categoriesTable, $usersTable) {
            $table->uuid('category_id')->nullable()->after('id')->index();
            $table->string('resource_type', 80)->nullable()->after('asset_type')->index();
            $table->text('description')->nullable()->after('name_bn');
            $table->json('specifications')->nullable()->after('capacity_info');
            $table->string('owning_organization', 180)->nullable()->after('specifications');
            $table->string('current_location', 255)->nullable()->after('owning_organization');
            $table->string('responsible_officer_name', 150)->nullable()->after('current_location');
            $table->string('responsible_officer_contact', 60)->nullable()->after('responsible_officer_name');
            $table->uuid('responsible_user_id')->nullable()->after('responsible_officer_contact')->index();
            $table->date('acquisition_date')->nullable()->after('responsible_user_id');
            $table->decimal('acquisition_value_bdt', 15, 2)->nullable()->after('acquisition_date');
            $table->date('next_maintenance_due_at')->nullable()->after('acquisition_value_bdt')->index();
            $table->uuid('created_by')->nullable()->after('is_active')->index();
            $table->uuid('updated_by')->nullable()->after('created_by');

            $table->foreign('category_id')->references('id')->on($categoriesTable)->onDelete('restrict');
            $table->foreign('responsible_user_id')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('created_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on($usersTable)->onDelete('set null');
        });

        if ($isPgsql) {
            DB::statement("ALTER TABLE {$assetsTable} ADD COLUMN location_geom geometry(Point, 4326) NULL;");
            DB::statement("CREATE INDEX idx_resource_assets_geom ON {$assetsTable} USING GIST (location_geom);");
        }

        // 3. Supporting Documents & Photographs for Resources
        Schema::create($attachmentsTable, function (Blueprint $table) use ($assetsTable, $usersTable) {
            $table->uuid('id')->primary();
            $table->uuid('resource_asset_id')->index();
            $table->string('attachment_kind', 30)->default('DOCUMENT')->index(); // DOCUMENT, PHOTOGRAPH
            $table->string('title', 160)->nullable();
            $table->string('original_name', 255);
            $table->string('storage_path', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size_bytes')->default(0);
            $table->char('sha256_hash', 64);
            $table->uuid('uploaded_by')->index();
            $table->timestamps();

            $table->foreign('resource_asset_id')->references('id')->on($assetsTable)->onDelete('cascade');
            $table->foreign('uploaded_by')->references('id')->on($usersTable)->onDelete('restrict');
        });

        // 4. Resource Allocations & Utilization History (linked to disasters & response operations)
        Schema::create($allocationsTable, function (Blueprint $table) use (
            $assetsTable,
            $disasterEventsTable,
            $operationsTable,
            $adminUnitsTable,
            $usersTable
        ) {
            $table->uuid('id')->primary();
            $table->uuid('resource_asset_id')->index();
            $table->uuid('disaster_event_id')->nullable()->index();
            $table->uuid('response_operation_id')->nullable()->index();
            $table->uuid('administrative_unit_id')->index();
            $table->string('allocation_no', 50)->unique();
            $table->string('allocation_location', 255);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->date('assignment_date')->index();
            $table->date('expected_return_date')->nullable()->index();
            $table->date('actual_return_date')->nullable();
            $table->string('responsible_officer_name', 150);
            $table->string('responsible_officer_contact', 60)->nullable();
            $table->uuid('responsible_user_id')->nullable()->index();
            $table->string('status', 30)->default('ASSIGNED')->index(); // ASSIGNED, IN_USE, RETURNED, CANCELLED
            $table->text('purpose')->nullable();
            $table->text('utilization_notes')->nullable();
            $table->text('return_condition_notes')->nullable();
            $table->uuid('allocated_by')->index();
            $table->uuid('released_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('resource_asset_id')->references('id')->on($assetsTable)->onDelete('restrict');
            $table->foreign('disaster_event_id')->references('id')->on($disasterEventsTable)->onDelete('restrict');
            $table->foreign('response_operation_id')->references('id')->on($operationsTable)->onDelete('set null');
            $table->foreign('administrative_unit_id')->references('id')->on($adminUnitsTable)->onDelete('restrict');
            $table->foreign('responsible_user_id')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('allocated_by')->references('id')->on($usersTable)->onDelete('restrict');
            $table->foreign('released_by')->references('id')->on($usersTable)->onDelete('set null');
        });

        if ($isPgsql) {
            DB::statement("ALTER TABLE {$allocationsTable} ADD COLUMN location_geom geometry(Point, 4326) NULL;");
            DB::statement("CREATE INDEX idx_resource_allocations_geom ON {$allocationsTable} USING GIST (location_geom);");
        }

        // 5. Maintenance Schedules, Records, Inspection Reports, Repair Costs & Reminders
        Schema::create($maintenancesTable, function (Blueprint $table) use ($assetsTable, $adminUnitsTable, $usersTable) {
            $table->uuid('id')->primary();
            $table->uuid('resource_asset_id')->index();
            $table->uuid('administrative_unit_id')->index();
            $table->string('maintenance_no', 50)->unique();
            $table->string('record_type', 30)->default('SCHEDULED')->index(); // SCHEDULED, PREVENTIVE, CORRECTIVE, INSPECTION, REPAIR
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->date('scheduled_date')->nullable()->index();
            $table->date('performed_date')->nullable();
            $table->date('next_due_date')->nullable()->index();
            $table->integer('reminder_days_before')->default(7);
            $table->text('inspection_findings')->nullable();
            $table->string('inspection_result', 30)->nullable()->index(); // PASS, CONDITIONAL, FAIL
            $table->decimal('repair_cost_bdt', 14, 2)->default(0);
            $table->string('vendor_or_workshop', 180)->nullable();
            $table->string('status', 30)->default('SCHEDULED')->index(); // SCHEDULED, IN_PROGRESS, COMPLETED, CANCELLED
            $table->string('performed_by_name', 150)->nullable();
            $table->string('attachment_path', 255)->nullable();
            $table->string('attachment_original_name', 255)->nullable();
            $table->string('attachment_mime', 100)->nullable();
            $table->unsignedBigInteger('attachment_size_bytes')->nullable();
            $table->string('attachment_sha256', 64)->nullable();
            $table->uuid('recorded_by')->index();
            $table->uuid('completed_by')->nullable();
            $table->timestamps();

            $table->foreign('resource_asset_id')->references('id')->on($assetsTable)->onDelete('cascade');
            $table->foreign('administrative_unit_id')->references('id')->on($adminUnitsTable)->onDelete('restrict');
            $table->foreign('recorded_by')->references('id')->on($usersTable)->onDelete('restrict');
            $table->foreign('completed_by')->references('id')->on($usersTable)->onDelete('set null');
        });

        // 6. Human Resource Allocation & Deployment History
        Schema::create($humanDeploymentsTable, function (Blueprint $table) use (
            $assetsTable,
            $usersTable,
            $teamsTable,
            $operationsTable,
            $disasterEventsTable,
            $adminUnitsTable
        ) {
            $table->uuid('id')->primary();
            $table->uuid('resource_asset_id')->nullable()->index();
            $table->uuid('user_id')->nullable()->index();
            $table->uuid('response_team_id')->nullable()->index();
            $table->uuid('response_operation_id')->nullable()->index();
            $table->uuid('disaster_event_id')->nullable()->index();
            $table->uuid('administrative_unit_id')->index();
            $table->string('deployment_no', 50)->unique();
            $table->string('personnel_name', 150);
            $table->string('personnel_designation', 120)->nullable();
            $table->string('personnel_contact', 60)->nullable();
            $table->text('assigned_duty');
            $table->string('deployment_location', 255)->nullable();
            $table->date('deployment_start_date')->index();
            $table->date('deployment_end_date')->nullable()->index();
            $table->date('actual_return_date')->nullable();
            $table->string('availability_status', 30)->default('ASSIGNED')->index(); // ASSIGNED, IN_USE, COMPLETED, CANCELLED
            $table->text('notes')->nullable();
            $table->uuid('assigned_by')->index();
            $table->uuid('released_by')->nullable();
            $table->timestamps();

            $table->foreign('resource_asset_id')->references('id')->on($assetsTable)->onDelete('set null');
            $table->foreign('user_id')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('response_team_id')->references('id')->on($teamsTable)->onDelete('set null');
            $table->foreign('response_operation_id')->references('id')->on($operationsTable)->onDelete('set null');
            $table->foreign('disaster_event_id')->references('id')->on($disasterEventsTable)->onDelete('restrict');
            $table->foreign('administrative_unit_id')->references('id')->on($adminUnitsTable)->onDelete('restrict');
            $table->foreign('assigned_by')->references('id')->on($usersTable)->onDelete('restrict');
            $table->foreign('released_by')->references('id')->on($usersTable)->onDelete('set null');
        });
    }

    public function down(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';

        $humanDeploymentsTable = $isPgsql ? 'resource.human_resource_deployments' : 'human_resource_deployments';
        $maintenancesTable = $isPgsql ? 'resource.resource_maintenances' : 'resource_maintenances';
        $allocationsTable = $isPgsql ? 'resource.resource_allocations' : 'resource_allocations';
        $attachmentsTable = $isPgsql ? 'resource.resource_attachments' : 'resource_attachments';
        $assetsTable = $isPgsql ? 'resource.resource_assets' : 'resource_assets';
        $categoriesTable = $isPgsql ? 'resource.resource_categories' : 'resource_categories';

        Schema::dropIfExists($humanDeploymentsTable);
        Schema::dropIfExists($maintenancesTable);
        Schema::dropIfExists($allocationsTable);
        Schema::dropIfExists($attachmentsTable);

        if (Schema::hasTable($assetsTable) && Schema::hasColumn($assetsTable, 'category_id')) {
            Schema::table($assetsTable, function (Blueprint $table) use ($isPgsql) {
                $table->dropForeign(['category_id']);
                $table->dropForeign(['responsible_user_id']);
                $table->dropForeign(['created_by']);
                $table->dropForeign(['updated_by']);
                $cols = [
                    'category_id',
                    'resource_type',
                    'description',
                    'specifications',
                    'owning_organization',
                    'current_location',
                    'responsible_officer_name',
                    'responsible_officer_contact',
                    'responsible_user_id',
                    'acquisition_date',
                    'acquisition_value_bdt',
                    'next_maintenance_due_at',
                    'created_by',
                    'updated_by',
                ];
                if ($isPgsql) {
                    $cols[] = 'location_geom';
                }
                $table->dropColumn($cols);
            });
        }

        Schema::dropIfExists($categoriesTable);
    }
};
