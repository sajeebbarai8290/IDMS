<?php

namespace Tests\Unit\UserManagement;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Core\Services\AdministrativeScopeService;
use App\Modules\UserManagement\Adapters\KeycloakAdminHttpAdapter;
use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\UserAdministrativeScope;
use App\Modules\UserManagement\Services\RolePermissionService;
use Database\Seeders\AdministrativeUnitSeeder;
use Database\Seeders\UserManagementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserManagementSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdministrativeUnitSeeder::class);
        $this->seed(UserManagementSeeder::class);
    }

    public function test_user_model_never_stores_local_passwords(): void
    {
        $user = new User([
            'id' => (string) Str::uuid(),
            'email' => 'officer@modmr.gov.bd',
            'full_name_en' => 'Test Officer',
            'role_code' => 'ROLE_PIO',
        ]);

        $this->assertSame('', $user->getAuthPassword());
    }

    public function test_temporary_scope_grants_and_automatically_expires_access(): void
    {
        $dacope = AdministrativeUnit::where('code', '12')->firstOrFail();
        $satkhira = AdministrativeUnit::where('code', '87')->firstOrFail();
        $scopeService = app(AdministrativeScopeService::class);

        $pio = User::create([
            'id' => (string) Str::uuid(),
            'keycloak_sub' => (string) Str::uuid(),
            'email' => 'pio.dacope@modmr.gov.bd',
            'full_name_en' => 'Dacope PIO',
            'role_code' => 'ROLE_PIO',
            'administrative_unit_id' => $dacope->id,
            'is_active' => true,
            'account_status' => User::STATUS_ACTIVE,
        ]);

        $this->assertTrue($scopeService->canAccessScope($pio, $dacope->ancestry_path));
        $this->assertFalse($scopeService->canAccessScope($pio, $satkhira->ancestry_path));

        // Grant active additional charge for Satkhira District
        $tempScope = UserAdministrativeScope::create([
            'id' => (string) Str::uuid(),
            'user_id' => $pio->id,
            'administrative_unit_id' => $satkhira->id,
            'assignment_type' => 'ADDITIONAL_CHARGE',
            'is_active' => true,
            'valid_from' => now()->subHour(),
            'valid_until' => now()->addDays(7),
            'official_order_ref' => 'MEMO-2026-099',
        ]);

        $this->assertTrue($scopeService->canAccessScope($pio->fresh(), $satkhira->ancestry_path));

        // Simulate expiration
        $tempScope->update(['valid_until' => now()->subMinute()]);

        $this->assertFalse($scopeService->canAccessScope($pio->fresh(), $satkhira->ancestry_path));
    }

    public function test_role_permission_sync_updates_effective_permissions_and_clears_cache(): void
    {
        $dacope = AdministrativeUnit::where('code', '12')->firstOrFail();
        $superAdmin = User::create([
            'id' => (string) Str::uuid(),
            'keycloak_sub' => (string) Str::uuid(),
            'email' => 'admin@modmr.gov.bd',
            'full_name_en' => 'Super Admin',
            'role_code' => 'ROLE_SUPER_ADMIN',
            'is_active' => true,
            'account_status' => User::STATUS_ACTIVE,
        ]);

        $roleService = app(RolePermissionService::class);
        $customRole = $roleService->createRole([
            'code' => 'ROLE_UNIT_CUSTOM',
            'name_en' => 'Custom Unit Role',
            'name_bn' => 'কাস্টম ভূমিকা',
            'tier_level' => 'UPAZILA',
        ], $superAdmin);

        $officer = User::create([
            'id' => (string) Str::uuid(),
            'keycloak_sub' => (string) Str::uuid(),
            'email' => 'custom.officer@modmr.gov.bd',
            'full_name_en' => 'Custom Officer',
            'role_code' => $customRole->code,
            'administrative_unit_id' => $dacope->id,
            'is_active' => true,
            'account_status' => User::STATUS_ACTIVE,
        ]);

        $this->assertFalse($officer->hasPermission('report.sitrep.generate'));

        $perm = Permission::where('code', 'report.sitrep.generate')->firstOrFail();
        $roleService->syncRolePermissions($customRole, [$perm->id], $superAdmin);

        $this->assertTrue($officer->fresh()->hasPermission('report.sitrep.generate'));
    }

    public function test_keycloak_admin_http_adapter_handles_missing_config_and_http_failures_safely(): void
    {
        config([
            'services.keycloak.admin_client_id' => null,
            'services.keycloak.admin_client_secret' => null,
        ]);

        $adapter = new KeycloakAdminHttpAdapter();
        $health = $adapter->healthCheck();

        $this->assertFalse($health['configured']);
        $this->assertFalse($health['reachable']);
        $this->assertSame('NOT_CONFIGURED', $health['status']);

        config([
            'services.keycloak.base_url' => 'http://keycloak.test',
            'services.keycloak.realm' => 'idms-gov-realm',
            'services.keycloak.admin_client_id' => 'idms-admin',
            'services.keycloak.admin_client_secret' => 'test-secret',
        ]);

        Http::fake([
            'http://keycloak.test/*' => Http::response(['error' => 'unavailable'], 503),
        ]);

        $user = new User([
            'id' => (string) Str::uuid(),
            'email' => 'sync.fail@modmr.gov.bd',
            'full_name_en' => 'Sync Fail User',
            'role_code' => 'ROLE_PIO',
            'is_active' => true,
            'account_status' => User::STATUS_ACTIVE,
        ]);

        $configuredAdapter = new KeycloakAdminHttpAdapter();
        $result = $configuredAdapter->provisionUser($user, ['ROLE_PIO']);

        $this->assertFalse($result['success']);
        $this->assertSame(User::IDP_SYNC_FAILED, $result['idp_sync_status']);
        $this->assertSame('KEYCLOAK_ADMIN_AUTH_FAILED', $result['error_code']);
    }
}
