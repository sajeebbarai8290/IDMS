<?php

namespace Tests\Feature\Api\V1;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\AuditLog;
use App\Core\Models\User;
use App\Modules\UserManagement\Contracts\KeycloakAdminServiceInterface;
use App\Modules\UserManagement\Models\Organization;
use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\UserAdministrativeScope;
use Database\Seeders\AdministrativeUnitSeeder;
use Database\Seeders\UserManagementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserManagementModuleTest extends TestCase
{
    use RefreshDatabase;

    protected string $privateKey;
    protected string $cleanX5cCert;
    protected string $kid = 'idms-user-mgmt-test-key-2026';
    protected string $realm = 'idms-gov-realm';
    protected string $issuer = 'http://localhost:8080/realms/idms-gov-realm';
    protected string $audience = 'idms-backend-api';

    protected AdministrativeUnit $nationalUnit;
    protected AdministrativeUnit $khulnaDistrict;
    protected AdministrativeUnit $barishalDistrict;
    protected AdministrativeUnit $dacopeUpazila;
    protected AdministrativeUnit $satkhiraDistrict;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->seed(AdministrativeUnitSeeder::class);
        $this->seed(UserManagementSeeder::class);

        $this->nationalUnit = AdministrativeUnit::where('tier_level', 'NATIONAL')->firstOrFail();
        $this->khulnaDistrict = AdministrativeUnit::where('code', '47')->firstOrFail();
        $this->barishalDistrict = AdministrativeUnit::where('code', '10')->firstOrFail();
        $this->dacopeUpazila = AdministrativeUnit::where('code', '12')->firstOrFail();
        $this->satkhiraDistrict = AdministrativeUnit::where('code', '87')->firstOrFail();

        $configPath = null;
        foreach ([
            'C:/wamp64/bin/php/php8.4.15/extras/ssl/openssl.cnf',
            'C:/wamp64/bin/php/php8.3.28/extras/ssl/openssl.cnf',
            'C:/wamp64/bin/apache/apache2.4.63/conf/openssl.cnf',
        ] as $candidate) {
            if (file_exists($candidate)) {
                $configPath = $candidate;
                putenv("OPENSSL_CONF={$candidate}");
                break;
            }
        }

        $sslOpts = $configPath ? ['config' => $configPath] : [];
        $res = openssl_pkey_new(array_merge($sslOpts, [
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]));
        $privKey = '';
        openssl_pkey_export($res, $privKey, null, $sslOpts);
        $this->privateKey = $privKey;

        $csr = openssl_csr_new([
            'countryName' => 'BD',
            'organizationName' => 'MoDMR',
            'commonName' => 'idms-test.modmr.gov.bd',
        ], $res, $sslOpts);
        $x509 = openssl_csr_sign($csr, null, $res, 365, $sslOpts);
        $certOut = '';
        openssl_x509_export($x509, $certOut);
        $this->cleanX5cCert = str_replace(
            ["-----BEGIN CERTIFICATE-----", "-----END CERTIFICATE-----", "\r", "\n"],
            '',
            $certOut
        );

        Cache::put("keycloak:jwks:{$this->realm}", [[
            'kid' => $this->kid,
            'kty' => 'RSA',
            'alg' => 'RS256',
            'use' => 'sig',
            'x5c' => [$this->cleanX5cCert],
        ]], 3600);
    }

    protected function createToken(array $payloadOverrides = []): string
    {
        $header = ['alg' => 'RS256', 'typ' => 'JWT', 'kid' => $this->kid];
        $now = time();
        $payload = array_merge([
            'sub' => (string) Str::uuid(),
            'preferred_username' => 'super_admin',
            'name' => 'ICT Super Admin',
            'email' => 'super.admin@modmr.gov.bd',
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'exp' => $now + 3600,
            'nbf' => $now - 60,
            'iat' => $now,
            'roles' => ['ROLE_SUPER_ADMIN'],
            'tier_level' => 'NATIONAL',
            'ancestry_path' => '/',
            'scope_id' => $this->nationalUnit->id,
            'designation' => 'Chief ICT Officer',
        ], $payloadOverrides);

        $h = rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '=');
        $p = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        openssl_sign("{$h}.{$p}", $sig, $this->privateKey, OPENSSL_ALGO_SHA256);
        $s = rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');

        return "{$h}.{$p}.{$s}";
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/user-management/users')->assertStatus(401);
        $this->getJson('/api/v1/user-management/dashboard')->assertStatus(401);
    }

    public function test_super_admin_can_provision_update_upload_photo_and_sync_user(): void
    {
        $token = $this->createToken([
            'sub' => '11111111-1111-4111-8111-111111111111',
            'email' => 'admin.ict@modmr.gov.bd',
        ]);

        $org = Organization::where('org_code', 'UPZ-DACOPE')->firstOrFail();

        $createRes = $this->withToken($token)->postJson('/api/v1/user-management/users', [
            'full_name_en' => 'Md. Kamal Uddin',
            'full_name_bn' => 'মোঃ কামাল উদ্দিন',
            'email' => 'kamal.pio@modmr.gov.bd',
            'employee_id' => 'MODMR-PIO-404712',
            'mobile_number' => '+8801711002233',
            'designation' => 'Project Implementation Officer',
            'designation_bn' => 'প্রকল্প বাস্তবায়ন কর্মকর্তা',
            'organization_id' => $org->id,
            'department_en' => 'Upazila Disaster Office',
            'department_bn' => 'উপজেলা দুর্যোগ কার্যালয়',
            'administrative_unit_id' => $this->dacopeUpazila->id,
            'role_codes' => ['ROLE_PIO', 'ROLE_TAG_OFFICER'],
            'account_status' => 'ACTIVE',
            'official_order_ref' => 'GO-2026-101',
        ]);

        $createRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.employee_id', 'MODMR-PIO-404712')
            ->assertJsonPath('data.role_code', 'ROLE_PIO')
            ->assertJsonPath('data.idp_sync_status', 'LINKED')
            ->assertJsonPath('data.mfa_enforced', true);

        $userId = $createRes->json('data.id');
        $created = User::findOrFail($userId);
        $this->assertNotNull($created->keycloak_sub);
        $this->assertSame('', $created->getAuthPassword());
        $this->assertTrue($created->hasRole('ROLE_TAG_OFFICER'));

        // Duplicate email / employee_id rejected
        $this->withToken($token)->postJson('/api/v1/user-management/users', [
            'full_name_en' => 'Duplicate Officer',
            'email' => 'kamal.pio@modmr.gov.bd',
            'employee_id' => 'MODMR-PIO-404712',
            'administrative_unit_id' => $this->dacopeUpazila->id,
            'role_codes' => ['ROLE_PIO'],
        ])->assertStatus(422);

        // Non-existent organization_id UUID returns 422 validation error instead of 500
        $this->withToken($token)->postJson('/api/v1/user-management/users', [
            'full_name_en' => 'Invalid Org User',
            'email' => 'invalid.org@modmr.gov.bd',
            'organization_id' => (string) Str::uuid(),
            'administrative_unit_id' => $this->dacopeUpazila->id,
            'role_codes' => ['ROLE_PIO'],
        ])->assertStatus(422);

        // Upload & download photograph
        $photo = UploadedFile::fake()->image('officer.png', 200, 200);
        $this->withToken($token)
            ->postJson("/api/v1/user-management/users/{$userId}/photo", ['photo' => $photo])
            ->assertStatus(200)
            ->assertJsonPath('data.has_photo', true);

        $this->withToken($token)
            ->get("/api/v1/user-management/users/{$userId}/photo")
            ->assertStatus(200);

        // Update profile
        $this->withToken($token)
            ->putJson("/api/v1/user-management/users/{$userId}", [
                'designation' => 'Senior Project Implementation Officer',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.designation', 'Senior Project Implementation Officer');

        $this->assertDatabaseHas('audit_logs', [
            'action_name' => 'USER_ACCOUNT_CREATED',
            'auditable_id' => $userId,
        ]);
    }

    public function test_lifecycle_suspension_and_deactivation_blocks_keycloak_token_access(): void
    {
        $adminToken = $this->createToken([
            'sub' => '11111111-1111-4111-8111-111111111112',
            'email' => 'super2@modmr.gov.bd',
        ]);

        $officerSub = '22222222-2222-4222-8222-222222222222';
        $officerToken = $this->createToken([
            'sub' => $officerSub,
            'preferred_username' => 'pio_dacope_lifecycle',
            'email' => 'pio.lifecycle@modmr.gov.bd',
            'roles' => ['ROLE_PIO'],
            'tier_level' => 'UPAZILA',
            'ancestry_path' => $this->dacopeUpazila->ancestry_path,
            'scope_id' => $this->dacopeUpazila->id,
        ]);

        // Initial login works
        $this->withToken($officerToken)->getJson('/api/v1/auth/user')->assertStatus(200);
        $officer = User::where('keycloak_sub', $officerSub)->firstOrFail();

        // Admin suspends user
        $this->withToken($adminToken)
            ->postJson("/api/v1/user-management/users/{$officer->id}/status", [
                'account_status' => 'SUSPENDED',
                'reason' => 'Pending departmental inquiry',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.account_status', 'SUSPENDED')
            ->assertJsonPath('data.is_active', false);

        // Suspended officer token must be rejected with 403 ACCOUNT_RESTRICTED
        $this->withToken($officerToken)
            ->getJson('/api/v1/auth/user')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'ACCOUNT_RESTRICTED');

        // Re-activating restores access
        $this->withToken($adminToken)
            ->postJson("/api/v1/user-management/users/{$officer->id}/status", [
                'account_status' => 'ACTIVE',
                'reason' => 'Inquiry cleared',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.account_status', 'ACTIVE');

        $this->withToken($officerToken)->getJson('/api/v1/auth/user')->assertStatus(200);
    }

    public function test_self_deactivation_and_privilege_escalation_are_blocked(): void
    {
        $adminSub = '11111111-1111-4111-8111-111111111113';
        $adminToken = $this->createToken([
            'sub' => $adminSub,
            'email' => 'self.admin@modmr.gov.bd',
        ]);
        $this->withToken($adminToken)->getJson('/api/v1/auth/user')->assertStatus(200);
        $admin = User::where('keycloak_sub', $adminSub)->firstOrFail();

        // Cannot suspend own account
        $this->withToken($adminToken)
            ->postJson("/api/v1/user-management/users/{$admin->id}/status", [
                'account_status' => 'SUSPENDED',
            ])
            ->assertStatus(403);

        // Cannot remove ROLE_SUPER_ADMIN from self
        $this->withToken($adminToken)
            ->postJson("/api/v1/user-management/users/{$admin->id}/roles", [
                'role_codes' => ['ROLE_PIO'],
            ])
            ->assertStatus(403);

        // Field enumerator cannot access user directory
        $fieldToken = $this->createToken([
            'sub' => '33333333-3333-4333-8333-333333333333',
            'email' => 'field.enum@modmr.gov.bd',
            'roles' => ['ROLE_FIELD_ENUMERATOR'],
            'tier_level' => 'FIELD',
            'ancestry_path' => $this->dacopeUpazila->ancestry_path,
            'scope_id' => $this->dacopeUpazila->id,
        ]);

        $this->withToken($fieldToken)
            ->getJson('/api/v1/user-management/users')
            ->assertStatus(403);
    }

    public function test_hierarchical_jurisdiction_isolation_for_scoped_viewers(): void
    {
        $dacopeUser = User::create([
            'id' => (string) Str::uuid(),
            'keycloak_sub' => (string) Str::uuid(),
            'email' => 'dacope.worker@modmr.gov.bd',
            'full_name_en' => 'Dacope Worker',
            'role_code' => 'ROLE_FIELD_ENUMERATOR',
            'administrative_unit_id' => $this->dacopeUpazila->id,
            'is_active' => true,
            'account_status' => User::STATUS_ACTIVE,
        ]);

        $barishalUser = User::create([
            'id' => (string) Str::uuid(),
            'keycloak_sub' => (string) Str::uuid(),
            'email' => 'barishal.worker@modmr.gov.bd',
            'full_name_en' => 'Barishal Worker',
            'role_code' => 'ROLE_DRRO',
            'administrative_unit_id' => $this->barishalDistrict->id,
            'is_active' => true,
            'account_status' => User::STATUS_ACTIVE,
        ]);

        $khulnaDrroToken = $this->createToken([
            'sub' => '44444444-4444-4444-8444-444444444444',
            'email' => 'drro.khulna@modmr.gov.bd',
            'roles' => ['ROLE_DRRO'],
            'tier_level' => 'DISTRICT',
            'ancestry_path' => $this->khulnaDistrict->ancestry_path,
            'scope_id' => $this->khulnaDistrict->id,
        ]);

        $listRes = $this->withToken($khulnaDrroToken)
            ->getJson('/api/v1/user-management/users?per_page=50')
            ->assertStatus(200);

        $returnedIds = collect($listRes->json('data'))->pluck('id')->all();
        $this->assertContains($dacopeUser->id, $returnedIds);
        $this->assertNotContains($barishalUser->id, $returnedIds);

        // Direct show of out-of-scope Barishal user returns 403
        $this->withToken($khulnaDrroToken)
            ->getJson("/api/v1/user-management/users/{$barishalUser->id}")
            ->assertStatus(403);

        // DRRO lacks user.account.create permission -> 403
        $this->withToken($khulnaDrroToken)
            ->postJson('/api/v1/user-management/users', [
                'full_name_en' => 'Unauthorized Provision',
                'email' => 'unauth@modmr.gov.bd',
                'administrative_unit_id' => $this->dacopeUpazila->id,
                'role_codes' => ['ROLE_FIELD_ENUMERATOR'],
            ])
            ->assertStatus(403);

        // Barishal organization is out of Khulna DRRO scope -> 403
        $barishalOrg = Organization::create([
            'id' => (string) Str::uuid(),
            'org_code' => 'DIST-BARISHAL',
            'name_en' => 'Barishal District Office',
            'org_type' => 'DISTRICT_ADMIN',
            'tier_level' => 'DISTRICT',
            'administrative_unit_id' => $this->barishalDistrict->id,
            'is_active' => true,
        ]);

        $this->withToken($khulnaDrroToken)
            ->getJson("/api/v1/user-management/organizations/{$barishalOrg->id}")
            ->assertStatus(403);
    }

    public function test_temporary_scope_assignment_role_matrix_organizations_and_dashboard(): void
    {
        $token = $this->createToken([
            'sub' => '55555555-5555-4555-8555-555555555555',
            'email' => 'admin.matrix@modmr.gov.bd',
        ]);

        // Create configurable role & sync permissions
        $roleRes = $this->withToken($token)->postJson('/api/v1/user-management/roles', [
            'code' => 'ROLE_DISTRICT_RELIEF_COORD',
            'name_en' => 'District Relief Coordinator',
            'name_bn' => 'জেলা ত্রাণ সমন্বয়কারী',
            'tier_level' => 'DISTRICT',
            'mfa_required' => true,
        ])->assertStatus(201);

        $roleId = $roleRes->json('data.id');
        $this->withToken($token)->postJson("/api/v1/user-management/roles/{$roleId}/permissions", [
            'permissions' => ['relief.requisition.view', 'disaster.event.view'],
        ])->assertStatus(200)
            ->assertJsonPath('data.permissions_count', 2);

        // System role cannot be deleted
        $systemRole = Role::where('code', 'ROLE_UNO')->firstOrFail();
        $this->withToken($token)
            ->deleteJson("/api/v1/user-management/roles/{$systemRole->id}")
            ->assertStatus(422);

        // Create organization
        $orgRes = $this->withToken($token)->postJson('/api/v1/user-management/organizations', [
            'org_code' => 'DIST-SATKHIRA',
            'name_en' => 'Satkhira District Disaster Office',
            'name_bn' => 'সাতক্ষীরা জেলা দুর্যোগ কার্যালয়',
            'org_type' => 'DISTRICT_ADMIN',
            'tier_level' => 'DISTRICT',
            'administrative_unit_id' => $this->satkhiraDistrict->id,
        ])->assertStatus(201);

        $orgId = $orgRes->json('data.id');

        // Create user and assign temporary scope
        $userRes = $this->withToken($token)->postJson('/api/v1/user-management/users', [
            'full_name_en' => 'Relief Coordinator',
            'email' => 'coord.khulna@modmr.gov.bd',
            'organization_id' => $orgId,
            'administrative_unit_id' => $this->dacopeUpazila->id,
            'role_codes' => ['ROLE_DISTRICT_RELIEF_COORD'],
            'account_status' => 'ACTIVE',
        ])->assertStatus(201);
        $userId = $userRes->json('data.id');

        // Cannot archive organization with assigned user
        $this->withToken($token)->deleteJson("/api/v1/user-management/organizations/{$orgId}")->assertStatus(422);

        $scopeRes = $this->withToken($token)->postJson("/api/v1/user-management/users/{$userId}/scopes", [
            'administrative_unit_id' => $this->satkhiraDistrict->id,
            'assignment_type' => 'EMERGENCY_DEPUTATION',
            'valid_from' => now()->toIso8601String(),
            'valid_until' => now()->addDays(10)->toIso8601String(),
            'official_order_ref' => 'MEMO-CYCLONE-2026',
        ])->assertStatus(201);

        $scopeId = $scopeRes->json('data.id');

        // Revoke temporary scope
        $this->withToken($token)
            ->postJson("/api/v1/user-management/users/{$userId}/scopes/{$scopeId}/revoke")
            ->assertStatus(200);

        // Dashboard & activity logs
        $this->withToken($token)
            ->getJson('/api/v1/user-management/dashboard')
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'kpis' => [
                        'total_users',
                        'active_users',
                        'inactive_users',
                        'mfa_enforced_users',
                    ],
                    'by_organization',
                    'by_geographical_level',
                    'role_distribution',
                    'keycloak_health',
                ],
            ]);

        $this->withToken($token)
            ->getJson("/api/v1/user-management/users/{$userId}/activity")
            ->assertStatus(200);

        $this->assertTrue(
            AuditLog::where('action_name', 'ROLE_PERMISSIONS_UPDATED')->exists()
        );
    }

    public function test_keycloak_sync_failure_is_handled_safely_and_recorded(): void
    {
        $this->app->instance(KeycloakAdminServiceInterface::class, new class implements KeycloakAdminServiceInterface {
            public function provisionUser(User $user, array $roleCodes, ?array $scopeProfile = null): array
            {
                return $this->fail($user);
            }
            public function syncUser(User $user, array $roleCodes, ?array $scopeProfile = null): array
            {
                return $this->fail($user);
            }
            public function setUserEnabled(User $user, bool $enabled, ?string $reason = null): array
            {
                return $this->fail($user);
            }
            public function assignRoles(User $user, array $roleCodes): array
            {
                return $this->fail($user);
            }
            public function enforceMfa(User $user, bool $enforced): array
            {
                return $this->fail($user);
            }
            public function healthCheck(): array
            {
                return ['driver' => 'keycloak', 'configured' => true, 'reachable' => false, 'status' => 'UNREACHABLE'];
            }
            private function fail(User $user): array
            {
                return [
                    'success' => false,
                    'is_mock' => false,
                    'keycloak_sub' => $user->keycloak_sub,
                    'idp_sync_status' => User::IDP_SYNC_FAILED,
                    'error_code' => 'KEYCLOAK_UNREACHABLE',
                    'error_message' => 'Simulated IdP outage',
                ];
            }
        });

        $token = $this->createToken([
            'sub' => '66666666-6666-4666-8666-666666666666',
            'email' => 'admin.idpfail@modmr.gov.bd',
        ]);

        $target = User::create([
            'id' => (string) Str::uuid(),
            'keycloak_sub' => null,
            'email' => 'pending.officer@modmr.gov.bd',
            'full_name_en' => 'Pending Officer',
            'role_code' => 'ROLE_PIO',
            'administrative_unit_id' => $this->dacopeUpazila->id,
            'is_active' => false,
            'account_status' => User::STATUS_PENDING,
            'idp_sync_status' => User::IDP_SYNC_PENDING,
        ]);

        $this->withToken($token)
            ->postJson("/api/v1/user-management/users/{$target->id}/sync-keycloak")
            ->assertStatus(502)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.idp_sync_status', 'SYNC_FAILED');
    }
}
