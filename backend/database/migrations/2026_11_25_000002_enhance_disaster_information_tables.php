<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';

        $usersTable = $isPgsql ? 'core.users' : 'users';
        $adminUnitsTable = $isPgsql ? 'core.administrative_units' : 'administrative_units';

        $categoriesTable = $isPgsql ? 'disaster.disaster_categories' : 'disaster_categories';
        $eventsTable = $isPgsql ? 'disaster.disaster_events' : 'disaster_events';
        $locationsTable = $isPgsql ? 'disaster.disaster_locations' : 'disaster_locations';
        $impactsTable = $isPgsql ? 'disaster.disaster_impacts' : 'disaster_impacts';
        $warningsTable = $isPgsql ? 'disaster.disaster_warnings' : 'disaster_warnings';
        $timelinesTable = $isPgsql ? 'disaster.disaster_timelines' : 'disaster_timelines';

        // 1. Configurable Disaster Categories Catalog
        Schema::create($categoriesTable, function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();
            $table->string('name_en', 120);
            $table->string('name_bn', 150);
            $table->json('subtypes')->nullable();
            $table->string('icon', 60)->nullable();
            $table->string('color_hex', 20)->nullable();
            $table->boolean('is_system')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        // 2. Extend existing disaster_events table
        Schema::table($eventsTable, function (Blueprint $table) use ($categoriesTable, $usersTable) {
            $table->uuid('category_id')->nullable()->after('id')->index();
            $table->string('subtype', 80)->nullable()->after('disaster_type')->index();
            $table->text('description_en')->nullable()->after('name_bn');
            $table->text('description_bn')->nullable()->after('description_en');
            $table->date('start_date')->nullable()->after('declaration_date')->index();
            $table->date('end_date')->nullable()->after('start_date');
            $table->decimal('epicenter_lat', 10, 7)->nullable()->after('end_date');
            $table->decimal('epicenter_lng', 10, 7)->nullable()->after('epicenter_lat');
            $table->text('affected_areas_summary')->nullable()->after('epicenter_lng');
            $table->json('affected_zone_geojson')->nullable()->after('affected_areas_summary');
            $table->boolean('is_national')->default(false)->after('affected_zone_geojson')->index();
            $table->boolean('is_emergency_declared')->default(false)->after('is_national')->index();
            $table->uuid('updated_by')->nullable()->after('created_by')->index();
            $table->uuid('verified_by')->nullable()->after('updated_by')->index();
            $table->timestamp('verified_at')->nullable()->after('verified_by');
            $table->softDeletes();

            $table->foreign('category_id')
                ->references('id')
                ->on($categoriesTable)
                ->onDelete('set null');

            $table->foreign('updated_by')
                ->references('id')
                ->on($usersTable)
                ->onDelete('set null');

            $table->foreign('verified_by')
                ->references('id')
                ->on($usersTable)
                ->onDelete('set null');
        });

        if ($isPgsql) {
            DB::statement("ALTER TABLE {$eventsTable} ADD COLUMN location_geom geometry(Point, 4326) NULL;");
            DB::statement("CREATE INDEX idx_disaster_location_gist ON {$eventsTable} USING GIST (location_geom);");
        }

        // Normalize legacy lifecycle values on existing rows
        DB::table($eventsTable)->where('lifecycle_phase', 'IMPENDING')->update(['lifecycle_phase' => 'REPORTED']);
        DB::table($eventsTable)->where('lifecycle_phase', 'RESPONSE')->update(['lifecycle_phase' => 'RESPONSE_ONGOING']);

        // 3. Affected Geographical Locations (Multi-location & 7-tier scoping)
        Schema::create($locationsTable, function (Blueprint $table) use ($eventsTable, $adminUnitsTable) {
            $table->uuid('id')->primary();
            $table->uuid('disaster_event_id')->index();
            $table->uuid('administrative_unit_id')->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('boundary_geojson')->nullable();
            $table->string('severity_override', 30)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['disaster_event_id', 'administrative_unit_id'], 'uq_disaster_admin_unit');

            $table->foreign('disaster_event_id')
                ->references('id')
                ->on($eventsTable)
                ->onDelete('cascade');

            $table->foreign('administrative_unit_id')
                ->references('id')
                ->on($adminUnitsTable)
                ->onDelete('restrict');
        });

        if ($isPgsql) {
            DB::statement("ALTER TABLE {$locationsTable} ADD COLUMN location_geom geometry(Point, 4326) NULL;");
            DB::statement("CREATE INDEX idx_disaster_loc_point_gist ON {$locationsTable} USING GIST (location_geom);");
        }

        // 4. Disaster Impact Summary
        Schema::create($impactsTable, function (Blueprint $table) use ($eventsTable, $usersTable) {
            $table->uuid('id')->primary();
            $table->uuid('disaster_event_id')->unique();
            $table->integer('affected_population')->default(0);
            $table->integer('affected_households')->default(0);
            $table->integer('deaths_count')->default(0);
            $table->integer('injured_count')->default(0);
            $table->integer('missing_count')->default(0);
            $table->integer('damaged_houses_fully')->default(0);
            $table->integer('damaged_houses_partially')->default(0);
            $table->integer('damaged_infrastructure_count')->default(0);
            $table->decimal('agricultural_loss_acres', 12, 2)->default(0.00);
            $table->decimal('agricultural_loss_bdt', 15, 2)->default(0.00);
            $table->integer('livestock_loss_count')->default(0);
            $table->decimal('livestock_loss_bdt', 15, 2)->default(0.00);
            $table->decimal('total_economic_loss_bdt', 16, 2)->default(0.00);
            $table->text('remarks')->nullable();
            $table->uuid('updated_by')->nullable()->index();
            $table->timestamps();

            $table->foreign('disaster_event_id')
                ->references('id')
                ->on($eventsTable)
                ->onDelete('cascade');

            $table->foreign('updated_by')
                ->references('id')
                ->on($usersTable)
                ->onDelete('set null');
        });

        // 5. Early Warnings
        Schema::create($warningsTable, function (Blueprint $table) use ($eventsTable, $usersTable) {
            $table->uuid('id')->primary();
            $table->uuid('disaster_event_id')->index();
            $table->string('warning_code', 40)->unique();
            $table->string('warning_level', 30)->index();
            $table->string('title_en', 180);
            $table->string('title_bn', 220)->nullable();
            $table->text('message_en');
            $table->text('message_bn')->nullable();
            $table->string('issuing_authority', 150);
            $table->timestamp('issued_at')->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->json('affected_unit_ids')->nullable();
            $table->string('status', 30)->default('ACTIVE')->index();
            $table->string('notification_dispatch_status', 30)->default('QUEUED');
            $table->string('notification_reference', 100)->nullable();
            $table->uuid('issued_by')->nullable()->index();
            $table->timestamps();

            $table->foreign('disaster_event_id')
                ->references('id')
                ->on($eventsTable)
                ->onDelete('cascade');

            $table->foreign('issued_by')
                ->references('id')
                ->on($usersTable)
                ->onDelete('set null');
        });

        // 6. Disaster Timeline & Attachments
        Schema::create($timelinesTable, function (Blueprint $table) use ($eventsTable, $usersTable) {
            $table->uuid('id')->primary();
            $table->uuid('disaster_event_id')->index();
            $table->string('event_type', 40)->index();
            $table->string('from_phase', 30)->nullable();
            $table->string('to_phase', 30)->nullable();
            $table->string('title_en', 180);
            $table->string('title_bn', 220)->nullable();
            $table->text('notes')->nullable();
            $table->string('attachment_original_name', 255)->nullable();
            $table->string('attachment_path', 500)->nullable();
            $table->string('attachment_mime', 120)->nullable();
            $table->bigInteger('attachment_size_bytes')->nullable();
            $table->char('attachment_sha256', 64)->nullable();
            $table->uuid('recorded_by')->nullable()->index();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->foreign('disaster_event_id')
                ->references('id')
                ->on($eventsTable)
                ->onDelete('cascade');

            $table->foreign('recorded_by')
                ->references('id')
                ->on($usersTable)
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';

        $categoriesTable = $isPgsql ? 'disaster.disaster_categories' : 'disaster_categories';
        $eventsTable = $isPgsql ? 'disaster.disaster_events' : 'disaster_events';
        $locationsTable = $isPgsql ? 'disaster.disaster_locations' : 'disaster_locations';
        $impactsTable = $isPgsql ? 'disaster.disaster_impacts' : 'disaster_impacts';
        $warningsTable = $isPgsql ? 'disaster.disaster_warnings' : 'disaster_warnings';
        $timelinesTable = $isPgsql ? 'disaster.disaster_timelines' : 'disaster_timelines';

        Schema::dropIfExists($timelinesTable);
        Schema::dropIfExists($warningsTable);
        Schema::dropIfExists($impactsTable);
        Schema::dropIfExists($locationsTable);

        Schema::table($eventsTable, function (Blueprint $table) use ($isPgsql) {
            $table->dropForeign(['category_id']);
            $table->dropForeign(['updated_by']);
            $table->dropForeign(['verified_by']);

            $cols = [
                'category_id',
                'subtype',
                'description_en',
                'description_bn',
                'start_date',
                'end_date',
                'epicenter_lat',
                'epicenter_lng',
                'affected_areas_summary',
                'affected_zone_geojson',
                'is_national',
                'is_emergency_declared',
                'updated_by',
                'verified_by',
                'verified_at',
                'deleted_at',
            ];

            if ($isPgsql) {
                $cols[] = 'location_geom';
            }

            $table->dropColumn($cols);
        });

        Schema::dropIfExists($categoriesTable);
    }
};
