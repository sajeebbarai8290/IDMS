<?php

namespace Tests\Feature\Api\V1;

use App\Core\Models\AdministrativeUnit;
use App\Modules\Disaster\Models\DisasterEvent;
use App\Modules\Resource\Models\ResourceAsset;
use App\Modules\Resource\Models\ResourceCategory;
use App\Modules\Response\Models\ResponseOperation;
use Database\Seeders\AdministrativeUnitSeeder;
use Database\Seeders\DisasterEventSeeder;
use Database\Seeders\ResourceSeeder;
use Database\Seeders\ResponseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResourceModuleTest extends TestCase
{
    use RefreshDatabase;

    protected string $privateKey;
    protected string $cleanX5cCert;
    protected string $kid = 'idms-resource-test-key-2026';
    protected string $realm = 'idms-gov-realm';
    protected string $issuer = 'http://localhost:8080/realms/idms-gov-realm';
    protected string $audience = 'idms-backend-api';

    protected AdministrativeUnit $dacopeUpazila;
    protected AdministrativeUnit $khulnaDistrict;
    protected AdministrativeUnit $barishalDistrict;
    protected DisasterEvent $disaster;
    protected ResourceCategory $boatsCategory;
    protected ResourceCategory $hrCategory;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->seed(AdministrativeUnitSeeder::class);
        $this->seed(DisasterEventSeeder::class);
        $this->seed(ResponseCatalogSeeder::class);
        $this->seed(ResourceSeeder::class);

        $this->dacopeUpazila = AdministrativeUnit::where('code', '12')->firstOrFail();
        $this->khulnaDistrict = AdministrativeUnit::where('code', '47')->firstOrFail();
        $this->barishalDistrict = AdministrativeUnit::where('code', '10')->firstOrFail();
        $this->disaster = DisasterEvent::where('disaster_code', 'CYC-REMAL-2024')->firstOrFail();
        $this->boatsCategory = ResourceCategory::where('code', 'BOATS')->firstOrFail();
        $this->hrCategory = ResourceCategory::where('code', 'HUMAN_RESOURCES')->firstOrFail();

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
            'sub' => 'e1111111-1111-4111-8111-111111111111',
            'preferred_username' => 'drro_khulna',
            'name' => 'Md. Rafiqul Islam (DRRO)',
            'email' => 'drro.khulna@modmr.gov.bd',
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'exp' => $now + 3600,
            'nbf' => $now - 60,
            'iat' => $now,
            'roles' => ['ROLE_DRRO'],
            'tier_level' => 'DISTRICT',
            'ancestry_path' => '/40/47/',
            'scope_id' => $this->khulnaDistrict->id,
        ], $overrides);

        $headerB64 = rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '=');
        $payloadB64 = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $signingInput = "{$headerB64}.{$payloadB64}";

        openssl_sign($signingInput, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);
        $signatureB64 = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        return "{$signingInput}.{$signatureB64}";
    }

    public function test_unauthenticated_resource_routes_return_401(): void
    {
        $this->getJson('/api/v1/resource/dashboard')->assertStatus(401);
        $this->getJson('/api/v1/resource/assets')->assertStatus(401);
        $this->getJson('/api/v1/resource/allocations')->assertStatus(401);
    }

    public function test_super_admin_can_configure_categories_and_register_assets_but_cannot_deploy(): void
    {
        $adminToken = $this->createToken([
            'sub' => 'a0000007-0000-4000-8000-000000000001',
            'preferred_username' => 'super_admin',
            'email' => 'super.admin@modmr.gov.bd',
            'roles' => ['ROLE_SUPER_ADMIN'],
            'tier_level' => 'NATIONAL',
            'ancestry_path' => '/',
        ]);

        $this->withToken($adminToken)->getJson('/api/v1/resource/dashboard')->assertStatus(200);

        $catRes = $this->withToken($adminToken)->postJson('/api/v1/resource/categories', [
            'code' => 'DRONE_SURVEILLANCE',
            'name_en' => 'Aerial & Drone Surveillance',
            'name_bn' => 'ড্রোন ও আকাশ পর্যবেক্ষণ',
            'color_hex' => '#0dcaf0',
        ]);
        $catRes->assertStatus(201)->assertJsonPath('data.code', 'DRONE_SURVEILLANCE');

        $boat = ResourceAsset::where('asset_code', 'AST-BOAT-KHL-01')->firstOrFail();

        // Operational blackout: Super Admin cannot allocate resources
        $this->withToken($adminToken)->postJson('/api/v1/resource/allocations', [
            'resource_asset_id' => $boat->id,
            'disaster_event_id' => $this->disaster->id,
            'allocation_location' => 'Dacope Ghat',
            'assignment_date' => now()->toDateString(),
            'responsible_officer_name' => 'Test Officer',
        ])->assertStatus(403);
    }

    public function test_resource_registration_duplicate_code_attachments_scoping_and_partner_privacy(): void
    {
        $pioToken = $this->createToken([
            'sub' => '22222222-2222-4222-8222-222222222201',
            'preferred_username' => 'pio_dacope',
            'email' => 'pio.dacope@modmr.gov.bd',
            'roles' => ['ROLE_PIO'],
            'tier_level' => 'UPAZILA',
            'ancestry_path' => '/40/47/12/',
            'scope_id' => $this->dacopeUpazila->id,
        ]);

        $barishalDrroToken = $this->createToken([
            'sub' => '44444444-4444-4444-8444-444444444401',
            'preferred_username' => 'drro_barishal',
            'email' => 'drro.barishal@modmr.gov.bd',
            'roles' => ['ROLE_DRRO'],
            'tier_level' => 'DISTRICT',
            'ancestry_path' => '/10/',
            'scope_id' => $this->barishalDistrict->id,
        ]);

        $partnerToken = $this->createToken([
            'sub' => '55555555-5555-4555-8555-555555555501',
            'preferred_username' => 'ext_partner',
            'email' => 'partner@ngo.org',
            'roles' => ['ROLE_EXTERNAL_PARTNER'],
            'tier_level' => 'NATIONAL',
            'ancestry_path' => '/',
        ]);

        // Cross-scope registration denied
        $this->withToken($barishalDrroToken)->postJson('/api/v1/resource/assets', [
            'category_id' => $this->boatsCategory->id,
            'administrative_unit_id' => $this->dacopeUpazila->id,
            'asset_code' => 'RES-BOAT-CROSS-01',
            'resource_type' => 'SPEEDBOAT',
            'name_en' => 'Unauthorized Cross Scope Boat',
            'name_bn' => 'অননুমোদিত বোট',
            'owning_organization' => 'Barishal DMC',
            'current_location' => 'Dacope',
            'responsible_officer_name' => 'Officer X',
        ])->assertStatus(403);

        // Register new resource in Dacope
        $createRes = $this->withToken($pioToken)->postJson('/api/v1/resource/assets', [
            'category_id' => $this->boatsCategory->id,
            'administrative_unit_id' => $this->dacopeUpazila->id,
            'asset_code' => 'RES-BOAT-DAC-99',
            'resource_type' => 'INFLATABLE_RESCUE_RAFT',
            'name_en' => 'Dacope Inflatable Rescue Boat #99',
            'name_bn' => 'দাকোপ ইনফ্লেটেবল উদ্ধার বোট #৯৯',
            'description' => '10-person inflatable rescue boat with 40HP outboard motor.',
            'registration_no' => 'DAC-BOAT-99',
            'capacity_info' => '10 passengers',
            'specifications' => ['engine_hp' => 40, 'hull' => 'Hypalon'],
            'owning_organization' => 'Dacope Upazila DMC',
            'current_location' => 'Chalna Riverine Jetty',
            'responsible_officer_name' => 'PIO Dacope',
            'responsible_officer_contact' => '+8801700-998877',
            'acquisition_date' => '2026-01-15',
            'acquisition_value_bdt' => 950000,
            'latitude' => 22.573456,
            'longitude' => 89.512345,
        ]);
        $createRes->assertStatus(201)->assertJsonPath('data.status', 'AVAILABLE');
        $assetId = $createRes->json('data.id');

        // Duplicate asset code blocked (422)
        $this->withToken($pioToken)->postJson('/api/v1/resource/assets', [
            'category_id' => $this->boatsCategory->id,
            'administrative_unit_id' => $this->dacopeUpazila->id,
            'asset_code' => 'RES-BOAT-DAC-99',
            'resource_type' => 'INFLATABLE_RESCUE_RAFT',
            'name_en' => 'Duplicate Boat',
            'name_bn' => 'ডুপ্লিকেট বোট',
            'owning_organization' => 'Dacope DMC',
            'current_location' => 'Chalna',
            'responsible_officer_name' => 'PIO',
        ])->assertStatus(422);

        // Upload photograph & supporting document
        $photoRes = $this->withToken($pioToken)->post("/api/v1/resource/assets/{$assetId}/attachments", [
            'attachment_kind' => 'PHOTOGRAPH',
            'title' => 'Jetty Inspection Photo',
            'file' => UploadedFile::fake()->image('boat99.jpg', 640, 480),
        ]);
        $photoRes->assertStatus(201);
        $attId = $photoRes->json('data.id');
        $this->assertNotEmpty($photoRes->json('data.sha256_hash'));

        // Barishal DRRO cannot view Dacope asset (403)
        $this->withToken($barishalDrroToken)->getJson("/api/v1/resource/assets/{$assetId}")->assertStatus(403);

        // External partner sees masked coords and redacted officer contact, cannot download attachments
        $partnerShow = $this->withToken($partnerToken)->getJson("/api/v1/resource/assets/{$assetId}");
        $partnerShow->assertStatus(200)
            ->assertJsonPath('data.responsible_officer_contact', null)
            ->assertJsonPath('data.latitude', 22.57);

        $this->withToken($partnerToken)
            ->get("/api/v1/resource/assets/{$assetId}/attachments/{$attId}")
            ->assertStatus(403);

        // Authorized officer can download attachment
        $this->withToken($pioToken)
            ->get("/api/v1/resource/assets/{$assetId}/attachments/{$attId}")
            ->assertStatus(200);
    }

    public function test_transaction_safe_allocation_prevents_unavailable_assignment_and_releases_cleanly(): void
    {
        $drroToken = $this->createToken();
        $boat = ResourceAsset::where('asset_code', 'AST-BOAT-KHL-01')->firstOrFail();

        // Create a linked ResponseOperation
        $opRes = $this->withToken($drroToken)->postJson('/api/v1/response/operations', [
            'disaster_event_id' => $this->disaster->id,
            'administrative_unit_id' => $this->dacopeUpazila->id,
            'title_en' => 'Coastal Evacuation Support',
            'priority' => 'HIGH',
            'severity_level' => 'DANGER',
            'start_date' => now()->toDateString(),
            'lead_organization' => 'DDM Khulna',
        ]);
        $opRes->assertStatus(201);
        $opId = $opRes->json('data.id');

        // 1. Allocate AVAILABLE resource -> asset becomes ASSIGNED
        $allocRes = $this->withToken($drroToken)->postJson('/api/v1/resource/allocations', [
            'resource_asset_id' => $boat->id,
            'disaster_event_id' => $this->disaster->id,
            'response_operation_id' => $opId,
            'administrative_unit_id' => $this->dacopeUpazila->id,
            'allocation_location' => 'Nalian Coastal Sector, Dacope',
            'assignment_date' => now()->toDateString(),
            'expected_return_date' => now()->addDays(5)->toDateString(),
            'responsible_officer_name' => 'Commander Tariq',
            'responsible_officer_contact' => '+8801711-888999',
            'purpose' => 'Emergency flood evacuation',
        ]);
        $allocRes->assertStatus(201)->assertJsonPath('data.status', 'ASSIGNED');
        $allocId = $allocRes->json('data.id');

        $this->assertSame('ASSIGNED', $boat->fresh()->status);

        // 2. Attempting to allocate already ASSIGNED resource fails with 422
        $this->withToken($drroToken)->postJson('/api/v1/resource/allocations', [
            'resource_asset_id' => $boat->id,
            'disaster_event_id' => $this->disaster->id,
            'allocation_location' => 'Another Location',
            'assignment_date' => now()->toDateString(),
            'responsible_officer_name' => 'Another Officer',
        ])->assertStatus(422);

        // 3. Mark IN_USE -> asset becomes IN_USE
        $this->withToken($drroToken)
            ->postJson("/api/v1/resource/allocations/{$allocId}/in-use", ['utilization_notes' => 'Sortie underway'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'IN_USE');
        $this->assertSame('IN_USE', $boat->fresh()->status);

        // 4. Cannot start maintenance while IN_USE
        $this->withToken($drroToken)
            ->postJson("/api/v1/resource/assets/{$boat->id}/maintenances", [
                'record_type' => 'REPAIR',
                'title' => 'Engine Check',
                'status' => 'IN_PROGRESS',
            ])
            ->assertStatus(422);

        // 5. Release resource in GOOD condition -> asset becomes AVAILABLE
        $this->withToken($drroToken)
            ->postJson("/api/v1/resource/allocations/{$allocId}/release", [
                'return_condition' => 'GOOD',
                'return_condition_notes' => 'All equipment intact.',
                'return_location' => 'Khulna District EOC Boat Yard',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'RETURNED');
        $this->assertSame('AVAILABLE', $boat->fresh()->status);

        // 6. Re-allocate and return DAMAGED -> asset becomes DAMAGED and cannot be re-allocated
        $alloc2 = $this->withToken($drroToken)->postJson('/api/v1/resource/allocations', [
            'resource_asset_id' => $boat->id,
            'disaster_event_id' => $this->disaster->id,
            'allocation_location' => 'Pankhali Ferry Ghat',
            'assignment_date' => now()->toDateString(),
            'responsible_officer_name' => 'Commander Tariq',
        ])->assertStatus(201)->json('data.id');

        $this->withToken($drroToken)
            ->postJson("/api/v1/resource/allocations/{$alloc2}/release", [
                'return_condition' => 'DAMAGED',
                'return_condition_notes' => 'Propeller struck submerged debris.',
            ])
            ->assertStatus(200);
        $this->assertSame('DAMAGED', $boat->fresh()->status);

        $this->withToken($drroToken)->postJson('/api/v1/resource/allocations', [
            'resource_asset_id' => $boat->id,
            'disaster_event_id' => $this->disaster->id,
            'allocation_location' => 'Should Fail',
            'assignment_date' => now()->toDateString(),
            'responsible_officer_name' => 'Officer Y',
        ])->assertStatus(422);
    }

    public function test_maintenance_lifecycle_reminders_and_human_resource_deployments_and_dashboard(): void
    {
        $drroToken = $this->createToken();
        $pump = ResourceAsset::where('asset_code', 'RES-HVM-DAC-01')->firstOrFail();
        $hrSpecialist = ResourceAsset::where('asset_code', 'RES-HR-KHL-01')->firstOrFail();

        // 1. Start maintenance with inspection attachment -> asset UNDER_MAINTENANCE
        $mntRes = $this->withToken($drroToken)->post("/api/v1/resource/assets/{$pump->id}/maintenances", [
            'record_type' => 'REPAIR',
            'title' => 'Overhaul Cooling System & Gasket',
            'scheduled_date' => now()->toDateString(),
            'next_due_date' => now()->addDays(10)->toDateString(),
            'reminder_days_before' => 7,
            'repair_cost_bdt' => 28500,
            'vendor_or_workshop' => 'Khulna Marine Dockyard',
            'status' => 'IN_PROGRESS',
            'performed_by_name' => 'Engr. Salim',
            'attachment' => UploadedFile::fake()->create('inspection.pdf', 120, 'application/pdf'),
        ]);
        $mntRes->assertStatus(201)->assertJsonPath('data.status', 'IN_PROGRESS');
        $mntId = $mntRes->json('data.id');
        $this->assertSame('UNDER_MAINTENANCE', $pump->fresh()->status);

        // Download maintenance attachment
        $this->withToken($drroToken)->get("/api/v1/resource/maintenances/{$mntId}/attachment")->assertStatus(200);

        // Complete maintenance -> asset AVAILABLE
        $this->withToken($drroToken)->putJson("/api/v1/resource/maintenances/{$mntId}", [
            'status' => 'COMPLETED',
            'inspection_result' => 'PASS',
            'inspection_findings' => 'Load test passed at 100% capacity.',
            'repair_cost_bdt' => 31200,
        ])->assertStatus(200)->assertJsonPath('data.status', 'COMPLETED');
        $this->assertSame('AVAILABLE', $pump->fresh()->status);

        // Reminders include overdue AST-WTP-DAC-01 and upcoming RES-HVM-DAC-01
        $remRes = $this->withToken($drroToken)->getJson('/api/v1/resource/reminders?days=30');
        $remRes->assertStatus(200);
        $this->assertNotEmpty($remRes->json('data.due_assets'));

        // 2. Human resource deployment linked to response operation
        $op = ResponseOperation::create([
            'disaster_event_id' => $this->disaster->id,
            'administrative_unit_id' => $this->dacopeUpazila->id,
            'operation_no' => 'OP-RES-TEST-01',
            'title_en' => 'Flood Mitigation Op',
            'priority' => 'HIGH',
            'severity_level' => 'DANGER',
            'status' => ResponseOperation::STATUS_ACTIVATED,
            'start_date' => now()->toDateString(),
            'lead_organization' => 'DDM',
            'created_by' => \App\Core\Models\User::first()->id,
        ]);

        $hrRes = $this->withToken($drroToken)->postJson('/api/v1/resource/human-deployments', [
            'resource_asset_id' => $hrSpecialist->id,
            'response_operation_id' => $op->id,
            'personnel_name' => 'Engr. Mahbubur Rahman',
            'personnel_designation' => 'Swift-Water Rescue Specialist',
            'personnel_contact' => '+8801711-000203',
            'assigned_duty' => 'Operate coastal rescue boats & coordinate water safety.',
            'deployment_location' => 'Dacope Command Post',
            'deployment_start_date' => now()->toDateString(),
            'deployment_end_date' => now()->addDays(7)->toDateString(),
        ]);
        $hrRes->assertStatus(201)->assertJsonPath('data.availability_status', 'ASSIGNED');
        $hrDepId = $hrRes->json('data.id');
        $this->assertSame('ASSIGNED', $hrSpecialist->fresh()->status);

        // Double assignment of same HR specialist blocked (422)
        $this->withToken($drroToken)->postJson('/api/v1/resource/human-deployments', [
            'resource_asset_id' => $hrSpecialist->id,
            'response_operation_id' => $op->id,
            'personnel_name' => 'Engr. Mahbubur Rahman',
            'assigned_duty' => 'Conflicting duty',
            'deployment_start_date' => now()->toDateString(),
        ])->assertStatus(422);

        // Release HR specialist -> AVAILABLE
        $this->withToken($drroToken)->postJson("/api/v1/resource/human-deployments/{$hrDepId}/release", [
            'notes' => 'Duty completed successfully.',
        ])->assertStatus(200)->assertJsonPath('data.availability_status', 'COMPLETED');
        $this->assertSame('AVAILABLE', $hrSpecialist->fresh()->status);

        // 3. Dashboard, GeoJSON & CSV Export
        $dashRes = $this->withToken($drroToken)->getJson('/api/v1/resource/dashboard');
        $dashRes->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'summary' => [
                        'total_resources',
                        'available_resources',
                        'assigned_resources',
                        'under_maintenance_resources',
                    ],
                    'by_category',
                    'utilization_by_disaster',
                    'geographical_distribution',
                    'maintenance_reminders',
                ],
            ]);

        $this->withToken($drroToken)->getJson('/api/v1/resource/gis/geojson')
            ->assertStatus(200)
            ->assertJsonPath('data.type', 'FeatureCollection');

        $this->withToken($drroToken)->get('/api/v1/resource/export')
            ->assertStatus(200)
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_asset_crud_filters_list_endpoints_validation_and_super_admin_retire_blackout(): void
    {
        $drroToken = $this->createToken();
        $pioToken = $this->createToken([
            'sub' => '22222222-2222-4222-8222-222222222299',
            'preferred_username' => 'pio_dacope_crud',
            'email' => 'pio.crud@modmr.gov.bd',
            'roles' => ['ROLE_PIO'],
            'tier_level' => 'UPAZILA',
            'ancestry_path' => '/40/47/12/',
            'scope_id' => $this->dacopeUpazila->id,
        ]);
        $barishalDrroToken = $this->createToken([
            'sub' => '44444444-4444-4444-8444-444444444499',
            'preferred_username' => 'drro_barishal_crud',
            'email' => 'drro.bar.crud@modmr.gov.bd',
            'roles' => ['ROLE_DRRO'],
            'tier_level' => 'DISTRICT',
            'ancestry_path' => '/10/',
            'scope_id' => $this->barishalDistrict->id,
        ]);
        $adminToken = $this->createToken([
            'sub' => 'a0000007-0000-4000-8000-000000000099',
            'preferred_username' => 'super_admin_crud',
            'email' => 'super.admin.crud@modmr.gov.bd',
            'roles' => ['ROLE_SUPER_ADMIN'],
            'tier_level' => 'NATIONAL',
            'ancestry_path' => '/',
        ]);
        $partnerToken = $this->createToken([
            'sub' => '55555555-5555-4555-8555-555555555599',
            'preferred_username' => 'ext_partner_crud',
            'email' => 'partner.crud@ngo.org',
            'roles' => ['ROLE_EXTERNAL_PARTNER'],
            'tier_level' => 'NATIONAL',
            'ancestry_path' => '/',
        ]);

        // Lookups personnel scoping & external partner redaction
        $this->withToken($partnerToken)->getJson('/api/v1/resource/lookups')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data.personnel_users');
        $this->withToken($pioToken)->getJson('/api/v1/resource/lookups')->assertStatus(200);

        // Register asset, then update it
        $assetId = $this->withToken($pioToken)->postJson('/api/v1/resource/assets', [
            'category_id' => $this->boatsCategory->id,
            'administrative_unit_id' => $this->dacopeUpazila->id,
            'asset_code' => 'RES-CRUD-01',
            'resource_type' => 'PATROL_BOAT',
            'name_en' => 'Dacope Patrol Boat',
            'name_bn' => 'দাকোপ টহল বোট',
            'owning_organization' => 'Dacope DMC',
            'current_location' => 'Chalna',
            'responsible_officer_name' => 'PIO Dacope',
        ])->assertStatus(201)->json('data.id');

        $this->withToken($pioToken)->putJson("/api/v1/resource/assets/{$assetId}", [
            'current_location' => 'Sutarkhali Jetty',
            'capacity_info' => '12 passengers',
        ])->assertStatus(200)->assertJsonPath('data.current_location', 'Sutarkhali Jetty');

        // Cross-scope update blocked
        $this->withToken($barishalDrroToken)->putJson("/api/v1/resource/assets/{$assetId}", [
            'current_location' => 'Tampered',
        ])->assertStatus(403);

        // Validation: expected_return_date before assignment_date fails 422
        $this->withToken($drroToken)->postJson('/api/v1/resource/allocations', [
            'resource_asset_id' => $assetId,
            'disaster_event_id' => $this->disaster->id,
            'allocation_location' => 'Sutarkhali',
            'assignment_date' => '2026-11-26',
            'expected_return_date' => '2026-11-20',
            'responsible_officer_name' => 'DRRO',
        ])->assertStatus(422);

        // Allocate asset -> cannot manually set status AVAILABLE or delete while ASSIGNED
        $allocId = $this->withToken($drroToken)->postJson('/api/v1/resource/allocations', [
            'resource_asset_id' => $assetId,
            'disaster_event_id' => $this->disaster->id,
            'allocation_location' => 'Sutarkhali',
            'assignment_date' => '2026-11-26',
            'expected_return_date' => '2026-11-30',
            'responsible_officer_name' => 'DRRO',
        ])->assertStatus(201)->json('data.id');

        $this->withToken($pioToken)->putJson("/api/v1/resource/assets/{$assetId}", [
            'status' => 'AVAILABLE',
        ])->assertStatus(422);

        $this->withToken($pioToken)->deleteJson("/api/v1/resource/assets/{$assetId}")->assertStatus(422);

        // Release, then verify Super Admin cannot delete, while PIO can retire/soft-delete
        $this->withToken($drroToken)->postJson("/api/v1/resource/allocations/{$allocId}/release", [
            'return_condition' => 'GOOD',
        ])->assertStatus(200);

        $this->withToken($adminToken)->deleteJson("/api/v1/resource/assets/{$assetId}")->assertStatus(403);
        $this->withToken($pioToken)->deleteJson("/api/v1/resource/assets/{$assetId}")->assertStatus(200);

        // List, filter, search, and pagination endpoints
        $this->withToken($drroToken)->getJson('/api/v1/resource/assets?search=AST-BOAT&sort_by=asset_code&sort_dir=asc')
            ->assertStatus(200)
            ->assertJsonPath('data.0.asset_code', 'AST-BOAT-KHL-01');
        $this->withToken($drroToken)->getJson('/api/v1/resource/allocations?status=RETURNED')->assertStatus(200);
        $this->withToken($drroToken)->getJson('/api/v1/resource/maintenances?status=COMPLETED')->assertStatus(200);
        $this->withToken($drroToken)->getJson('/api/v1/resource/human-deployments')->assertStatus(200);
    }
}
