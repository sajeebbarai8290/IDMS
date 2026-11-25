<?php

use Database\Seeders\DisasterEventSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill categories, coordinates, GeoJSON boundaries, and PostGIS geometries
     * for pre-existing seeded disaster events.
     */
    public function up(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';
        $eventsTable = $isPgsql ? 'disaster.disaster_events' : 'disaster_events';

        // Only backfill if seeded rows exist without coordinates/national scope
        if (DB::table($eventsTable)->whereIn('disaster_code', ['CYC-REMAL-2024', 'FLD-MONSOON-2024', 'COLD-WAVE-2025'])->exists()) {
            (new DisasterEventSeeder())->run();
        }
    }

    public function down(): void
    {
        // No-op: non-destructive data backfill
    }
};
