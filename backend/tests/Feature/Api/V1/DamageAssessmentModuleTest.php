<?php

namespace Tests\Feature\Api\V1;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\AuditLog;
use App\Modules\DamageAssessment\Models\DamageAssessment;
use App\Modules\DamageAssessment\Models\DamageCategory;
use App\Modules\Disaster\Models\DisasterEvent;
use Database\Seeders\AdministrativeUnitSeeder;
use Database\Seeders\DamageAssessmentSeeder;
use Database\Seeders\DisasterEventSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DamageAssessmentModuleTest extends TestCase
{
    use RefreshDatabase;

    protected string $privateKey;
    protected string $cleanX5cCert;
    protected string $kid = 'idms-damage-test-key-2026';
    protected string $realm = 'idms-gov-realm';
    protected string $issuer = 'http://localhost:8080/realms/idms-gov-realm';
    protected string $audience = 'idms-backend-api';

    protected AdministrativeUnit $chalnaUnion;
    protected AdministrativeUnit $dacopeUpazila;
    protected AdministrativeUnit $khulnaDistrict;
    protected AdministrativeUnit $barishalDistrict;
    protected DisasterEvent $disaster;
    protected DamageCategory $housingCat;
    protected DamageCategory $roadsCat;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->seed(AdministrativeUnitSeeder::class);
        $this->seed(DisasterEventSeeder::class);
        $this->seed(DamageAssessmentSeeder::class);

        $this->chalnaUnion = AdministrativeUnit::where('code', '35')->firstOrFail();
        $this->dacopeUpazila = AdministrativeUnit::where('code', '12')->firstOrFail();
        $this->khulnaDistrict = AdministrativeUnit::where('code', '47')->firstOrFail();
        $this->barishalDistrict = AdministrativeUnit::where('code', '10')->firstOrFail();
        $this->disaster = DisasterEvent::where('disaster_code', 'CYC-REMAL-2024')->firstOrFail();
        $this->housingCat = DamageCategory::where('code', 'HOUSING')->firstOrFail();
        $this->roadsCat = DamageCategory::where('code', 'ROADS_BRIDGES')->firstOrFail();

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

    protected function createToken(array $overrides = []): string
    {
        $header = ['alg' => 'RS256', 'typ' => 'JWT', 'kid' => $this->kid];
        $now = time();

        $payload = array_merge([
            'sub' => 'd1111111-1111-4111-8111-111111111111',
            'preferred_username' => 'enumerator_chalna',
            'name' => 'Md. Kamal Hossain',
            'email' => 'enumerator.chalna@modmr.gov.bd',
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'iat' => $now,
            'nbf' => $now - 5,
            'exp' => $now + 3600,
            'roles' => ['ROLE_FIELD_ENUMERATOR'],
            'tier_level' => 'UNION',
            'scope_id' => $this->chalnaUnion->id,
            'ancestry_path' => $this->chalnaUnion->ancestry_path,
            'administrative_profile' => [
                'tier_level' => 'UNION',
                'scope_id' => $this->chalnaUnion->id,
                'ancestry_path' => $this->chalnaUnion->ancestry_path,
                'name_en' => $this->chalnaUnion->name_en,
            ],
        ], $overrides);

        $h = $this->base64UrlEncode(json_encode($header));
        $p = $this->base64UrlEncode(json_encode($payload));
        $sig = '';
        openssl_sign("{$h}.{$p}", $sig, $this->privateKey, OPENSSL_ALGO_SHA256);

        return "{$h}.{$p}." . $this->base64UrlEncode($sig);
    }

    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public function test_configurable_damage_categories_and_permissions(): void
    {
        $enumToken = $this->createToken();
        $ddmToken = $this->createToken([
            'sub' => 'd2222222-2222-4222-8222-222222222222',
            'email' => 'planning.director@modmr.gov.bd',
            'roles' => ['ROLE_DDM_PLANNING_DIRECTOR'],
            'tier_level' => 'DIRECTORATE',
            'scope_id' => $this->khulnaDistrict->id,
            'ancestry_path' => '/',
            'administrative_profile' => [
                'tier_level' => 'DIRECTORATE',
                'scope_id' => $this->khulnaDistrict->id,
                'ancestry_path' => '/',
            ],
        ]);

        // Seeded 9 categories present
        $listRes = $this->withHeader('Authorization', "Bearer {$enumToken}")
            ->getJson('/api/v1/damage/categories')
            ->assertStatus(200);
        $this->assertGreaterThanOrEqual(9, count($listRes->json('data')));

        // Enumerator cannot create custom category
        $this->withHeader('Authorization', "Bearer {$enumToken}")
            ->postJson('/api/v1/damage/categories', [
                'code' => 'CUSTOM_SOLAR',
                'name_en' => 'Solar Grids',
                'name_bn' => 'সৌর বিদ্যুৎ গ্রিড',
            ])
            ->assertStatus(403);

        // Planning Director can create & update configurable category
        $createRes = $this->withHeader('Authorization', "Bearer {$ddmToken}")
            ->postJson('/api/v1/damage/categories', [
                'code' => 'CUSTOM_SOLAR',
                'name_en' => 'Solar Mini-Grids',
                'name_bn' => 'সৌর মিনি-গ্রিড',
                'default_unit' => 'SYSTEMS',
                'color_hex' => '#ffc107',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.code', 'CUSTOM_SOLAR')
            ->assertJsonPath('data.is_system', false);

        $catId = $createRes->json('data.id');
        $this->withHeader('Authorization', "Bearer {$ddmToken}")
            ->putJson("/api/v1/damage/categories/{$catId}", [
                'name_en' => 'Coastal Solar Mini-Grids',
                'name_bn' => 'উপকূলীয় সৌর মিনি-গ্রিড',
                'is_active' => true,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.name_en', 'Coastal Solar Mini-Grids');
    }

    public function test_full_dform_lifecycle_4eye_sod_correction_request_and_attachments(): void
    {
        $enumToken = $this->createToken();

        $pioToken = $this->createToken([
            'sub' => 'd3333333-3333-4333-8333-333333333333',
            'email' => 'pio.dacope@modmr.gov.bd',
            'name' => 'Upazila PIO Dacope',
            'roles' => ['ROLE_PIO'],
            'tier_level' => 'UPAZILA',
            'scope_id' => $this->dacopeUpazila->id,
            'ancestry_path' => $this->dacopeUpazila->ancestry_path,
            'administrative_profile' => [
                'tier_level' => 'UPAZILA',
                'scope_id' => $this->dacopeUpazila->id,
                'ancestry_path' => $this->dacopeUpazila->ancestry_path,
            ],
        ]);

        $drroToken = $this->createToken([
            'sub' => 'd4444444-4444-4444-8444-444444444444',
            'email' => 'drro.khulna@modmr.gov.bd',
            'name' => 'DRRO Khulna',
            'roles' => ['ROLE_DRRO'],
            'tier_level' => 'DISTRICT',
            'scope_id' => $this->khulnaDistrict->id,
            'ancestry_path' => $this->khulnaDistrict->ancestry_path,
            'administrative_profile' => [
                'tier_level' => 'DISTRICT',
                'scope_id' => $this->khulnaDistrict->id,
                'ancestry_path' => $this->khulnaDistrict->ancestry_path,
            ],
        ]);

        // 1. Enumerator registers DRAFT D-Form
        $createRes = $this->withHeader('Authorization', "Bearer {$enumToken}")
            ->postJson('/api/v1/damage/assessments', [
                'disaster_event_id' => $this->disaster->id,
                'administrative_unit_id' => $this->chalnaUnion->id,
                'assessment_type' => 'PRELIMINARY_D_FORM',
                'assessment_date' => '2026-11-24',
                'assessing_organization' => 'Chalna Union DMC Survey Unit',
                'team_name' => 'Ward-4 Assessment Team',
                'assessor_name' => 'Md. Kamal Hossain',
                'assessor_contact' => '+8801711223344',
                'ward_no' => 'Ward-04',
                'latitude' => 22.6105,
                'longitude' => 89.5142,
                'affected_households' => 220,
                'affected_population' => 980,
                'field_observations' => 'Initial ward survey completed.',
                'locations' => [
                    [
                        'administrative_unit_id' => $this->chalnaUnion->id,
                        'ward_no' => 'Ward-04',
                        'locality_name_en' => 'Chalna Bazaar',
                        'affected_households' => 220,
                        'affected_population' => 980,
                        'severity_level' => 'SEVERE',
                        'latitude' => 22.6105,
                        'longitude' => 89.5142,
                    ],
                ],
                'sector_items' => [
                    [
                        'category_id' => $this->housingCat->id,
                        'item_title' => 'Tinshed & Katcha Houses',
                        'fully_damaged_count' => 45,
                        'partially_damaged_count' => 90,
                        'minor_damaged_count' => 30,
                        'estimated_loss_bdt' => 4500000,
                    ],
                    [
                        'category_id' => $this->roadsCat->id,
                        'item_title' => 'Union Feeder Road',
                        'fully_damaged_count' => 2,
                        'partially_damaged_count' => 5,
                        'minor_damaged_count' => 3,
                        'estimated_loss_bdt' => 1500000,
                    ],
                ],
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.verification_status', 'DRAFT')
            ->assertJsonPath('data.total_fully_damaged', 47)
            ->assertJsonPath('data.total_estimated_loss_bdt', 6000000);

        $assessmentId = $createRes->json('data.id');

        // 2. Upload geo-tagged photograph & download it
        $photo = UploadedFile::fake()->image('damaged_bridge.jpg', 800, 600);
        $attRes = $this->withHeader('Authorization', "Bearer {$enumToken}")
            ->post("/api/v1/damage/assessments/{$assessmentId}/attachments", [
                'file' => $photo,
                'attachment_kind' => 'PHOTOGRAPH',
                'title' => 'Breached Road Culvert',
                'latitude' => 22.6108,
                'longitude' => 89.5145,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.attachment_kind', 'PHOTOGRAPH');

        $attId = $attRes->json('data.id');
        $this->assertNotEmpty($attRes->json('data.sha256_hash'));

        $this->withHeader('Authorization', "Bearer {$enumToken}")
            ->get("/api/v1/damage/assessments/{$assessmentId}/attachments/{$attId}")
            ->assertStatus(200);

        // 3. Submit assessment
        $this->withHeader('Authorization', "Bearer {$enumToken}")
            ->postJson("/api/v1/damage/assessments/{$assessmentId}/submit", ['notes' => 'Ready for PIO review.'])
            ->assertStatus(200)
            ->assertJsonPath('data.verification_status', 'SUBMITTED');

        // 4. 4-Eye SoD: Submitter/Creator cannot verify their own assessment (even if role were PIO)
        $selfVerifyToken = $this->createToken([
            'sub' => 'd1111111-1111-4111-8111-111111111111',
            'email' => 'enumerator.chalna@modmr.gov.bd',
            'roles' => ['ROLE_PIO'],
            'tier_level' => 'UPAZILA',
            'scope_id' => $this->dacopeUpazila->id,
            'ancestry_path' => $this->dacopeUpazila->ancestry_path,
        ]);
        $this->withHeader('Authorization', "Bearer {$selfVerifyToken}")
            ->postJson("/api/v1/damage/assessments/{$assessmentId}/verify")
            ->assertStatus(403);

        // 5. Upazila PIO starts verification & requests correction
        $this->withHeader('Authorization', "Bearer {$pioToken}")
            ->postJson("/api/v1/damage/assessments/{$assessmentId}/start-verification")
            ->assertStatus(200)
            ->assertJsonPath('data.verification_status', 'UNDER_VERIFICATION');

        $this->withHeader('Authorization', "Bearer {$pioToken}")
            ->postJson("/api/v1/damage/assessments/{$assessmentId}/request-correction", [
                'notes' => 'Please verify housing fully-damaged count in Ward-04.',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.verification_status', 'CORRECTION_REQUESTED');

        // 6. Field officer edits & resubmits
        $this->withHeader('Authorization', "Bearer {$enumToken}")
            ->putJson("/api/v1/damage/assessments/{$assessmentId}", [
                'disaster_event_id' => $this->disaster->id,
                'administrative_unit_id' => $this->chalnaUnion->id,
                'assessment_type' => 'PRELIMINARY_D_FORM',
                'assessment_date' => '2026-11-24',
                'assessing_organization' => 'Chalna Union DMC Survey Unit',
                'affected_households' => 235,
                'affected_population' => 1040,
                'sector_items' => [
                    [
                        'category_id' => $this->housingCat->id,
                        'fully_damaged_count' => 50,
                        'partially_damaged_count' => 95,
                        'minor_damaged_count' => 30,
                        'estimated_loss_bdt' => 5000000,
                    ],
                ],
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.total_estimated_loss_bdt', 5000000);

        $this->withHeader('Authorization', "Bearer {$enumToken}")
            ->postJson("/api/v1/damage/assessments/{$assessmentId}/submit")
            ->assertStatus(200)
            ->assertJsonPath('data.verification_status', 'SUBMITTED');

        // 7. PIO verifies -> DRRO approves
        $this->withHeader('Authorization', "Bearer {$pioToken}")
            ->postJson("/api/v1/damage/assessments/{$assessmentId}/verify", ['notes' => 'Verified on field cross-check.'])
            ->assertStatus(200)
            ->assertJsonPath('data.verification_status', 'VERIFIED');

        $detailRes = $this->withHeader('Authorization', "Bearer {$drroToken}")
            ->postJson("/api/v1/damage/assessments/{$assessmentId}/approve", ['notes' => 'Approved for district D-Form consolidation.'])
            ->assertStatus(200)
            ->assertJsonPath('data.verification_status', 'APPROVED');

        $this->assertGreaterThanOrEqual(6, count($detailRes->json('data.verification_logs')));
        $this->assertTrue(AuditLog::where('auditable_id', $assessmentId)->exists());
    }

    public function test_jurisdiction_scoping_super_admin_blackout_and_external_partner_masking(): void
    {
        $barishalAssessment = DamageAssessment::where('d_form_code', 'DF-MODMR-2026-0003')->firstOrFail();
        $khulnaApproved = DamageAssessment::where('d_form_code', 'DF-MODMR-2026-0001')->firstOrFail();

        $khulnaDrroToken = $this->createToken([
            'sub' => 'd5555555-5555-4555-8555-555555555555',
            'email' => 'drro.khulna@modmr.gov.bd',
            'roles' => ['ROLE_DRRO'],
            'tier_level' => 'DISTRICT',
            'scope_id' => $this->khulnaDistrict->id,
            'ancestry_path' => $this->khulnaDistrict->ancestry_path,
            'administrative_profile' => [
                'tier_level' => 'DISTRICT',
                'scope_id' => $this->khulnaDistrict->id,
                'ancestry_path' => $this->khulnaDistrict->ancestry_path,
            ],
        ]);

        // Lateral isolation: Khulna DRRO cannot view Barishal assessment
        $this->withHeader('Authorization', "Bearer {$khulnaDrroToken}")
            ->getJson("/api/v1/damage/assessments/{$barishalAssessment->id}")
            ->assertStatus(403);

        // Downward visibility: Khulna DRRO can view Chalna Union assessment
        $this->withHeader('Authorization', "Bearer {$khulnaDrroToken}")
            ->getJson("/api/v1/damage/assessments/{$khulnaApproved->id}")
            ->assertStatus(200);

        // Super Admin blackout: allowed to view, blocked from creating
        $superAdminToken = $this->createToken([
            'sub' => 'd6666666-6666-4666-8666-666666666666',
            'email' => 'superadmin@modmr.gov.bd',
            'roles' => ['ROLE_SUPER_ADMIN'],
            'tier_level' => 'NATIONAL',
            'ancestry_path' => '/',
            'administrative_profile' => ['tier_level' => 'NATIONAL', 'ancestry_path' => '/'],
        ]);

        $this->withHeader('Authorization', "Bearer {$superAdminToken}")
            ->getJson('/api/v1/damage/assessments')
            ->assertStatus(200);

        $this->withHeader('Authorization', "Bearer {$superAdminToken}")
            ->postJson('/api/v1/damage/assessments', [
                'disaster_event_id' => $this->disaster->id,
                'administrative_unit_id' => $this->chalnaUnion->id,
                'assessment_type' => 'INITIAL_RAPID',
                'assessment_date' => '2026-11-25',
                'assessing_organization' => 'ICT Admin',
                'affected_households' => 10,
                'affected_population' => 40,
                'sector_items' => [
                    ['category_id' => $this->housingCat->id, 'fully_damaged_count' => 2, 'partially_damaged_count' => 3, 'estimated_loss_bdt' => 100000],
                ],
            ])
            ->assertStatus(403);

        // External Partner: sees APPROVED/VERIFIED with assessor PII redacted, blocked from SUBMITTED/DRAFT
        $partnerToken = $this->createToken([
            'sub' => 'd7777777-7777-4777-8777-777777777777',
            'email' => 'observer@wfp.org',
            'roles' => ['ROLE_EXTERNAL_PARTNER'],
            'tier_level' => 'NATIONAL',
            'ancestry_path' => '/',
            'administrative_profile' => ['tier_level' => 'NATIONAL', 'ancestry_path' => '/'],
        ]);

        $this->withHeader('Authorization', "Bearer {$partnerToken}")
            ->getJson("/api/v1/damage/assessments/{$khulnaApproved->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.assessor_name', 'REDACTED')
            ->assertJsonPath('data.assessor_contact', null);

        $this->withHeader('Authorization', "Bearer {$partnerToken}")
            ->getJson("/api/v1/damage/assessments/{$barishalAssessment->id}")
            ->assertStatus(403);

        // Approved assessment is immutable: late attachment upload is rejected (422)
        $enumTokenForApproved = $this->createToken();
        $this->withHeader('Authorization', "Bearer {$enumTokenForApproved}")
            ->post("/api/v1/damage/assessments/{$khulnaApproved->id}/attachments", [
                'file' => UploadedFile::fake()->image('late.jpg', 200, 200),
            ])
            ->assertStatus(422);

        // Cross-jurisdiction offline sync probe using Barishal's client_uuid is blocked (403)
        $this->withHeader('Authorization', "Bearer {$enumTokenForApproved}")
            ->postJson('/api/v1/damage/assessments/sync', [
                'records' => [
                    [
                        'client_uuid' => $barishalAssessment->client_uuid,
                        'disaster_event_id' => $this->disaster->id,
                        'administrative_unit_id' => $this->chalnaUnion->id,
                        'assessment_type' => 'INITIAL_RAPID',
                        'assessment_date' => '2026-11-25',
                        'assessing_organization' => 'Chalna Unit',
                        'affected_households' => 10,
                        'affected_population' => 40,
                        'sector_items' => [
                            ['sector_code' => 'HOUSING', 'fully_damaged_count' => 1, 'partially_damaged_count' => 2, 'estimated_loss_bdt' => 50000],
                        ],
                    ],
                ],
            ])
            ->assertStatus(403);
    }

    public function test_offline_mobile_sync_idempotency_and_conflict_detection(): void
    {
        $enumToken = $this->createToken();
        $clientUuid = 'f1111111-1111-4111-8111-111111111199';
        $idempotencyKey = 'flutter-sync-2026-001';

        $syncPayload = [
            'records' => [
                [
                    'client_uuid' => $clientUuid,
                    'idempotency_key' => $idempotencyKey,
                    'device_id' => 'FLUTTER-ANDROID-PIXEL-01',
                    'offline_created_at' => '2026-11-25T08:30:00Z',
                    'sync_version' => 1,
                    'disaster_event_id' => $this->disaster->id,
                    'administrative_unit_id' => $this->chalnaUnion->id,
                    'assessment_type' => 'INITIAL_RAPID',
                    'assessment_date' => '2026-11-25',
                    'assessing_organization' => 'Chalna Mobile Field Unit',
                    'assessor_name' => 'Md. Kamal Hossain',
                    'latitude' => 22.6122,
                    'longitude' => 89.5101,
                    'affected_households' => 65,
                    'affected_population' => 280,
                    'sector_items' => [
                        [
                            'sector_code' => 'HOUSING',
                            'fully_damaged_count' => 10,
                            'partially_damaged_count' => 25,
                            'minor_damaged_count' => 12,
                            'estimated_loss_bdt' => 950000,
                        ],
                    ],
                ],
            ],
        ];

        // First sync creates record
        $res1 = $this->withHeader('Authorization', "Bearer {$enumToken}")
            ->postJson('/api/v1/damage/assessments/sync', $syncPayload)
            ->assertStatus(200)
            ->assertJsonPath('data.results.0.outcome', 'CREATED');

        $createdId = $res1->json('data.results.0.assessment_id');

        // Replaying same idempotency_key skips duplicate creation
        $this->withHeader('Authorization', "Bearer {$enumToken}")
            ->postJson('/api/v1/damage/assessments/sync', $syncPayload)
            ->assertStatus(200)
            ->assertJsonPath('data.results.0.outcome', 'DUPLICATE_SKIPPED')
            ->assertJsonPath('data.results.0.assessment_id', $createdId);

        // Update on server increments sync_version to 2, then stale client sync triggers CONFLICT
        $assessment = DamageAssessment::findOrFail($createdId);
        $assessment->update(['sync_version' => 2]);

        $stalePayload = $syncPayload;
        $stalePayload['records'][0]['idempotency_key'] = 'flutter-sync-2026-002';
        $stalePayload['records'][0]['sync_version'] = 1;

        $this->withHeader('Authorization', "Bearer {$enumToken}")
            ->postJson('/api/v1/damage/assessments/sync', $stalePayload)
            ->assertStatus(200)
            ->assertJsonPath('data.results.0.outcome', 'CONFLICT');
    }

    public function test_gis_dashboard_reports_and_csv_export(): void
    {
        $nationalToken = $this->createToken([
            'sub' => 'd8888888-8888-4888-8888-888888888888',
            'email' => 'dg.ddm@modmr.gov.bd',
            'roles' => ['ROLE_DDM_DIRECTOR_GENERAL'],
            'tier_level' => 'DIRECTORATE',
            'ancestry_path' => '/',
            'administrative_profile' => ['tier_level' => 'DIRECTORATE', 'ancestry_path' => '/'],
        ]);

        // Dashboard metrics
        $dashRes = $this->withHeader('Authorization', "Bearer {$nationalToken}")
            ->getJson('/api/v1/damage/dashboard')
            ->assertStatus(200);
        $this->assertGreaterThanOrEqual(3, $dashRes->json('data.summary.total_assessments'));
        $this->assertGreaterThanOrEqual(2, $dashRes->json('data.summary.verified_assessments'));
        $this->assertNotEmpty($dashRes->json('data.damage_by_category'));
        $this->assertNotEmpty($dashRes->json('data.damage_by_area'));

        // GIS GeoJSON, Heatmap, Spatial Aggregation
        $geoRes = $this->withHeader('Authorization', "Bearer {$nationalToken}")
            ->getJson('/api/v1/damage/gis/geojson')
            ->assertStatus(200)
            ->assertJsonPath('data.type', 'FeatureCollection');
        $this->assertNotEmpty($geoRes->json('data.features'));

        $heatRes = $this->withHeader('Authorization', "Bearer {$nationalToken}")
            ->getJson('/api/v1/damage/gis/heatmap')
            ->assertStatus(200);
        $this->assertNotEmpty($heatRes->json('data.points'));
        $this->assertNotEmpty($heatRes->json('data.area_severity'));

        $aggRes = $this->withHeader('Authorization', "Bearer {$nationalToken}")
            ->getJson('/api/v1/damage/gis/spatial-aggregation')
            ->assertStatus(200);
        $this->assertNotEmpty($aggRes->json('data.areas'));

        // Reports default to verified & approved only (2 seeded verified/approved vs 1 submitted)
        $repRes = $this->withHeader('Authorization', "Bearer {$nationalToken}")
            ->getJson('/api/v1/damage/reports?type=disaster')
            ->assertStatus(200)
            ->assertJsonPath('data.verified_only', true)
            ->assertJsonPath('data.total_assessments', 2);

        $this->assertNotEmpty($repRes->json('data.disaster_wise'));
        $this->assertNotEmpty($repRes->json('data.location_wise'));
        $this->assertNotEmpty($repRes->json('data.category_wise'));
        $this->assertNotEmpty($repRes->json('data.financial_loss'));

        // Export CSV
        $csvRes = $this->withHeader('Authorization', "Bearer {$nationalToken}")
            ->get('/api/v1/damage/export')
            ->assertStatus(200);
        $this->assertStringContainsString('text/csv', (string) $csvRes->headers->get('content-type'));
    }
}
