<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp";');
            DB::statement('CREATE EXTENSION IF NOT EXISTS postgis;');

            // Initialize sovereign database schemas
            $schemas = [
                'core',
                'beneficiary',
                'disaster',
                'damage',
                'logistics',
                'distribution',
                'project',
                'volunteer',
                'workflow',
                'notification',
                'document',
                'audit',
            ];

            foreach ($schemas as $schema) {
                DB::statement("CREATE SCHEMA IF NOT EXISTS {$schema};");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $schemas = [
                'audit',
                'document',
                'notification',
                'workflow',
                'volunteer',
                'project',
                'distribution',
                'logistics',
                'damage',
                'disaster',
                'beneficiary',
                'core',
            ];

            foreach ($schemas as $schema) {
                DB::statement("DROP SCHEMA IF EXISTS {$schema} CASCADE;");
            }
        }
    }
};
