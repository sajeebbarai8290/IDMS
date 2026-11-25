<?php

namespace Tests\Feature\Api\V1;

use App\Core\Models\AdministrativeUnit;
use App\Modules\Disaster\Jobs\DispatchDisasterWarningJob;
use Database\Seeders\AdministrativeUnitSeeder;
use Database\Seeders\DisasterEventSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DisasterModuleTest extends TestCase
{
    use RefreshDatabase;

    protected string $privateKey;
    protected string $cleanX5cCert;
    protected string $kid = 'idms-disaster-test-key-2026';
    protected string $realm = 'idms-gov-realm';
    protected string $issuer = 'http://localhost:8080/realms/idms-gov-realm';
    protected string $audience = 'idms-backend-api';

    protected AdministrativeUnit $chalnaUnion;
    protected AdministrativeUnit $dacopeUpazila;
    protected AdministrativeUnit $khulnaDistrict;
    protected AdministrativeUnit $barishalDistrict;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AdministrativeUnitSeeder::class);
        $this->seed(DisasterEventSeeder::class);

        $this->chalnaUnion = AdministrativeUnit::where('code', '35')->firstOrFail();
        $this->dacopeUpazila = AdministrativeUnit::where('code', '12')->firstOrFail();
        $this->khulnaDistrict = AdministrativeUnit::where('code', '47')->firstOrFail();
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

    protected function createToken(array $overrides = []): string
    {
        $header = ['alg' => 'RS256', 'typ' => 'JWT', 'kid' => $this->kid];
        $now = time();

        $payload = array_merge([
            'sub' => 'c111334c-687f-4ca7-b24e-4b6807890701',
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
            'scope_id' => $this->dacopeUpazila->id,
            'designation' => 'Project Implementation Officer',
        ], $overrides);

        $headerB64 = rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '=');
        $payloadB64 = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $signingInput = "{$headerB64}.{$payloadB64}";

        openssl_sign($signingInput, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);
        $signatureB64 = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        return "{$signingInput}.{$signatureB64}";
    }

    public function test_unauthenticated_disaster_requests_rejected(): void
    {
        $this->getJson('/api/v1/disaster/dashboard')->assertStatus(401);
        $this->getJson('/api/v1/disaster/events')->assertStatus(401);
        $this->postJson('/api/v1/disaster/events', [])->assertStatus(401);
    }

    public function test_configurable_disaster_categories_and_authorization(): void
    {
        $pioToken = $this->createToken();
        $ministryToken = $this->createToken([
            'sub' => 'ministry-exec-01',
            'preferred_username' => 'ministry_exec',
            'email' => 'exec@modmr.gov.bd',
            'roles' => ['ROLE_MINISTRY_EXECUTIVE'],
            'tier_level' => 'NATIONAL',
            'ancestry_path' => '/',
        ]);

        // List seeded categories
        $this->withHeader('Authorization', "Bearer {$pioToken}")
            ->getJson('/api/v1/disaster/categories')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        // PIO cannot create system categories
        $this->withHeader('Authorization', "Bearer {$pioToken}")
            ->postJson('/api/v1/disaster/categories', [
                'code' => 'HEATWAVE',
                'name_en' => 'Heatwave',
                'name_bn' => 'তাপপ্রবাহ',
            ])
            ->assertStatus(403);

        // Ministry Executive can create configurable category
        $this->withHeader('Authorization', "Bearer {$ministryToken}")
            ->postJson('/api/v1/disaster/categories', [
                'code' => 'HEATWAVE',
                'name_en' => 'Extreme Heatwave',
                'name_bn' => 'তীব্র তাপপ্রবাহ',
                'subtypes' => [
                    ['code' => 'URBAN_HEAT', 'name_en' => 'Urban Heat Island', 'name_bn' => 'নগর তাপপ্রবাহ'],
                ],
            ])
            ->assertStatus(201)
            ->assertJsonPath('category.code', 'HEATWAVE');
    }

    public function test_register_disaster_with_locations_geojson_impact_and_duplicate_code_prevention(): void
    {
        $pioToken = $this->createToken();

        $payload = [
            'disaster_code' => 'CYC-DACOPE-2026',
            'disaster_type' => 'CYCLONE',
            'subtype' => 'SEVERE_CYCLONE',
            'name_en' => 'Dacope Coastal Squall 2026',
            'name_bn' => 'দাকোপ উপকূলীয় ঝড় ২০২৬',
            'description_en' => 'Localized severe cyclonic squall hitting Dacope unions.',
            'description_bn' => 'দাকোপ উপজেলার ইউনিয়নগুলোতে আঘাত হানা ঝড়।',
            'severity_level' => 'DANGER',
            'lifecycle_phase' => 'REPORTED',
            'start_date' => '2026-11-20',
            'epicenter_lat' => 22.5721,
            'epicenter_lng' => 89.5106,
            'affected_areas_summary' => 'Chalna & Banishanta Unions, Dacope',
            'affected_zone_geojson' => [
                'type' => 'Polygon',
                'coordinates' => [[[89.45, 22.50], [89.60, 22.50], [89.60, 22.65], [89.45, 22.65], [89.45, 22.50]]],
            ],
            'locations' => [
                ['administrative_unit_id' => $this->chalnaUnion->id],
                ['administrative_unit_id' => $this->dacopeUpazila->id],
            ],
            'impact' => [
                'affected_population' => 12500,
                'affected_households' => 2800,
                'deaths_count' => 1,
                'injured_count' => 18,
                'damaged_houses_fully' => 120,
                'damaged_houses_partially' => 450,
                'total_economic_loss_bdt' => 15000000,
            ],
        ];

        $res = $this->withHeader('Authorization', "Bearer {$pioToken}")
            ->postJson('/api/v1/disaster/events', $payload);

        $res->assertStatus(201)
            ->assertJsonPath('disaster.disaster_code', 'CYC-DACOPE-2026')
            ->assertJsonPath('disaster.lifecycle_phase', 'REPORTED')
            ->assertJsonPath('disaster.impact.affected_population', 12500);

        $this->assertCount(2, $res->json('disaster.locations'));
        $this->assertDatabaseHas('audit_logs', ['action_name' => 'DISASTER_EVENT_CREATED']);

        // Duplicate code rejected
        $this->withHeader('Authorization', "Bearer {$pioToken}")
            ->postJson('/api/v1/disaster/events', $payload)
            ->assertStatus(422);
    }

    public function test_organizational_scoping_and_lifecycle_verification_permissions(): void
    {
        $pioToken = $this->createToken();
        $drroKhulnaToken = $this->createToken([
            'sub' => 'drro-khulna-01',
            'preferred_username' => 'drro_khulna',
            'email' => 'drro.khulna@modmr.gov.bd',
            'roles' => ['ROLE_DRRO'],
            'tier_level' => 'DISTRICT',
            'ancestry_path' => '/40/47/',
            'scope_id' => $this->khulnaDistrict->id,
        ]);
        $drroBarishalToken = $this->createToken([
            'sub' => 'drro-barishal-01',
            'preferred_username' => 'drro_barishal',
            'email' => 'drro.barishal@modmr.gov.bd',
            'roles' => ['ROLE_DRRO'],
            'tier_level' => 'DISTRICT',
            'ancestry_path' => '/10/06/',
            'scope_id' => $this->barishalDistrict->id,
        ]);
        $enumToken = $this->createToken([
            'sub' => 'enum-01',
            'preferred_username' => 'enum_dacope',
            'email' => 'enum@modmr.gov.bd',
            'roles' => ['ROLE_FIELD_ENUMERATOR'],
        ]);

        // Field enumerator cannot register disaster
        $this->withHeader('Authorization', "Bearer {$enumToken}")
            ->postJson('/api/v1/disaster/events', [
                'disaster_type' => 'FLOOD',
                'name_en' => 'Unauthorized',
                'name_bn' => 'অননুমোদিত',
                'severity_level' => 'WARNING',
            ])
            ->assertStatus(403);

        // Dacope PIO cannot attach Barishal District or declare nationwide
        $this->withHeader('Authorization', "Bearer {$pioToken}")
            ->postJson('/api/v1/disaster/events', [
                'disaster_type' => 'FLOOD',
                'name_en' => 'Cross Jurisdiction Attempt',
                'name_bn' => 'সীমানা বহির্ভূত',
                'severity_level' => 'WARNING',
                'locations' => [['administrative_unit_id' => $this->barishalDistrict->id]],
            ])
            ->assertStatus(403);

        // Dacope PIO registers local disaster
        $createRes = $this->withHeader('Authorization', "Bearer {$pioToken}")
            ->postJson('/api/v1/disaster/events', [
                'disaster_code' => 'FL-DAC-001',
                'disaster_type' => 'FLOOD',
                'name_en' => 'Chalna Tidal Flood',
                'name_bn' => 'চালনা জোয়ারের বন্যা',
                'severity_level' => 'WARNING',
                'locations' => [['administrative_unit_id' => $this->chalnaUnion->id]],
            ])->assertStatus(201);

        $eventId = $createRes->json('disaster.id');

        // Barishal DRRO cannot view or transition Dacope-only disaster
        $this->withHeader('Authorization', "Bearer {$drroBarishalToken}")
            ->getJson("/api/v1/disaster/events/{$eventId}")
            ->assertStatus(403);

        // Dacope PIO can move REPORTED -> UNDER_ASSESSMENT
        $this->withHeader('Authorization', "Bearer {$pioToken}")
            ->postJson("/api/v1/disaster/events/{$eventId}/lifecycle", [
                'lifecycle_phase' => 'UNDER_ASSESSMENT',
                'notes' => 'Field assessment teams deployed.',
            ])
            ->assertStatus(200)
            ->assertJsonPath('disaster.lifecycle_phase', 'UNDER_ASSESSMENT');

        // Dacope PIO CANNOT verify/activate (requires District DRRO+ or Ministry)
        $this->withHeader('Authorization', "Bearer {$pioToken}")
            ->postJson("/api/v1/disaster/events/{$eventId}/lifecycle", [
                'lifecycle_phase' => 'VERIFIED',
            ])
            ->assertStatus(403);

        // Khulna DRRO CAN verify and activate
        $this->withHeader('Authorization', "Bearer {$drroKhulnaToken}")
            ->postJson("/api/v1/disaster/events/{$eventId}/lifecycle", [
                'lifecycle_phase' => 'VERIFIED',
                'notes' => 'Verified by Khulna DRRO.',
            ])
            ->assertStatus(200)
            ->assertJsonPath('disaster.lifecycle_phase', 'VERIFIED');
    }

    public function test_early_warning_timeline_attachment_gis_and_dashboard(): void
    {
        Storage::fake('local');
        Queue::fake();

        $drroToken = $this->createToken([
            'sub' => 'drro-khulna-02',
            'preferred_username' => 'drro_khulna_2',
            'email' => 'drro2@modmr.gov.bd',
            'roles' => ['ROLE_DRRO'],
            'tier_level' => 'DISTRICT',
            'ancestry_path' => '/40/47/',
            'scope_id' => $this->khulnaDistrict->id,
        ]);

        $createRes = $this->withHeader('Authorization', "Bearer {$drroToken}")
            ->postJson('/api/v1/disaster/events', [
                'disaster_code' => 'EROSION-KHULNA-2026',
                'disaster_type' => 'RIVER_EROSION',
                'name_en' => 'Rupsha Riverbank Erosion',
                'name_bn' => 'রূপসা নদী ভাঙন',
                'severity_level' => 'DANGER',
                'epicenter_lat' => 22.815,
                'epicenter_lng' => 89.568,
                'locations' => [['administrative_unit_id' => $this->khulnaDistrict->id]],
            ])->assertStatus(201);

        $eventId = $createRes->json('disaster.id');

        // Issue early warning
        $warnRes = $this->withHeader('Authorization', "Bearer {$drroToken}")
            ->postJson("/api/v1/disaster/events/{$eventId}/warnings", [
                'warning_level' => 'DANGER',
                'title_en' => 'Embankment Breach Alert',
                'title_bn' => 'বেড়িবাঁধ ভাঙন সতর্কতা',
                'message_en' => 'Residents near river bank advised to move to shelter.',
                'issuing_authority' => 'DDMC Khulna',
            ]);
        $warnRes->assertStatus(201);
        $warningId = $warnRes->json('warning.id');

        Queue::assertPushed(DispatchDisasterWarningJob::class);
        app()->call([new DispatchDisasterWarningJob($warningId), 'handle']);
        $this->assertDatabaseHas('disaster_warnings', [
            'id' => $warningId,
            'notification_dispatch_status' => 'DISPATCHED',
        ]);

        // Add timeline entry with PDF attachment
        $file = UploadedFile::fake()->create('sitrep.pdf', 120, 'application/pdf');
        $tlRes = $this->withHeader('Authorization', "Bearer {$drroToken}")
            ->post("/api/v1/disaster/events/{$eventId}/timelines", [
                'title_en' => 'Initial Situation Report Uploaded',
                'notes' => 'Attached district sitrep PDF.',
                'attachment' => $file,
            ]);
        $tlRes->assertStatus(201)
            ->assertJsonPath('timeline.has_attachment', true);

        $timelineId = $tlRes->json('timeline.id');

        // Download attachment
        $this->withHeader('Authorization', "Bearer {$drroToken}")
            ->get("/api/v1/disaster/events/{$eventId}/timelines/{$timelineId}/attachment")
            ->assertStatus(200);

        // GIS GeoJSON & spatial bbox filter + attribute filters
        $gisRes = $this->withHeader('Authorization', "Bearer {$drroToken}")
            ->getJson('/api/v1/disaster/gis/geojson?bbox=89.0,22.0,90.0,23.2&disaster_type=RIVER_EROSION');
        $gisRes->assertStatus(200)
            ->assertJsonPath('geojson.type', 'FeatureCollection')
            ->assertJsonPath('count', 1);

        // Upload Shapefile / GeoJSON FeatureCollection boundary (normalized to Polygon/MultiPolygon)
        $shapefileZip = UploadedFile::fake()->create('rupsha_erosion.zip', 64, 'application/zip');
        $featureCollectionJson = json_encode([
            'type' => 'FeatureCollection',
            'features' => [
                [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Polygon',
                        'coordinates' => [[[89.52, 22.78], [89.62, 22.78], [89.62, 22.85], [89.52, 22.85], [89.52, 22.78]]],
                    ],
                    'properties' => [],
                ],
            ],
        ]);

        $boundaryRes = $this->withHeader('Authorization', "Bearer {$drroToken}")
            ->post("/api/v1/disaster/events/{$eventId}/boundary", [
                'affected_zone_geojson' => $featureCollectionJson,
                'source_filename' => 'rupsha_erosion.zip',
                'shapefile_archive' => $shapefileZip,
                'notes' => 'Uploaded from district GIS shapefile.',
            ]);

        $boundaryRes->assertStatus(200)
            ->assertJsonPath('disaster.affected_zone_geojson.type', 'Polygon');

        $this->assertDatabaseHas('audit_logs', ['action_name' => 'DISASTER_BOUNDARY_UPDATED']);

        // Dashboard metrics
        $this->withHeader('Authorization', "Bearer {$drroToken}")
            ->getJson('/api/v1/disaster/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['summary', 'by_category', 'by_severity', 'by_lifecycle', 'geo_distribution', 'trends'],
            ]);
    }
}
