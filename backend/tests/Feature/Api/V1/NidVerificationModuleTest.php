<?php

namespace Tests\Feature\Api\V1;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\AuditLog;
use App\Modules\Beneficiary\Models\Household;
use App\Modules\NidVerification\Jobs\ProcessNidVerificationJob;
use App\Modules\NidVerification\Models\NidVerification;
use App\Modules\NidVerification\Models\NidVerificationAttempt;
use Database\Seeders\AdministrativeUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NidVerificationModuleTest extends TestCase
{
    use RefreshDatabase;

    protected string $privateKey;
    protected string $cleanX5cCert;
    protected string $kid = 'idms-nid-test-key-2026';
    protected string $realm = 'idms-gov-realm';
    protected string $issuer = 'http://localhost:8080/realms/idms-gov-realm';
    protected string $audience = 'idms-backend-api';

    protected AdministrativeUnit $chalnaUnion;
    protected AdministrativeUnit $barishalDistrict;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AdministrativeUnitSeeder::class);
        $this->chalnaUnion = AdministrativeUnit::where('code', '35')->firstOrFail();
        $this->barishalDistrict = AdministrativeUnit::where('code', '10')->firstOrFail();

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

        $dn = [
            'countryName' => 'BD',
            'organizationName' => 'Ministry of Disaster Management and Relief',
            'commonName' => 'idms-keycloak.modmr.gov.bd',
        ];
        $csr = openssl_csr_new($dn, $res, $sslOpts);
        $x509 = openssl_csr_sign($csr, null, $res, 365, $sslOpts);
        $certOut = '';
        openssl_x509_export($x509, $certOut);

        $this->cleanX5cCert = str_replace(
            ["-----BEGIN CERTIFICATE-----", "-----END CERTIFICATE-----", "\r", "\n"],
            '',
            $certOut
        );

        Cache::put("keycloak:jwks:{$this->realm}", [
            [
                'kid' => $this->kid,
                'kty' => 'RSA',
                'alg' => 'RS256',
                'use' => 'sig',
                'x5c' => [$this->cleanX5cCert],
            ],
        ], 3600);
    }

    protected function createToken(array $payloadOverrides = []): string
    {
        $header = ['alg' => 'RS256', 'typ' => 'JWT', 'kid' => $this->kid];
        $now = time();

        $payload = array_merge([
            'sub' => 'b661334c-687f-4ca7-b24e-4b6807890601',
            'preferred_username' => 'pio_dacope',
            'name' => 'Md. Shamsul Huda (PIO)',
            'email' => 'pio.dacope@modmr.gov.bd',
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'exp' => $now + 3600,
            'nbf' => $now - 60,
            'iat' => $now,
            'roles' => ['ROLE_PIO'],
            'tier_level' => 'UPAZILA',
            'ancestry_path' => '/40/47/12/',
            'scope_id' => $this->chalnaUnion->parent_id,
            'designation' => 'Project Implementation Officer',
        ], $payloadOverrides);

        $headerB64 = rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '=');
        $payloadB64 = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $signingInput = "{$headerB64}.{$payloadB64}";

        openssl_sign($signingInput, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);
        $signatureB64 = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        return "{$signingInput}.{$signatureB64}";
    }

    public function test_unauthenticated_nid_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/nid/dashboard')->assertStatus(401);
        $this->getJson('/api/v1/nid/verifications')->assertStatus(401);
        $this->postJson('/api/v1/nid/verifications', ['nid_number' => '1990471234567'])->assertStatus(401);
    }

    public function test_invalid_nid_format_and_future_dob_are_rejected(): void
    {
        $token = $this->createToken();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/nid/verifications', [
                'nid_number' => '12345',
                'date_of_birth' => '2099-01-01',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['nid_number', 'date_of_birth']]]);
    }

    public function test_synchronous_verification_records_attempts_audit_and_updates_household(): void
    {
        $token = $this->createToken();

        $hhRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/beneficiary/households', [
                'administrative_unit_id' => $this->chalnaUnion->id,
                'present_address' => 'Chalna',
                'hoh_name_en' => 'Kamal Uddin',
                'hoh_name_bn' => 'কামাল উদ্দিন',
                'hoh_nid' => '1990471234567',
                'hoh_dob' => '1990-05-12',
                'hoh_gender' => 'MALE',
            ])
            ->assertStatus(201);

        $householdId = $hhRes->json('household.id');

        $verifyRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/nid/verifications', [
                'nid_number' => '1990471234567',
                'date_of_birth' => '1990-05-12',
                'submitted_name_en' => 'Kamal Uddin',
                'household_id' => $householdId,
            ]);

        $verifyRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('verification.verification_status', 'VERIFIED')
            ->assertJsonPath('verification.is_mock', true)
            ->assertJsonPath('verification.attempt_count', 1);

        // Raw NID must never appear in JSON response
        $this->assertStringNotContainsString('1990471234567', $verifyRes->getContent());

        $verificationId = $verifyRes->json('verification.id');
        $this->assertDatabaseHas('nid_verifications', [
            'id' => $verificationId,
            'verification_status' => 'VERIFIED',
            'is_mock' => 1,
            'attempt_count' => 1,
        ]);
        $this->assertDatabaseHas('nid_verification_attempts', [
            'nid_verification_id' => $verificationId,
            'attempt_number' => 1,
            'status' => 'VERIFIED',
        ]);
        $this->assertDatabaseHas('households', [
            'id' => $householdId,
            'nid_verified' => 1,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action_name' => 'NID_VERIFICATION_VERIFIED',
            'auditable_id' => $verificationId,
        ]);
    }

    public function test_timeout_fixture_performs_retries_and_logs_attempts(): void
    {
        config()->set('services.nid.max_retries', 2);
        $token = $this->createToken();

        $res = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/nid/verifications', [
                'nid_number' => '7777777777',
                'date_of_birth' => '1985-03-03',
            ]);

        $res->assertStatus(201)
            ->assertJsonPath('verification.verification_status', 'FAILED')
            ->assertJsonPath('verification.attempt_count', 3)
            ->assertJsonPath('verification.last_error_code', 'PROVIDER_TIMEOUT');

        $verificationId = $res->json('verification.id');
        $this->assertSame(3, NidVerificationAttempt::where('nid_verification_id', $verificationId)->count());
    }

    public function test_async_mode_queues_job_and_clears_temporary_encrypted_payload(): void
    {
        Queue::fake();
        $token = $this->createToken();

        $res = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/nid/verifications', [
                'nid_number' => '1234567890',
                'date_of_birth' => '1991-07-15',
                'async' => true,
            ]);

        $res->assertStatus(202)
            ->assertJsonPath('verification.verification_status', 'PENDING');

        $verificationId = $res->json('verification.id');
        Queue::assertPushed(ProcessNidVerificationJob::class, function ($job) use ($verificationId) {
            return $job->verificationId === $verificationId;
        });

        $record = NidVerification::findOrFail($verificationId);
        $this->assertNotNull($record->nid_encrypted_payload);

        // Execute the queued job synchronously and verify payload is wiped after completion
        $job = new ProcessNidVerificationJob($verificationId, $record->requested_by_user_id);
        app()->call([$job, 'handle']);

        $record->refresh();
        $this->assertSame('VERIFIED', $record->verification_status);
        $this->assertNull($record->nid_encrypted_payload);
    }

    public function test_rate_limiting_blocks_excessive_nid_requests(): void
    {
        config()->set('services.nid.rate_limit_per_minute', 2);
        $token = $this->createToken(['sub' => 'rate-limit-user-sub-001', 'preferred_username' => 'rate_user']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/nid/verifications', ['nid_number' => '1111111111'])
            ->assertStatus(201);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/nid/verifications', ['nid_number' => '2222222222'])
            ->assertStatus(201);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/nid/verifications', ['nid_number' => '3333333333'])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'NID_RATE_LIMIT_EXCEEDED');
    }

    public function test_manual_review_enforces_four_eye_sod_and_updates_audit_trail(): void
    {
        $enumeratorToken = $this->createToken([
            'sub' => 'enum-sub-001',
            'preferred_username' => 'enum_dacope',
            'email' => 'enum.dacope@modmr.gov.bd',
            'roles' => ['ROLE_FIELD_ENUMERATOR'],
        ]);

        $pioToken = $this->createToken([
            'sub' => 'pio-sub-001',
            'preferred_username' => 'pio_dacope_reviewer',
            'email' => 'pio.reviewer@modmr.gov.bd',
            'roles' => ['ROLE_PIO'],
        ]);

        // PIO creates a household
        $hhRes = $this->withHeader('Authorization', "Bearer {$pioToken}")
            ->postJson('/api/v1/beneficiary/households', [
                'administrative_unit_id' => $this->chalnaUnion->id,
                'present_address' => 'Chalna',
                'hoh_name_en' => 'Review Candidate',
                'hoh_name_bn' => 'রিভিউ প্রার্থী',
                'hoh_nid' => '8888888888',
                'hoh_gender' => 'FEMALE',
            ])->assertStatus(201);
        $householdId = $hhRes->json('household.id');

        // Field enumerator initiates NID verification (triggers MANUAL_REVIEW_REQUIRED)
        $initRes = $this->withHeader('Authorization', "Bearer {$enumeratorToken}")
            ->postJson('/api/v1/nid/verifications', [
                'nid_number' => '8888888888',
                'household_id' => $householdId,
            ]);
        $initRes->assertStatus(201)
            ->assertJsonPath('verification.verification_status', 'MANUAL_REVIEW_REQUIRED');
        $verificationId = $initRes->json('verification.id');

        // Field enumerator cannot manually review
        $this->withHeader('Authorization', "Bearer {$enumeratorToken}")
            ->postJson("/api/v1/nid/verifications/{$verificationId}/manual-review", [
                'decision_status' => 'VERIFIED',
                'override_reason' => 'Enumerator attempting unauthorized override.',
            ])
            ->assertStatus(403);

        // PIO initiates another verification and tries to override their own request -> 4-eye SoD 403
        $pioInit = $this->withHeader('Authorization', "Bearer {$pioToken}")
            ->postJson('/api/v1/nid/verifications', [
                'nid_number' => '8888888888',
            ])->assertStatus(201);
        $pioVerificationId = $pioInit->json('verification.id');

        $this->withHeader('Authorization', "Bearer {$pioToken}")
            ->postJson("/api/v1/nid/verifications/{$pioVerificationId}/manual-review", [
                'decision_status' => 'VERIFIED',
                'override_reason' => 'Self-override attempt should be blocked.',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->assertStringContainsString(
            'Separation of Duties',
            $this->withHeader('Authorization', "Bearer {$pioToken}")
                ->postJson("/api/v1/nid/verifications/{$pioVerificationId}/manual-review", [
                    'decision_status' => 'VERIFIED',
                    'override_reason' => 'Self-override attempt should be blocked.',
                ])
                ->json('error.message') ?? ''
        );

        // Verify requested_by_keycloak_sub is present for frontend 4-eye SoD checks
        $this->assertSame('pio-sub-001', $pioInit->json('verification.requested_by_keycloak_sub'));

        // PIO CAN review the enumerator's verification request
        $reviewRes = $this->withHeader('Authorization', "Bearer {$pioToken}")
            ->postJson("/api/v1/nid/verifications/{$verificationId}/manual-review", [
                'decision_status' => 'VERIFIED',
                'override_reason' => 'Physical Smart NID card inspected and verified at Union office.',
            ]);

        $reviewRes->assertStatus(200)
            ->assertJsonPath('verification.verification_status', 'VERIFIED')
            ->assertJsonPath('verification.manual_override', true)
            ->assertJsonPath('verification.previous_status', 'MANUAL_REVIEW_REQUIRED');

        $this->assertDatabaseHas('households', [
            'id' => $householdId,
            'nid_verified' => 1,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action_name' => 'NID_MANUAL_REVIEW_OVERRIDE',
            'auditable_id' => $verificationId,
        ]);
    }

    public function test_organizational_scoping_and_role_restrictions(): void
    {
        $dacopeToken = $this->createToken([
            'sub' => 'dacope-pio-sub',
            'preferred_username' => 'pio_dacope_scope',
            'email' => 'pio.scope@modmr.gov.bd',
            'roles' => ['ROLE_PIO'],
            'ancestry_path' => '/40/47/12/',
            'scope_id' => $this->chalnaUnion->parent_id,
        ]);

        $barishalToken = $this->createToken([
            'sub' => 'barishal-drro-sub',
            'preferred_username' => 'drro_barishal',
            'email' => 'drro.barishal@modmr.gov.bd',
            'roles' => ['ROLE_DRRO'],
            'tier_level' => 'DISTRICT',
            'ancestry_path' => '/10/06/',
            'scope_id' => $this->barishalDistrict->id,
        ]);

        $externalToken = $this->createToken([
            'sub' => 'ngo-sub',
            'preferred_username' => 'ngo_observer',
            'email' => 'observer@wfp.org',
            'roles' => ['ROLE_EXTERNAL_PARTNER'],
            'tier_level' => 'NATIONAL',
            'ancestry_path' => '/',
        ]);

        // External partner denied
        $this->withHeader('Authorization', "Bearer {$externalToken}")
            ->getJson('/api/v1/nid/verifications')
            ->assertStatus(403);

        // Dacope creates verification
        $createRes = $this->withHeader('Authorization', "Bearer {$dacopeToken}")
            ->postJson('/api/v1/nid/verifications', [
                'nid_number' => '1990471234568',
                'administrative_unit_id' => $this->chalnaUnion->id,
            ])->assertStatus(201);
        $verificationId = $createRes->json('verification.id');

        // Barishal DRRO cannot view or review Khulna/Dacope verification
        $this->withHeader('Authorization', "Bearer {$barishalToken}")
            ->getJson("/api/v1/nid/verifications/{$verificationId}")
            ->assertStatus(403);

        // National Executive can view across jurisdictions
        $nationalToken = $this->createToken([
            'sub' => 'ministry-exec-sub',
            'preferred_username' => 'ministry_exec',
            'email' => 'secretary@modmr.gov.bd',
            'roles' => ['ROLE_MINISTRY_EXECUTIVE'],
            'tier_level' => 'NATIONAL',
            'ancestry_path' => '/',
        ]);

        $this->withHeader('Authorization', "Bearer {$nationalToken}")
            ->getJson("/api/v1/nid/verifications/{$verificationId}")
            ->assertStatus(200);
    }

    public function test_retry_and_dashboard_metrics_endpoints(): void
    {
        $token = $this->createToken();

        // Create 1 verified and 1 failed verification
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/nid/verifications', ['nid_number' => '1991471234567'])
            ->assertStatus(201);

        $failRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/nid/verifications', ['nid_number' => '0000000000'])
            ->assertStatus(201);

        $failId = $failRes->json('verification.id');

        // Retry with mismatched NID must fail validation
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/nid/verifications/{$failId}/retry", [
                'nid_number' => '1991471234567',
            ])
            ->assertStatus(422);

        // Retry with original NID succeeds in logging a 2nd attempt
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/nid/verifications/{$failId}/retry", [
                'nid_number' => '0000000000',
            ])
            ->assertStatus(200)
            ->assertJsonPath('verification.attempt_count', 2);

        $dashRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/nid/dashboard');

        $dashRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.total_requests', 2)
            ->assertJsonPath('data.summary.verified_count', 1)
            ->assertJsonPath('data.summary.failed_count', 1)
            ->assertJsonPath('data.provider_health.is_mock', true);
    }

    public function test_unseeded_jwt_scope_id_does_not_cause_foreign_key_violation(): void
    {
        $token = $this->createToken([
            'sub' => '838de364-4f84-4393-913b-694a6d90c998',
            'preferred_username' => 'kohinoor_officer',
            'email' => 'kohinoor@modmr.gov.bd',
            'roles' => ['ROLE_PIO'],
            'tier_level' => 'NATIONAL',
            'ancestry_path' => '/',
            'scope_id' => '00000000-0000-0000-0000-000000000001',
        ]);

        $res = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/nid/verifications', [
                'nid_number' => '1990123456789',
                'date_of_birth' => '1990-10-11',
                'submitted_name_en' => 'Mst. Kohinoor Begum',
            ]);

        $res->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('verification.verification_status', 'VERIFIED');
    }

    public function test_keycloak_default_realm_role_prefix_does_not_block_manual_review(): void
    {
        $enumToken = $this->createToken([
            'sub' => 'enum-realm-sub-001',
            'preferred_username' => 'enum_realm',
            'email' => 'enum.realm@modmr.gov.bd',
            'roles' => ['default-roles-idms-gov-realm', 'ROLE_FIELD_ENUMERATOR'],
        ]);

        $drroToken = $this->createToken([
            'sub' => 'drro-realm-sub-001',
            'preferred_username' => 'drro_realm',
            'email' => 'drro.realm@modmr.gov.bd',
            'roles' => ['default-roles-idms-gov-realm', 'offline_access', 'ROLE_DRRO'],
            'tier_level' => 'DISTRICT',
            'ancestry_path' => '/40/47/',
            'scope_id' => $this->chalnaUnion->parent->parent_id,
        ]);

        $initRes = $this->withHeader('Authorization', "Bearer {$enumToken}")
            ->postJson('/api/v1/nid/verifications', [
                'nid_number' => '8888888888',
                'administrative_unit_id' => $this->chalnaUnion->id,
            ])->assertStatus(201);

        $verificationId = $initRes->json('verification.id');

        $this->withHeader('Authorization', "Bearer {$drroToken}")
            ->postJson("/api/v1/nid/verifications/{$verificationId}/manual-review", [
                'decision_status' => 'VERIFIED',
                'override_reason' => 'DRRO inspected original NID card in district office.',
            ])
            ->assertStatus(200)
            ->assertJsonPath('verification.verification_status', 'VERIFIED')
            ->assertJsonPath('verification.manual_override', true);
    }
}
