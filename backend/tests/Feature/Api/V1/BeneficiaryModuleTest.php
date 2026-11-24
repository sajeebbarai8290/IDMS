<?php

namespace Tests\Feature\Api\V1;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\AuditLog;
use App\Modules\Beneficiary\Models\Household;
use App\Modules\Disaster\Models\DisasterEvent;
use Database\Seeders\AdministrativeUnitSeeder;
use Database\Seeders\DisasterEventSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BeneficiaryModuleTest extends TestCase
{
    use RefreshDatabase;

    protected string $privateKey;
    protected string $publicKey;
    protected string $cleanX5cCert;
    protected string $kid = 'idms-test-key-2026';
    protected string $realm = 'idms-gov-realm';
    protected string $issuer = 'http://localhost:8080/realms/idms-gov-realm';
    protected string $audience = 'idms-backend-api';

    protected AdministrativeUnit $chalnaUnion;
    protected AdministrativeUnit $barishalDistrict;
    protected DisasterEvent $remalCyclone;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed administrative units and disasters
        $this->seed(AdministrativeUnitSeeder::class);
        $this->seed(DisasterEventSeeder::class);

        $this->chalnaUnion = AdministrativeUnit::where('code', '35')->firstOrFail();
        $this->barishalDistrict = AdministrativeUnit::where('code', '10')->firstOrFail();
        $this->remalCyclone = DisasterEvent::where('disaster_code', 'CYC-REMAL-2024')->firstOrFail();

        // SSL config for Windows OpenSSL
        $configPath = null;
        $candidates = [
            'C:/wamp64/bin/php/php8.4.15/extras/ssl/openssl.cnf',
            'C:/wamp64/bin/php/php8.3.28/extras/ssl/openssl.cnf',
            'C:/wamp64/bin/apache/apache2.4.63/conf/openssl.cnf',
        ];
        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                $configPath = $candidate;
                putenv("OPENSSL_CONF={$candidate}");
                break;
            }
        }

        $sslOpts = $configPath ? ['config' => $configPath] : [];
        $keyOpts = array_merge($sslOpts, [
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        $res = openssl_pkey_new($keyOpts);
        $privKey = '';
        openssl_pkey_export($res, $privKey, null, $sslOpts);
        $this->privateKey = $privKey;
        $details = openssl_pkey_get_details($res);
        $this->publicKey = $details['key'];

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
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
            'kid' => $this->kid,
        ];

        $now = time();
        $payload = array_merge([
            'sub' => 'b661334c-687f-4ca7-b24e-4b6807890501',
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

        $headerB64 = $this->base64UrlEncode(json_encode($header));
        $payloadB64 = $this->base64UrlEncode(json_encode($payload));
        $signingInput = "{$headerB64}.{$payloadB64}";

        openssl_sign($signingInput, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);
        $signatureB64 = $this->base64UrlEncode($signature);

        return "{$signingInput}.{$signatureB64}";
    }

    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * 1. Unauthenticated requests are rejected.
     */
    public function test_unauthenticated_request_rejected(): void
    {
        $response = $this->getJson('/api/v1/beneficiary/households');
        $response->assertStatus(401);
    }

    /**
     * 2. Can register household with encrypted PII, masked response, and vulnerability score.
     */
    public function test_can_register_household_with_vulnerability_and_pii_protection(): void
    {
        $token = $this->createToken();

        $payload = [
            'administrative_unit_id' => $this->chalnaUnion->id,
            'village_ward_name' => 'Chalna Bazar',
            'present_address' => 'Holding 14, Ward 02, Chalna',
            'hoh_name_en' => 'Fatema Begum',
            'hoh_name_bn' => 'ফাতেমা বেগম',
            'hoh_nid' => '1992471234567',
            'hoh_gender' => 'FEMALE',
            'hoh_mobile' => '01711223344',
            'monthly_income_bdt' => 3500,
            'is_female_headed' => true,
            'has_disabled' => true,
            'is_displaced' => true,
            'latitude' => 22.6021,
            'longitude' => 89.5312,
            'members' => [
                [
                    'full_name_en' => 'Rahim Ali',
                    'relationship_to_hoh' => 'CHILD',
                    'gender' => 'MALE',
                    'age' => 4,
                    'has_disability' => true,
                ],
            ],
        ];

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/beneficiary/households', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('household.hoh_name_en', 'Fatema Begum')
            ->assertJsonPath('household.is_nid_unmasked', false);

        // Verify NID is masked in response
        $maskedNid = $response->json('household.hoh_nid');
        $this->assertStringContainsString('*', $maskedNid);
        $this->assertNotEquals('1992471234567', $maskedNid);

        // Verify Vulnerability Score: Female (15) + Extreme Poor (25) + Disabled (20) + Infant (10) + Displaced (15) = 85.00
        $score = $response->json('household.vulnerability.score');
        $this->assertEquals(85.00, $score);

        // Verify raw NID is encrypted in database
        $household = Household::where('hoh_name_en', 'Fatema Begum')->firstOrFail();
        $this->assertNotEquals('1992471234567', $household->hoh_nid_encrypted);
        $this->assertEquals('1992471234567', $household->getDecryptedNid());
    }

    /**
     * 3. Duplicate NID registration is rejected.
     */
    public function test_duplicate_nid_is_prevented(): void
    {
        $token = $this->createToken();

        $payload = [
            'administrative_unit_id' => $this->chalnaUnion->id,
            'present_address' => 'Chalna',
            'hoh_name_en' => 'Original Person',
            'hoh_name_bn' => 'আসল ব্যক্তি',
            'hoh_nid' => '1980112233445',
            'hoh_gender' => 'MALE',
        ];

        $first = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/beneficiary/households', $payload);
        $first->assertStatus(201);

        // Second registration with same NID
        $second = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/beneficiary/households', array_merge($payload, [
                'hoh_name_en' => 'Duplicate Person',
            ]));

        $second->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    /**
     * 4. Adding and removing household members updates the vulnerability score.
     */
    public function test_member_management_recalculates_score(): void
    {
        $token = $this->createToken();

        $payload = [
            'administrative_unit_id' => $this->chalnaUnion->id,
            'present_address' => 'Chalna',
            'hoh_name_en' => 'Abul Kashem',
            'hoh_name_bn' => 'আবুল কাশেম',
            'hoh_nid' => '1975112233441',
            'hoh_gender' => 'MALE',
            'monthly_income_bdt' => 15000, // Lower middle (+5)
        ];

        $createRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/beneficiary/households', $payload);
        $createRes->assertStatus(201);
        $householdId = $createRes->json('household.id');

        $initialScore = $createRes->json('household.vulnerability.score');
        $this->assertEquals(5.00, $initialScore);

        // Add a disabled elderly member (Elderly: +15, Disabled: +20 -> +35)
        $memberRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/beneficiary/households/{$householdId}/members", [
                'full_name_en' => 'Rokeya Begum (Mother)',
                'relationship_to_hoh' => 'PARENT',
                'gender' => 'FEMALE',
                'age' => 72,
                'has_disability' => true,
            ]);

        $memberRes->assertStatus(201);
        $newScore = $memberRes->json('updated_vulnerability_score');
        $this->assertEquals(40.00, $newScore);

        // Remove the member
        $memberId = $memberRes->json('member.id');
        $deleteRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/beneficiary/households/{$householdId}/members/{$memberId}");

        $deleteRes->assertStatus(200);
        $this->assertEquals(5.00, $deleteRes->json('updated_vulnerability_score'));
    }

    /**
     * 5. Gated PII reveal endpoint requires authorization and records an audit log.
     */
    public function test_pii_decryption_is_gated_and_audited(): void
    {
        $token = $this->createToken(['roles' => ['ROLE_PIO']]);

        $createRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/beneficiary/households', [
                'administrative_unit_id' => $this->chalnaUnion->id,
                'present_address' => 'Chalna',
                'hoh_name_en' => 'Citizen X',
                'hoh_name_bn' => 'নাগরিক এক্স',
                'hoh_nid' => '1999988776655',
                'hoh_mobile' => '01899887766',
                'hoh_gender' => 'MALE',
            ]);
        $householdId = $createRes->json('household.id');

        // Normal show returns masked NID
        $showRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/beneficiary/households/{$householdId}");
        $showRes->assertStatus(200)
            ->assertJsonPath('household.is_nid_unmasked', false);

        // Reveal PII endpoint
        $revealRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/beneficiary/households/{$householdId}/reveal-pii");

        $revealRes->assertStatus(200)
            ->assertJsonPath('unmasked_nid', '1999988776655')
            ->assertJsonPath('unmasked_mobile', '01899887766');

        // Verify audit log exists
        $audit = AuditLog::where('action_name', 'PII_DECRYPTION_ACCESSED')->latest('created_at')->first();
        $this->assertNotNull($audit);
    }

    /**
     * 6. 7-Tier Administrative Scope Isolation (Lateral isolation between jurisdictions).
     */
    public function test_administrative_scope_isolates_records(): void
    {
        // PIO Dacope (/40/47/12/) creates a household in Dacope (Chalna)
        $dacopeToken = $this->createToken([
            'preferred_username' => 'pio_dacope',
            'roles' => ['ROLE_PIO'],
            'ancestry_path' => '/40/47/12/',
        ]);

        $createRes = $this->withHeader('Authorization', "Bearer {$dacopeToken}")
            ->postJson('/api/v1/beneficiary/households', [
                'administrative_unit_id' => $this->chalnaUnion->id,
                'present_address' => 'Dacope Ward',
                'hoh_name_en' => 'Dacope Resident',
                'hoh_name_bn' => 'দাকোপ বাসিন্দা',
                'hoh_nid' => '1988877665544',
                'hoh_gender' => 'MALE',
            ]);
        $householdId = $createRes->json('household.id');

        // UNO in Barishal District (/10/) tries to view Dacope's household
        $barishalToken = $this->createToken([
            'preferred_username' => 'uno_barishal',
            'roles' => ['ROLE_UNO'],
            'ancestry_path' => '/10/',
            'scope_id' => $this->barishalDistrict->id,
        ]);

        $unauthorizedRes = $this->withHeader('Authorization', "Bearer {$barishalToken}")
            ->getJson("/api/v1/beneficiary/households/{$householdId}");

        $unauthorizedRes->assertStatus(403);
    }

    /**
     * 7. National roles have universal read visibility across all administrative units.
     */
    public function test_national_scope_has_universal_read_access(): void
    {
        $dacopeToken = $this->createToken();
        $createRes = $this->withHeader('Authorization', "Bearer {$dacopeToken}")
            ->postJson('/api/v1/beneficiary/households', [
                'administrative_unit_id' => $this->chalnaUnion->id,
                'present_address' => 'Dacope',
                'hoh_name_en' => 'National Test Resident',
                'hoh_name_bn' => 'জাতীয় টেস্ট বাসিন্দা',
                'hoh_nid' => '1966554433221',
                'hoh_gender' => 'MALE',
            ]);
        $householdId = $createRes->json('household.id');

        // National Auditor has '/' path and national role
        $nationalToken = $this->createToken([
            'preferred_username' => 'national_auditor',
            'roles' => ['ROLE_NATIONAL_AUDITOR'],
            'ancestry_path' => '/',
            'tier_level' => 'NATIONAL',
        ]);

        $res = $this->withHeader('Authorization', "Bearer {$nationalToken}")
            ->getJson("/api/v1/beneficiary/households/{$householdId}");

        $res->assertStatus(200)
            ->assertJsonPath('household.hoh_name_en', 'National Test Resident');
    }

    /**
     * 8. Mock NID Verification Gateway returns cryptographic token.
     */
    public function test_nid_verification_via_gateway(): void
    {
        $token = $this->createToken();

        $res = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/beneficiary/verify-nid', [
                'nid_number' => '1990471234567',
                'date_of_birth' => '1990-05-12',
            ]);

        $res->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('verification.verification_status', 'VERIFIED');

        $this->assertNotNull($res->json('verification.verification_token'));
    }

    /**
     * 9. Multi-stage verification workflow.
     */
    public function test_multi_stage_verification_workflow(): void
    {
        $token = $this->createToken();

        $createRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/beneficiary/households', [
                'administrative_unit_id' => $this->chalnaUnion->id,
                'present_address' => 'Chalna',
                'hoh_name_en' => 'Survey Candidate',
                'hoh_name_bn' => 'সার্ভে প্রার্থী',
                'hoh_nid' => '1984556677889',
                'hoh_gender' => 'MALE',
            ]);
        $householdId = $createRes->json('household.id');

        // Step 1: Field survey step
        $stepRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/beneficiary/households/{$householdId}/verify-step", [
                'stage' => 'FIELD_SURVEY',
                'status' => 'VERIFIED',
                'remarks' => 'Dwelling physically inspected by enumerator.',
            ]);

        $stepRes->assertStatus(200)
            ->assertJsonPath('household.verification.field_verified', true)
            ->assertJsonPath('household.verification.status', 'FIELD_VERIFIED');

        // Step 2: Final approval by PIO
        $approveRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/beneficiary/households/{$householdId}/approval", [
                'action' => 'APPROVE',
                'remarks' => 'Approved by Upazila Disaster Management Committee.',
            ]);

        $approveRes->assertStatus(200)
            ->assertJsonPath('household.verification.status', 'APPROVED');
    }

    /**
     * 10. Field Enumerator cannot approve relief entitlement.
     */
    public function test_field_enumerator_cannot_grant_final_approval(): void
    {
        $token = $this->createToken();

        $createRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/beneficiary/households', [
                'administrative_unit_id' => $this->chalnaUnion->id,
                'present_address' => 'Chalna',
                'hoh_name_en' => 'Applicant Y',
                'hoh_name_bn' => 'আবেদনকারী ওয়াই',
                'hoh_nid' => '1981122334455',
                'hoh_gender' => 'MALE',
            ]);
        $householdId = $createRes->json('household.id');

        // Enumerator token
        $enumToken = $this->createToken([
            'preferred_username' => 'enumerator_1',
            'roles' => ['ROLE_FIELD_ENUMERATOR'],
        ]);

        $res = $this->withHeader('Authorization', "Bearer {$enumToken}")
            ->postJson("/api/v1/beneficiary/households/{$householdId}/approval", [
                'action' => 'APPROVE',
            ]);

        $res->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.details.authorization.0', 'Field Enumerators are prohibited from granting final relief entitlement approvals.');
    }

    /**
     * 11. Disaster Linking and Assistance Disbursement Tracking.
     */
    public function test_disaster_linking_and_assistance_disbursement(): void
    {
        $token = $this->createToken();

        $createRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/beneficiary/households', [
                'administrative_unit_id' => $this->chalnaUnion->id,
                'present_address' => 'Chalna',
                'hoh_name_en' => 'Disaster Victim',
                'hoh_name_bn' => 'দুর্যোগ ক্ষতিগ্রস্ত',
                'hoh_nid' => '1983344556677',
                'hoh_gender' => 'MALE',
            ]);
        $householdId = $createRes->json('household.id');

        // Link to Cyclone Remal
        $linkRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/beneficiary/households/{$householdId}/disaster-eligibility", [
                'disaster_event_id' => $this->remalCyclone->id,
                'eligibility_criteria' => 'House completely blown away during Remal landfall.',
            ]);

        $linkRes->assertStatus(201)
            ->assertJsonPath('eligibility.disaster_code', 'CYC-REMAL-2024')
            ->assertJsonPath('eligibility.assistance_count', 0);

        $eligibilityId = $linkRes->json('eligibility.id');

        // Disburse relief
        $disburseRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/beneficiary/households/{$householdId}/disaster-eligibility/{$eligibilityId}/disburse");

        $disburseRes->assertStatus(200)
            ->assertJsonPath('assistance_count', 1);
    }

    /**
     * 12. Dashboard metrics and geographical breakdown.
     */
    public function test_dashboard_metrics_and_geo_breakdown(): void
    {
        $token = $this->createToken();

        // Register two households
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/beneficiary/households', [
                'administrative_unit_id' => $this->chalnaUnion->id,
                'present_address' => 'Chalna',
                'hoh_name_en' => 'Person A',
                'hoh_name_bn' => 'ব্যক্তি ক',
                'hoh_nid' => '1982233445566',
                'hoh_gender' => 'FEMALE',
                'monthly_income_bdt' => 3000,
            ]);

        $metricsRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/beneficiary/dashboard/metrics');

        $metricsRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertGreaterThanOrEqual(1, $metricsRes->json('metrics.total_households'));

        $geoRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/beneficiary/dashboard/geo-breakdown');

        $geoRes->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    /**
     * 13. CSV Batch Import and Export.
     */
    public function test_csv_batch_import_and_export(): void
    {
        $token = $this->createToken();

        $csv = "hoh_name_en,hoh_name_bn,nid,mobile,gender,dob,admin_code,village,income,member_count,has_disabled\n" .
            "Abdul Barek,আব্দুল বারেক,1970471234567,01700112233,MALE,1970-01-01,35,Chalna,4000,3,1\n" .
            "Momena Khatun,মোমেনা খাতুন,1975471234568,01700112234,FEMALE,1975-02-02,35,Chalna,2500,2,0\n";

        $importRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/beneficiary/import', [
                'csv_data' => $csv,
            ]);

        $importRes->assertStatus(200)
            ->assertJsonPath('created', 2)
            ->assertJsonPath('failed', 0);

        // Export CSV
        $exportRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->get('/api/v1/beneficiary/export');

        $exportRes->assertStatus(200);
        $this->assertStringContainsString('Abdul Barek', $exportRes->getContent());
        $this->assertStringContainsString('Momena Khatun', $exportRes->getContent());
    }
}
