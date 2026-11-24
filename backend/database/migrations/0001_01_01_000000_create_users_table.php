<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates sovereign user registry synchronized with Keycloak IAM.
     * No password hashes or credentials are stored in Laravel.
     */
    public function up(): void
    {
        $tableName = DB::getDriverName() === 'pgsql' ? 'core.users' : 'users';

        Schema::create($tableName, function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('keycloak_sub')->unique()->comment('Keycloak OIDC Subject UUID');
            $table->string('email')->unique();
            $table->string('mobile_number', 20)->nullable();
            $table->string('full_name_en', 150);
            $table->string('full_name_bn', 200)->nullable();
            $table->string('role_code', 50)->default('ROLE_FIELD_ENUMERATOR')->index();
            $table->uuid('administrative_unit_id')->nullable()->index();
            $table->string('designation', 100)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Sessions table (for web session support, without foreign key to integer user_id)
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->uuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = DB::getDriverName() === 'pgsql' ? 'core.users' : 'users';
        Schema::dropIfExists($tableName);
        Schema::dropIfExists('sessions');
    }
};
