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
        $tableName = DB::getDriverName() === 'pgsql' ? 'audit.audit_logs' : 'audit_logs';
        $usersTable = DB::getDriverName() === 'pgsql' ? 'core.users' : 'users';
        $adminUnitsTable = DB::getDriverName() === 'pgsql' ? 'core.administrative_units' : 'administrative_units';

        Schema::create($tableName, function (Blueprint $table) use ($usersTable, $adminUnitsTable) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable()->index();
            $table->string('action_name', 100)->index()->comment('e.g. BENEFICIARY_REGISTERED, PII_DECRYPTION_ACCESSED');
            $table->string('auditable_type', 150)->index();
            $table->uuid('auditable_id')->nullable()->index();
            $table->uuid('administrative_unit_id')->nullable()->index();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->index();

            $table->foreign('user_id')
                ->references('id')
                ->on($usersTable)
                ->onDelete('set null');

            $table->foreign('administrative_unit_id')
                ->references('id')
                ->on($adminUnitsTable)
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = DB::getDriverName() === 'pgsql' ? 'audit.audit_logs' : 'audit_logs';
        Schema::dropIfExists($tableName);
    }
};
