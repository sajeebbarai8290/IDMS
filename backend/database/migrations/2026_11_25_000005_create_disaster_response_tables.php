<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';

        if ($isPgsql) {
            DB::statement('CREATE SCHEMA IF NOT EXISTS response;');
            DB::statement('CREATE SCHEMA IF NOT EXISTS resource;');
        }

        $usersTable = $isPgsql ? 'core.users' : 'users';
        $adminUnitsTable = $isPgsql ? 'core.administrative_units' : 'administrative_units';
        $disasterEventsTable = $isPgsql ? 'disaster.disaster_events' : 'disaster_events';

        $activityTypesTable = $isPgsql ? 'response.response_activity_types' : 'response_activity_types';
        $centersTable = $isPgsql ? 'response.response_centers' : 'response_centers';
        $assetsTable = $isPgsql ? 'resource.resource_assets' : 'resource_assets';
        $operationsTable = $isPgsql ? 'response.response_operations' : 'response_operations';
        $agenciesTable = $isPgsql ? 'response.response_operation_agencies' : 'response_operation_agencies';
        $teamsTable = $isPgsql ? 'response.response_teams' : 'response_teams';
        $tasksTable = $isPgsql ? 'response.response_tasks' : 'response_tasks';
        $attachmentsTable = $isPgsql ? 'response.response_task_attachments' : 'response_task_attachments';
        $resourcesTable = $isPgsql ? 'response.response_resource_allocations' : 'response_resource_allocations';
        $sitrepsTable = $isPgsql ? 'response.response_situation_reports' : 'response_situation_reports';

        // 1. Configurable Response Activity Types
        Schema::create($activityTypesTable, function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();
            $table->string('name_en', 120);
            $table->string('name_bn', 160);
            $table->text('description')->nullable();
            $table->string('icon', 60)->nullable();
            $table->string('color_hex', 20)->nullable();
            $table->boolean('is_system')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        // 2. Response Centers / EOCs
        Schema::create($centersTable, function (Blueprint $table) use ($adminUnitsTable) {
            $table->uuid('id')->primary();
            $table->uuid('administrative_unit_id')->index();
            $table->string('center_code', 30)->unique();
            $table->string('center_type', 40)->default('EOC')->index();
            $table->string('name_en', 150);
            $table->string('name_bn', 200);
            $table->text('address')->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('administrative_unit_id')->references('id')->on($adminUnitsTable)->onDelete('restrict');
        });

        if ($isPgsql) {
            DB::statement("ALTER TABLE {$centersTable} ADD COLUMN location_geom geometry(Point, 4326) NULL;");
            DB::statement("CREATE INDEX idx_response_centers_geom ON {$centersTable} USING GIST (location_geom);");
        }

        // 3. Deployable Vehicles & Emergency Assets (shared core resource foundation)
        Schema::create($assetsTable, function (Blueprint $table) use ($adminUnitsTable) {
            $table->uuid('id')->primary();
            $table->uuid('administrative_unit_id')->index();
            $table->string('asset_code', 40)->unique();
            $table->string('asset_type', 50)->index();
            $table->string('name_en', 150);
            $table->string('name_bn', 200);
            $table->string('registration_no', 60)->nullable();
            $table->string('capacity_info', 120)->nullable();
            $table->string('status', 30)->default('AVAILABLE')->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('administrative_unit_id')->references('id')->on($adminUnitsTable)->onDelete('restrict');
        });

        // 4. Response Operations (6-state lifecycle)
        Schema::create($operationsTable, function (Blueprint $table) use (
            $disasterEventsTable,
            $adminUnitsTable,
            $centersTable,
            $usersTable
        ) {
            $table->uuid('id')->primary();
            $table->uuid('disaster_event_id')->index();
            $table->uuid('administrative_unit_id')->index();
            $table->uuid('response_center_id')->nullable()->index();
            $table->string('operation_no', 50)->unique();
            $table->string('title_en', 180);
            $table->string('title_bn', 220)->nullable();
            $table->text('objectives')->nullable();
            $table->text('description')->nullable();
            $table->string('priority', 20)->default('HIGH')->index();
            $table->string('severity_level', 30)->default('WARNING')->index();
            $table->string('status', 30)->default('PLANNED')->index();
            $table->date('start_date')->index();
            $table->date('expected_completion_date')->nullable();
            $table->date('actual_completion_date')->nullable();
            $table->string('lead_organization', 160);
            $table->string('commander_name', 150)->nullable();
            $table->uuid('commander_user_id')->nullable()->index();
            $table->text('status_notes')->nullable();
            $table->uuid('created_by')->index();
            $table->uuid('updated_by')->nullable();
            $table->uuid('activated_by')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->uuid('closed_by')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('disaster_event_id')->references('id')->on($disasterEventsTable)->onDelete('restrict');
            $table->foreign('administrative_unit_id')->references('id')->on($adminUnitsTable)->onDelete('restrict');
            $table->foreign('response_center_id')->references('id')->on($centersTable)->onDelete('set null');
            $table->foreign('commander_user_id')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('created_by')->references('id')->on($usersTable)->onDelete('restrict');
            $table->foreign('updated_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('activated_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('closed_by')->references('id')->on($usersTable)->onDelete('set null');
        });

        // 5. Participating / Coordinating Government Agencies & Organizations
        Schema::create($agenciesTable, function (Blueprint $table) use ($operationsTable, $usersTable) {
            $table->uuid('id')->primary();
            $table->uuid('response_operation_id')->index();
            $table->string('agency_name', 160);
            $table->string('agency_type', 50)->default('GOVERNMENT')->index();
            $table->string('role_summary', 200)->nullable();
            $table->string('focal_person_name', 120)->nullable();
            $table->string('focal_contact', 60)->nullable();
            $table->string('coordination_status', 30)->default('ACTIVE')->index();
            $table->uuid('added_by')->nullable();
            $table->timestamps();

            $table->foreign('response_operation_id')->references('id')->on($operationsTable)->onDelete('cascade');
            $table->foreign('added_by')->references('id')->on($usersTable)->onDelete('set null');
        });

        // 6. Participating Response Teams
        Schema::create($teamsTable, function (Blueprint $table) use ($operationsTable, $adminUnitsTable, $usersTable) {
            $table->uuid('id')->primary();
            $table->uuid('response_operation_id')->index();
            $table->uuid('administrative_unit_id')->index();
            $table->string('team_code', 40)->unique();
            $table->string('team_name', 150);
            $table->string('agency_name', 150);
            $table->string('team_type', 40)->index();
            $table->string('leader_name', 120)->nullable();
            $table->string('leader_contact', 50)->nullable();
            $table->uuid('leader_user_id')->nullable();
            $table->integer('member_count')->default(1);
            $table->string('status', 30)->default('STANDBY')->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('last_checkin_at')->nullable();
            $table->timestamps();

            $table->foreign('response_operation_id')->references('id')->on($operationsTable)->onDelete('cascade');
            $table->foreign('administrative_unit_id')->references('id')->on($adminUnitsTable)->onDelete('restrict');
            $table->foreign('leader_user_id')->references('id')->on($usersTable)->onDelete('set null');
        });

        if ($isPgsql) {
            DB::statement("ALTER TABLE {$teamsTable} ADD COLUMN location_geom geometry(Point, 4326) NULL;");
            DB::statement("CREATE INDEX idx_response_teams_geom ON {$teamsTable} USING GIST (location_geom);");
        }

        // 7. Response Tasks
        Schema::create($tasksTable, function (Blueprint $table) use (
            $operationsTable,
            $activityTypesTable,
            $teamsTable,
            $usersTable
        ) {
            $table->uuid('id')->primary();
            $table->uuid('response_operation_id')->index();
            $table->uuid('activity_type_id')->index();
            $table->uuid('response_team_id')->nullable()->index();
            $table->uuid('assigned_user_id')->nullable()->index();
            $table->string('task_code', 40)->unique();
            $table->string('title_en', 180);
            $table->string('title_bn', 220)->nullable();
            $table->text('description')->nullable();
            $table->string('assigned_officer_name', 140)->nullable();
            $table->string('priority', 20)->default('MEDIUM')->index();
            $table->string('status', 30)->default('PENDING')->index();
            $table->unsignedTinyInteger('progress_percentage')->default(0);
            $table->timestamp('deadline_at')->nullable()->index();
            $table->text('completion_notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->uuid('created_by')->index();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('response_operation_id')->references('id')->on($operationsTable)->onDelete('cascade');
            $table->foreign('activity_type_id')->references('id')->on($activityTypesTable)->onDelete('restrict');
            $table->foreign('response_team_id')->references('id')->on($teamsTable)->onDelete('set null');
            $table->foreign('assigned_user_id')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('created_by')->references('id')->on($usersTable)->onDelete('restrict');
            $table->foreign('updated_by')->references('id')->on($usersTable)->onDelete('set null');
        });

        // 8. Private Task Evidence & Report Attachments
        Schema::create($attachmentsTable, function (Blueprint $table) use ($tasksTable, $usersTable) {
            $table->uuid('id')->primary();
            $table->uuid('response_task_id')->index();
            $table->string('title', 160)->nullable();
            $table->string('original_name', 255);
            $table->string('storage_path', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size_bytes')->default(0);
            $table->char('sha256_hash', 64);
            $table->uuid('uploaded_by')->index();
            $table->timestamps();

            $table->foreign('response_task_id')->references('id')->on($tasksTable)->onDelete('cascade');
            $table->foreign('uploaded_by')->references('id')->on($usersTable)->onDelete('restrict');
        });

        // 9. Resource Requirements & Linked Vehicles / Equipment
        Schema::create($resourcesTable, function (Blueprint $table) use ($operationsTable, $assetsTable, $usersTable) {
            $table->uuid('id')->primary();
            $table->uuid('response_operation_id')->index();
            $table->uuid('resource_asset_id')->nullable()->index();
            $table->string('requirement_title', 180);
            $table->string('category', 60)->default('VEHICLE_EQUIPMENT');
            $table->decimal('quantity_needed', 12, 2)->default(1);
            $table->decimal('quantity_assigned', 12, 2)->default(0);
            $table->string('unit', 30)->default('UNIT');
            $table->string('priority', 20)->default('HIGH')->index();
            $table->string('status', 30)->default('REQUESTED')->index();
            $table->text('notes')->nullable();
            $table->uuid('requested_by')->index();
            $table->timestamps();

            $table->foreign('response_operation_id')->references('id')->on($operationsTable)->onDelete('cascade');
            $table->foreign('resource_asset_id')->references('id')->on($assetsTable)->onDelete('set null');
            $table->foreign('requested_by')->references('id')->on($usersTable)->onDelete('restrict');
        });

        // 10. Emergency Situation & Incident Reports
        Schema::create($sitrepsTable, function (Blueprint $table) use (
            $operationsTable,
            $disasterEventsTable,
            $adminUnitsTable,
            $usersTable
        ) {
            $table->uuid('id')->primary();
            $table->uuid('response_operation_id')->index();
            $table->uuid('disaster_event_id')->index();
            $table->uuid('administrative_unit_id')->index();
            $table->string('report_no', 50)->unique();
            $table->string('report_type', 30)->default('FIELD_UPDATE')->index();
            $table->string('severity_level', 30)->default('WARNING')->index();
            $table->string('title_en', 180);
            $table->string('title_bn', 220)->nullable();
            $table->text('summary');
            $table->integer('casualties_injured')->default(0);
            $table->integer('casualties_missing')->default(0);
            $table->integer('casualties_fatalities')->default(0);
            $table->integer('rescued_count')->default(0);
            $table->integer('evacuated_count')->default(0);
            $table->text('emergency_requirements')->nullable();
            $table->string('location_description', 255)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('privacy_level', 30)->default('PUBLIC_AREA')->index();
            $table->uuid('reported_by')->index();
            $table->timestamp('reported_at')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('response_operation_id')->references('id')->on($operationsTable)->onDelete('cascade');
            $table->foreign('disaster_event_id')->references('id')->on($disasterEventsTable)->onDelete('restrict');
            $table->foreign('administrative_unit_id')->references('id')->on($adminUnitsTable)->onDelete('restrict');
            $table->foreign('reported_by')->references('id')->on($usersTable)->onDelete('restrict');
        });

        if ($isPgsql) {
            DB::statement("ALTER TABLE {$sitrepsTable} ADD COLUMN location_geom geometry(Point, 4326) NULL;");
            DB::statement("CREATE INDEX idx_response_sitreps_geom ON {$sitrepsTable} USING GIST (location_geom);");
        }
    }

    public function down(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';

        $tables = [
            $isPgsql ? 'response.response_situation_reports' : 'response_situation_reports',
            $isPgsql ? 'response.response_resource_allocations' : 'response_resource_allocations',
            $isPgsql ? 'response.response_task_attachments' : 'response_task_attachments',
            $isPgsql ? 'response.response_tasks' : 'response_tasks',
            $isPgsql ? 'response.response_teams' : 'response_teams',
            $isPgsql ? 'response.response_operation_agencies' : 'response_operation_agencies',
            $isPgsql ? 'response.response_operations' : 'response_operations',
            $isPgsql ? 'resource.resource_assets' : 'resource_assets',
            $isPgsql ? 'response.response_centers' : 'response_centers',
            $isPgsql ? 'response.response_activity_types' : 'response_activity_types',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
    }
};
