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
        $tableName = DB::getDriverName() === 'pgsql' ? 'disaster.disaster_events' : 'disaster_events';
        $usersTable = DB::getDriverName() === 'pgsql' ? 'core.users' : 'users';

        Schema::create($tableName, function (Blueprint $table) use ($usersTable) {
            $table->uuid('id')->primary();
            $table->string('disaster_code', 30)->unique()->comment('Official code e.g. CYC-REMAL-2024');
            $table->string('name_en', 150)->index();
            $table->string('name_bn', 200);
            $table->string('disaster_type', 50)->index()->comment('CYCLONE, FLOOD, FLASH_FLOOD, EARTHQUAKE, EROSION, LANDSLIDE, FIRE, COLD_WAVE');
            $table->string('severity_level', 30)->index()->comment('ADVISORY, WARNING, DANGER, GREAT_DANGER, CATASTROPHIC');
            $table->string('lifecycle_phase', 30)->default('ACTIVE')->index()->comment('IMPENDING, ACTIVE, RESPONSE, RECOVERY, CLOSED');
            $table->timestamp('declaration_date')->index();
            $table->uuid('created_by')->nullable()->index();
            $table->timestamps();

            $table->foreign('created_by')
                ->references('id')
                ->on($usersTable)
                ->onDelete('set null');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE {$tableName} ADD COLUMN affected_zone_geom geometry(MultiPolygon, 4326) NULL;");
            DB::statement("CREATE INDEX idx_disaster_affected_zone_gist ON {$tableName} USING GIST (affected_zone_geom);");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = DB::getDriverName() === 'pgsql' ? 'disaster.disaster_events' : 'disaster_events';
        Schema::dropIfExists($tableName);
    }
};
