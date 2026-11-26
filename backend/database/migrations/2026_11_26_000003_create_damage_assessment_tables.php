<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for Module 09: Damage Assessment (D-Form).
     */
    public function up(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';

        if ($isPgsql) {
            DB::statement('CREATE SCHEMA IF NOT EXISTS damage;');
        }

        $usersTable = $isPgsql ? 'core.users' : 'users';
        $adminUnitsTable = $isPgsql ? 'core.administrative_units' : 'administrative_units';
        $disasterEventsTable = $isPgsql ? 'disaster.disaster_events' : 'disaster_events';

        $categoriesTable = $isPgsql ? 'damage.damage_categories' : 'damage_categories';
        $assessmentsTable = $isPgsql ? 'damage.damage_assessments' : 'damage_assessments';
        $locationsTable = $isPgsql ? 'damage.damage_locations' : 'damage_locations';
        $itemsTable = $isPgsql ? 'damage.damage_sector_items' : 'damage_sector_items';
        $attachmentsTable = $isPgsql ? 'damage.damage_attachments' : 'damage_attachments';
        $logsTable = $isPgsql ? 'damage.damage_verification_logs' : 'damage_verification_logs';

        // 1. Configurable Damage Categories
        Schema::create($categoriesTable, function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();
            $table->string('name_en', 120);
            $table->string('name_bn', 160);
            $table->text('description')->nullable();
            $table->string('default_unit', 40)->default('UNITS');
            $table->string('color_hex', 20)->nullable();
            $table->integer('sort_order')->default(100);
            $table->boolean('is_system')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        // 2. Master Damage Assessments (D-Form + Offline Sync + Spatial)
        Schema::create($assessmentsTable, function (Blueprint $table) use (
            $disasterEventsTable,
            $adminUnitsTable,
            $usersTable
        ) {
            $table->uuid('id')->primary();
            $table->uuid('disaster_event_id')->index();
            $table->uuid('administrative_unit_id')->index();
            $table->string('d_form_code', 50)->unique();
            $table->string('reference_no', 80)->nullable()->unique();
            $table->string('assessment_type', 40)->default('PRELIMINARY_D_FORM')->index();
            $table->date('assessment_date')->index();
            $table->timestamp('assessed_at')->nullable()->index();
            $table->string('assessing_organization', 180);
            $table->string('team_name', 150)->nullable();
            $table->string('team_lead_name', 150)->nullable();
            $table->json('team_members')->nullable();
            $table->uuid('assessor_user_id')->nullable()->index();
            $table->string('assessor_name', 150);
            $table->string('assessor_designation', 120)->nullable();
            $table->string('assessor_contact', 60)->nullable();
            $table->string('ward_no', 40)->nullable();
            $table->string('location_summary', 255)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('boundary_geojson')->nullable();
            $table->string('severity_level', 30)->default('MODERATE')->index();
            $table->integer('affected_households')->default(0);
            $table->integer('affected_population')->default(0);
            $table->integer('displaced_population')->default(0);
            $table->integer('deaths_count')->default(0);
            $table->integer('injured_count')->default(0);
            $table->integer('missing_count')->default(0);
            $table->integer('total_fully_damaged')->default(0);
            $table->integer('total_partially_damaged')->default(0);
            $table->integer('total_minor_damaged')->default(0);
            $table->decimal('total_estimated_loss_bdt', 15, 2)->default(0)->index();
            $table->text('field_observations')->nullable();
            $table->string('verification_status', 30)->default('DRAFT')->index();
            $table->text('correction_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->uuid('submitted_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->uuid('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->uuid('rejected_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->uuid('created_by')->index();
            $table->uuid('updated_by')->nullable();

            // Offline synchronization compatibility for future Flutter mobile app
            $table->string('client_uuid', 64)->nullable()->unique();
            $table->string('idempotency_key', 80)->nullable()->unique();
            $table->string('device_id', 100)->nullable();
            $table->timestamp('offline_created_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->string('sync_status', 25)->default('ONLINE')->index();
            $table->integer('sync_version')->default(1);

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('disaster_event_id')->references('id')->on($disasterEventsTable)->onDelete('restrict');
            $table->foreign('administrative_unit_id')->references('id')->on($adminUnitsTable)->onDelete('restrict');
            $table->foreign('assessor_user_id')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('submitted_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('verified_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('rejected_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('created_by')->references('id')->on($usersTable)->onDelete('restrict');
            $table->foreign('updated_by')->references('id')->on($usersTable)->onDelete('set null');
        });

        if ($isPgsql) {
            DB::statement("ALTER TABLE {$assessmentsTable} ADD COLUMN location_geom geometry(Point, 4326) NULL;");
            DB::statement("ALTER TABLE {$assessmentsTable} ADD COLUMN damage_area_geom geometry(MultiPolygon, 4326) NULL;");
            DB::statement("CREATE INDEX idx_damage_assessments_point_gist ON {$assessmentsTable} USING GIST (location_geom);");
            DB::statement("CREATE INDEX idx_damage_assessments_area_gist ON {$assessmentsTable} USING GIST (damage_area_geom);");
        }

        // 3. Multiple Geographical Coverage Locations per Assessment
        Schema::create($locationsTable, function (Blueprint $table) use ($assessmentsTable, $adminUnitsTable) {
            $table->uuid('id')->primary();
            $table->uuid('damage_assessment_id')->index();
            $table->uuid('administrative_unit_id')->index();
            $table->string('ward_no', 40)->nullable();
            $table->string('locality_name_en', 180)->nullable();
            $table->string('locality_name_bn', 200)->nullable();
            $table->integer('affected_households')->default(0);
            $table->integer('affected_population')->default(0);
            $table->string('severity_level', 30)->default('MODERATE');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('damage_assessment_id')->references('id')->on($assessmentsTable)->onDelete('cascade');
            $table->foreign('administrative_unit_id')->references('id')->on($adminUnitsTable)->onDelete('restrict');
        });

        if ($isPgsql) {
            DB::statement("ALTER TABLE {$locationsTable} ADD COLUMN location_geom geometry(Point, 4326) NULL;");
            DB::statement("CREATE INDEX idx_damage_locations_gist ON {$locationsTable} USING GIST (location_geom);");
        }

        // 4. Sectoral Damage Category Line Items
        Schema::create($itemsTable, function (Blueprint $table) use ($assessmentsTable, $categoriesTable) {
            $table->uuid('id')->primary();
            $table->uuid('damage_assessment_id')->index();
            $table->uuid('category_id')->index();
            $table->string('sector_code', 50)->index();
            $table->string('item_title', 180)->nullable();
            $table->string('unit_of_measure', 40)->default('UNITS');
            $table->integer('fully_damaged_count')->default(0);
            $table->integer('partially_damaged_count')->default(0);
            $table->integer('minor_damaged_count')->default(0);
            $table->decimal('damaged_quantity', 12, 2)->default(0);
            $table->integer('affected_households')->default(0);
            $table->integer('affected_population')->default(0);
            $table->decimal('estimated_loss_bdt', 14, 2)->default(0);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->foreign('damage_assessment_id')->references('id')->on($assessmentsTable)->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on($categoriesTable)->onDelete('restrict');
        });

        if ($isPgsql) {
            DB::statement("ALTER TABLE {$itemsTable} ADD COLUMN location_geom geometry(Point, 4326) NULL;");
            DB::statement("CREATE INDEX idx_damage_items_gist ON {$itemsTable} USING GIST (location_geom);");
        }

        // 5. Geo-tagged Supporting Photographs & Documents
        Schema::create($attachmentsTable, function (Blueprint $table) use ($assessmentsTable, $itemsTable, $usersTable) {
            $table->uuid('id')->primary();
            $table->uuid('damage_assessment_id')->index();
            $table->uuid('damage_sector_item_id')->nullable()->index();
            $table->string('attachment_kind', 25)->default('PHOTOGRAPH')->index();
            $table->string('title', 180)->nullable();
            $table->string('caption', 255)->nullable();
            $table->string('original_name', 255);
            $table->string('storage_path', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size_bytes');
            $table->string('sha256_hash', 64);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->uuid('uploaded_by')->index();
            $table->timestamps();

            $table->foreign('damage_assessment_id')->references('id')->on($assessmentsTable)->onDelete('cascade');
            $table->foreign('damage_sector_item_id')->references('id')->on($itemsTable)->onDelete('set null');
            $table->foreign('uploaded_by')->references('id')->on($usersTable)->onDelete('restrict');
        });

        // 6. Immutable Verification & Workflow Transition History
        Schema::create($logsTable, function (Blueprint $table) use ($assessmentsTable, $usersTable) {
            $table->uuid('id')->primary();
            $table->uuid('damage_assessment_id')->index();
            $table->string('action', 40)->index();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->uuid('actor_id')->index();
            $table->string('actor_name', 150);
            $table->string('actor_role', 60)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->foreign('damage_assessment_id')->references('id')->on($assessmentsTable)->onDelete('cascade');
            $table->foreign('actor_id')->references('id')->on($usersTable)->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';

        $logsTable = $isPgsql ? 'damage.damage_verification_logs' : 'damage_verification_logs';
        $attachmentsTable = $isPgsql ? 'damage.damage_attachments' : 'damage_attachments';
        $itemsTable = $isPgsql ? 'damage.damage_sector_items' : 'damage_sector_items';
        $locationsTable = $isPgsql ? 'damage.damage_locations' : 'damage_locations';
        $assessmentsTable = $isPgsql ? 'damage.damage_assessments' : 'damage_assessments';
        $categoriesTable = $isPgsql ? 'damage.damage_categories' : 'damage_categories';

        Schema::dropIfExists($logsTable);
        Schema::dropIfExists($attachmentsTable);
        Schema::dropIfExists($itemsTable);
        Schema::dropIfExists($locationsTable);
        Schema::dropIfExists($assessmentsTable);
        Schema::dropIfExists($categoriesTable);
    }
};
