<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for Module 12: User Management, RBAC & Organizational Scoping.
     * Supports PostgreSQL (core schema) and SQLite (testing fallback).
     */
    public function up(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';

        if ($isPgsql) {
            DB::statement('CREATE SCHEMA IF NOT EXISTS core;');
        }

        $usersTable = $isPgsql ? 'core.users' : 'users';
        $adminUnitsTable = $isPgsql ? 'core.administrative_units' : 'administrative_units';
        $organizationsTable = $isPgsql ? 'core.organizations' : 'organizations';
        $rolesTable = $isPgsql ? 'core.roles' : 'roles';
        $permissionsTable = $isPgsql ? 'core.permissions' : 'permissions';
        $rolePermissionsTable = $isPgsql ? 'core.role_permissions' : 'role_permissions';
        $userRolesTable = $isPgsql ? 'core.user_roles' : 'user_roles';
        $userScopesTable = $isPgsql ? 'core.user_administrative_scopes' : 'user_administrative_scopes';

        // 1. Core Organizations Registry
        Schema::create($organizationsTable, function (Blueprint $table) use ($adminUnitsTable, $usersTable) {
            $table->uuid('id')->primary();
            $table->string('org_code', 40)->unique();
            $table->string('name_en', 180);
            $table->string('name_bn', 220)->nullable();
            $table->string('org_type', 40)->default('GOVERNMENT')->index()
                ->comment('MINISTRY, DIRECTORATE, DIVISION_ADMIN, DISTRICT_ADMIN, UPAZILA_ADMIN, UNION_PARISHAD, DEFENSE_RESCUE, HUMANITARIAN_PARTNER, VOLUNTEER_AGENCY, OTHER');
            $table->string('tier_level', 30)->default('NATIONAL')->index();
            $table->uuid('administrative_unit_id')->nullable()->index();
            $table->uuid('parent_organization_id')->nullable()->index();
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->uuid('created_by')->nullable()->index();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('administrative_unit_id')->references('id')->on($adminUnitsTable)->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on($usersTable)->onDelete('set null');
        });

        Schema::table($organizationsTable, function (Blueprint $table) use ($organizationsTable) {
            $table->foreign('parent_organization_id')->references('id')->on($organizationsTable)->onDelete('set null');
        });

        // 2. Extend core.users with officer identity, organization, lifecycle, MFA, photo & Keycloak sync metadata
        if ($isPgsql) {
            DB::statement("ALTER TABLE {$usersTable} ALTER COLUMN keycloak_sub DROP NOT NULL;");
        } else {
            Schema::table($usersTable, function (Blueprint $table) {
                $table->string('keycloak_sub')->nullable()->change();
            });
        }

        Schema::table($usersTable, function (Blueprint $table) use ($organizationsTable, $adminUnitsTable, $usersTable) {
            $table->string('employee_id', 50)->nullable()->unique()->after('email');
            $table->string('designation_bn', 150)->nullable()->after('designation');
            $table->uuid('organization_id')->nullable()->index()->after('administrative_unit_id');
            $table->string('department_en', 120)->nullable()->after('organization_id');
            $table->string('department_bn', 150)->nullable()->after('department_en');
            $table->string('account_status', 30)->default('ACTIVE')->index()->after('is_active')
                ->comment('PENDING, ACTIVE, SUSPENDED, DEACTIVATED, LOCKED');
            $table->text('status_reason')->nullable()->after('account_status');
            $table->timestamp('status_changed_at')->nullable()->after('status_reason');
            $table->uuid('status_changed_by')->nullable()->after('status_changed_at');
            $table->string('photo_path', 255)->nullable()->after('status_changed_by');
            $table->string('photo_mime', 100)->nullable()->after('photo_path');
            $table->char('photo_hash', 64)->nullable()->after('photo_mime');
            $table->string('keycloak_username', 150)->nullable()->index()->after('keycloak_sub');
            $table->string('idp_sync_status', 30)->default('LINKED')->index()->after('keycloak_username')
                ->comment('LINKED, PENDING_SYNC, SYNC_FAILED, LOCKED_BY_IDP, NOT_LINKED');
            $table->timestamp('idp_synced_at')->nullable()->after('idp_sync_status');
            $table->text('idp_sync_error')->nullable()->after('idp_synced_at');
            $table->boolean('mfa_enforced')->default(false)->index()->after('idp_sync_error');
            $table->boolean('mfa_verified')->default(false)->after('mfa_enforced');
            $table->timestamp('mfa_enrolled_at')->nullable()->after('mfa_verified');
            $table->uuid('created_by')->nullable()->index()->after('last_login_at');
            $table->uuid('updated_by')->nullable()->after('created_by');

            $table->foreign('organization_id')->references('id')->on($organizationsTable)->onDelete('set null');
            $table->foreign('administrative_unit_id')->references('id')->on($adminUnitsTable)->onDelete('set null');
            $table->foreign('status_changed_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('created_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on($usersTable)->onDelete('set null');
        });

        // 3. Role Catalog (System & Configurable Roles)
        Schema::create($rolesTable, function (Blueprint $table) use ($usersTable) {
            $table->uuid('id')->primary();
            $table->string('code', 60)->unique();
            $table->string('name_en', 120);
            $table->string('name_bn', 160);
            $table->string('tier_level', 30)->default('FIELD')->index();
            $table->integer('hierarchy_rank')->default(7)->index()
                ->comment('1=National/Ministry, 2=Directorate, 3=Division, 4=District, 5=Upazila, 6=Union, 7=Field/Partner');
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('mfa_required')->default(false);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on($usersTable)->onDelete('set null');
        });

        // 4. Granular Permission Catalog
        Schema::create($permissionsTable, function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 100)->unique();
            $table->string('module_code', 50)->index();
            $table->string('name_en', 150);
            $table->string('name_bn', 200);
            $table->string('action_type', 30)->index();
            $table->boolean('is_sensitive')->default(false)->index();
            $table->text('description')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // 5. Role-Permission Pivot
        Schema::create($rolePermissionsTable, function (Blueprint $table) use ($rolesTable, $permissionsTable) {
            $table->uuid('role_id')->index();
            $table->uuid('permission_id')->index();
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['role_id', 'permission_id']);
            $table->foreign('role_id')->references('id')->on($rolesTable)->onDelete('cascade');
            $table->foreign('permission_id')->references('id')->on($permissionsTable)->onDelete('cascade');
        });

        // 6. User-Role Assignments (Supports Primary & Additional Roles)
        Schema::create($userRolesTable, function (Blueprint $table) use ($usersTable, $rolesTable) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->index();
            $table->uuid('role_id')->index();
            $table->boolean('is_primary')->default(false)->index();
            $table->uuid('assigned_by')->nullable();
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamps();

            $table->unique(['user_id', 'role_id'], 'uq_user_roles_user_role');
            $table->foreign('user_id')->references('id')->on($usersTable)->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on($rolesTable)->onDelete('cascade');
            $table->foreign('assigned_by')->references('id')->on($usersTable)->onDelete('set null');
        });

        // 7. User Organizational & Temporary Administrative Scopes
        Schema::create($userScopesTable, function (Blueprint $table) use ($usersTable, $adminUnitsTable) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->index();
            $table->uuid('administrative_unit_id')->index();
            $table->string('assignment_type', 30)->default('PRIMARY_POSTING')->index()
                ->comment('PRIMARY_POSTING, ADDITIONAL_CHARGE, EMERGENCY_DEPUTATION, INSPECTION_TAG');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('valid_from')->useCurrent();
            $table->timestamp('valid_until')->nullable()->index();
            $table->uuid('delegated_by')->nullable();
            $table->string('official_order_ref', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on($usersTable)->onDelete('cascade');
            $table->foreign('administrative_unit_id')->references('id')->on($adminUnitsTable)->onDelete('restrict');
            $table->foreign('delegated_by')->references('id')->on($usersTable)->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';

        $usersTable = $isPgsql ? 'core.users' : 'users';
        $organizationsTable = $isPgsql ? 'core.organizations' : 'organizations';
        $rolesTable = $isPgsql ? 'core.roles' : 'roles';
        $permissionsTable = $isPgsql ? 'core.permissions' : 'permissions';
        $rolePermissionsTable = $isPgsql ? 'core.role_permissions' : 'role_permissions';
        $userRolesTable = $isPgsql ? 'core.user_roles' : 'user_roles';
        $userScopesTable = $isPgsql ? 'core.user_administrative_scopes' : 'user_administrative_scopes';

        Schema::dropIfExists($userScopesTable);
        Schema::dropIfExists($userRolesTable);
        Schema::dropIfExists($rolePermissionsTable);
        Schema::dropIfExists($permissionsTable);
        Schema::dropIfExists($rolesTable);

        Schema::table($usersTable, function (Blueprint $table) use ($isPgsql) {
            $table->dropForeign(['organization_id']);
            $table->dropForeign(['administrative_unit_id']);
            $table->dropForeign(['status_changed_by']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            if (!$isPgsql) {
                $table->dropUnique(['employee_id']);
            }
            $table->dropColumn([
                'employee_id',
                'designation_bn',
                'organization_id',
                'department_en',
                'department_bn',
                'account_status',
                'status_reason',
                'status_changed_at',
                'status_changed_by',
                'photo_path',
                'photo_mime',
                'photo_hash',
                'keycloak_username',
                'idp_sync_status',
                'idp_synced_at',
                'idp_sync_error',
                'mfa_enforced',
                'mfa_verified',
                'mfa_enrolled_at',
                'created_by',
                'updated_by',
            ]);
        });

        Schema::dropIfExists($organizationsTable);
    }
};
