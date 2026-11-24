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
        $tableName = DB::getDriverName() === 'pgsql' ? 'core.administrative_units' : 'administrative_units';

        Schema::create($tableName, function (Blueprint $table) use ($tableName) {
            $table->uuid('id')->primary();
            $table->uuid('parent_id')->nullable()->index();
            $table->string('code', 20)->unique()->comment('BBS Official GeoCode');
            $table->string('name_en', 100)->index();
            $table->string('name_bn', 150);
            $table->string('tier_level', 30)->index()->comment('NATIONAL, DIRECTORATE, DIVISION, DISTRICT, UPAZILA, UNION, WARD');
            $table->string('ancestry_path', 255)->default('/')->index()->comment('Materialized path e.g. /40/47/12/35/');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        // Self-referencing foreign key constraint added after primary key is registered
        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            $table->foreign('parent_id')
                ->references('id')
                ->on($tableName)
                ->onDelete('restrict');
        });

        // Add PostGIS spatial columns and GIST index if running on PostgreSQL
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE {$tableName} ADD COLUMN boundary_geom geometry(MultiPolygon, 4326) NULL;");
            DB::statement("ALTER TABLE {$tableName} ADD COLUMN centroid_geom geometry(Point, 4326) NULL;");
            DB::statement("CREATE INDEX idx_admin_units_boundary_gist ON {$tableName} USING GIST (boundary_geom);");
            DB::statement("CREATE INDEX idx_admin_units_ancestry_pattern ON {$tableName} USING btree (ancestry_path varchar_pattern_ops);");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = DB::getDriverName() === 'pgsql' ? 'core.administrative_units' : 'administrative_units';
        Schema::dropIfExists($tableName);
    }
};
