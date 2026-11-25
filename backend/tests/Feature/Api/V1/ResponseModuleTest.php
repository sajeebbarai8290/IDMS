<?php

namespace Tests\Feature\Api\V1;

use App\Core\Models\AdministrativeUnit;
use App\Modules\Disaster\Models\DisasterEvent;
use App\Modules\Response\Models\ResourceAsset;
use App\Modules\Response\Models\ResponseActivityType;
use App\Modules\Response\Models\ResponseCenter;
use App\Modules\Response\Models\ResponseOperation;
use App\Modules\Response\Models\ResponseTask;
use Database\Seeders\AdministrativeUnitSeeder;
use Database\Seeders\DisasterEventSeeder;
use Database\Seeders\ResponseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResponseModuleTest extends TestCase
{
    use RefreshDatabase;

    protected string $privateKey;
    protected string $cleanX5cCert;
    protected string $kid = 'idms-response-test-key-2026';
    protected string $realm = 'idms-gov-realm';
    protected string $issuer = 'http://localhost:8080/realms/idms-gov-realm';
    protected string $audience = 'idms-backend-api';

    protected AdministrativeUnit $dacopeUpazila;
    protected AdministrativeUnit $khulnaDistrict;
    protected AdministrativeUnit $barishalDistrict;
    protected DisasterEvent $disaster;
    protected ResponseCenter $dacopeCenter;
    protected ResponseActivityType $sarActivity;
    protected ResourceAsset $boatAsset;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->seed(AdministrativeUnitSeeder::class);
        $this->seed(DisasterEventSeeder::class);
        $this->seed(ResponseCatalogSeeder::class);

        $this->dacopeUpazila = AdministrativeUnit::where('code', '12')->firstOrFail();
        $this->khulnaDistrict = AdministrativeUnit::where('code', '47')->firstOrFail();
        $this->barishalDistrict = AdministrativeUnit::where('code', '10')->firstOrFail();
        $this->disaster = DisasterEvent::where('disaster_code', 'CYC-REMAL-2024')->firstOrFail();
        $this->dacopeCenter = ResponseCenter::where('center_code', 'EOC-DAC-01')->firstOrFail();
        $this->sarActivity = ResponseActivityType::where('code', 'SEARCH_RESCUE')->firstOrFail();
        $this->boatAsset = ResourceAsset::where('asset_code', 'AST-BOAT-KHL-01')->firstOrFail();

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

    public function test_unauthenticated_response_routes_return_401(): void
    {
        $this->getJson('/api/v1/response/dashboard')->assertStatus(401);
        $this->getJson('/api/v1/response/operations')->assertStatus(401);
    }

    public function test_super_admin_blackout_allows_activities_and_view_but_blocks_operations(): void
    {
        $adminToken = $this->createToken([
            'sub' => 'a0000001-0000-4000-8000-000000000001',
            'preferred_username' => 'super_admin',
            'email' => 'super.admin@modmr.gov.bd',
            'roles' => ['ROLE_SUPER_ADMIN'],
            'tier_level' => 'NATIONAL',
            'ancestry_path' => '/',
        ]);

        $this->withToken($adminToken)->getJson('/api/v1/response/dashboard')->assertStatus(200);

        $this->withToken($adminToken)->postJson('/api/v1/response/activities', [
            'code' => 'ANIMAL_RESCUE',
            'name_en' => 'Livestock Rescue & Care',
            'name_bn' => 'গবাদিপশু উদ্ধার ও চিকিৎসা',
        ])->assertStatus(201)->assertJsonPath('data.code', 'ANIMAL_RESCUE');

        // Blackout blocks operation creation
        $this->withToken($adminToken)->postJson('/api/v1/response/operations', [
            'disaster_event_id' => $this->disaster->id,
            'administrative_unit_id' => $this->dacopeUpazila->id,
            'title_en' => 'Emergency Rescue',
            'priority' => 'HIGH',
            'severity_level' => 'DANGER',
            'start_date' => now()->toDateString(),
            'lead_organization' => 'DDM',
        ])->assertStatus(403);
    }

    public function test_full_response_lifecycle_coordination_tasks_resources_sitrep_and_gis_privacy(): void
    {
        $pioToken = $this->createToken([
            'sub' => '22222222-2222-4222-8222-222222222222',
            'preferred_username' => 'pio_dacope',
            'email' => 'pio.dacope@modmr.gov.bd',
            'roles' => ['ROLE_PIO'],
            'tier_level' => 'UPAZILA',
            'ancestry_path' => '/40/47/12/',
            'scope_id' => $this->dacopeUpazila->id,
        ]);

        $drroToken = $this->createToken([
            'sub' => '33333333-3333-4333-8333-333333333333',
            'preferred_username' => 'drro_khulna',
            'email' => 'drro.khulna@modmr.gov.bd',
            'roles' => ['ROLE_DRRO'],
            'tier_level' => 'DISTRICT',
            'ancestry_path' => '/40/47/',
            'scope_id' => $this->khulnaDistrict->id,
        ]);

        $barishalDrroToken = $this->createToken([
            'sub' => '44444444-4444-4444-8444-444444444444',
            'preferred_username' => 'drro_barishal',
            'email' => 'drro.barishal@modmr.gov.bd',
            'roles' => ['ROLE_DRRO'],
            'tier_level' => 'DISTRICT',
            'ancestry_path' => '/10/',
            'scope_id' => $this->barishalDistrict->id,
        ]);

        $partnerToken = $this->createToken([
            'sub' => '55555555-5555-4555-8555-555555555555',
            'preferred_username' => 'ext_partner',
            'email' => 'partner@ngo.org',
            'roles' => ['ROLE_EXTERNAL_PARTNER'],
            'tier_level' => 'NATIONAL',
            'ancestry_path' => '/',
        ]);

        // 1. Cross-scope creation denied (Barishal cannot create operation in Khulna)
        $this->withToken($barishalDrroToken)->postJson('/api/v1/response/operations', [
            'disaster_event_id' => $this->disaster->id,
            'administrative_unit_id' => $this->dacopeUpazila->id,
            'title_en' => 'Cross Scope Op',
            'priority' => 'HIGH',
            'severity_level' => 'DANGER',
            'start_date' => now()->toDateString(),
            'lead_organization' => 'DDM',
        ])->assertStatus(403);

        // 2. PIO creates planned operation
        $opRes = $this->withToken($pioToken)->postJson('/api/v1/response/operations', [
            'disaster_event_id' => $this->disaster->id,
            'administrative_unit_id' => $this->dacopeUpazila->id,
            'response_center_id' => $this->dacopeCenter->id,
            'title_en' => 'Cyclone Remal Dacope SAR & Evacuation',
            'title_bn' => 'ঘূর্ণিঝড় রেমাল দাকোপ উদ্ধার অভিযান',
            'objectives' => 'Evacuate coastal wards and restore arterial access.',
            'priority' => 'CRITICAL',
            'severity_level' => 'GREAT_DANGER',
            'start_date' => now()->toDateString(),
            'lead_organization' => 'Upazila DMC Dacope',
            'commander_name' => 'UNO Dacope',
        ]);
        $opRes->assertStatus(201)->assertJsonPath('data.status', ResponseOperation::STATUS_PLANNED);
        $opId = $opRes->json('data.id');

        // 3. Lifecycle gating: PIO cannot ACTIVATE operation -> 403; DRRO activates & marks ONGOING -> 200
        $this->withToken($pioToken)
            ->postJson("/api/v1/response/operations/{$opId}/lifecycle", ['status' => 'ACTIVATED'])
            ->assertStatus(403);

        $this->withToken($drroToken)
            ->postJson("/api/v1/response/operations/{$opId}/lifecycle", [
                'status' => 'ACTIVATED',
                'notes' => 'District Emergency Control Room activated SAR plan.',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', ResponseOperation::STATUS_ACTIVATED);

        // 4. Coordinate Agencies & Deploy SAR Team
        $this->withToken($pioToken)->postJson("/api/v1/response/operations/{$opId}/agencies", [
            'agency_name' => 'Bangladesh Fire Service & Civil Defence',
            'agency_type' => 'FIRE_SERVICE',
            'role_summary' => 'Coastal boat search & rescue',
        ])->assertStatus(201);

        $teamRes = $this->withToken($pioToken)->postJson("/api/v1/response/operations/{$opId}/teams", [
            'team_name' => 'Dacope Riverine SAR Bravo',
            'agency_name' => 'Fire Service',
            'team_type' => 'FIRE_SERVICE',
            'leader_name' => 'Station Officer Kamal',
            'member_count' => 12,
            'latitude' => 22.573456,
            'longitude' => 89.514321,
        ]);
        $teamRes->assertStatus(201);
        $teamId = $teamRes->json('data.id');

        // 5. Link & deploy ResourceAsset (boat switches AVAILABLE -> DEPLOYED, double deploy blocked 422, return -> AVAILABLE)
        $resAlloc = $this->withToken($drroToken)->postJson("/api/v1/response/operations/{$opId}/resources", [
            'resource_asset_id' => $this->boatAsset->id,
            'requirement_title' => 'Fiberglass Speedboat for Riverine Rescue',
            'quantity_needed' => 1,
            'priority' => 'CRITICAL',
        ]);
        $resAlloc->assertStatus(201)->assertJsonPath('data.status', 'DEPLOYED');
        $allocId = $resAlloc->json('data.id');

        $this->assertSame('DEPLOYED', $this->boatAsset->fresh()->status);

        $this->withToken($drroToken)->postJson("/api/v1/response/operations/{$opId}/resources", [
            'resource_asset_id' => $this->boatAsset->id,
            'requirement_title' => 'Duplicate Boat Claim',
            'quantity_needed' => 1,
            'priority' => 'HIGH',
        ])->assertStatus(422);

        $this->withToken($drroToken)->putJson("/api/v1/response/resources/{$allocId}", ['status' => 'RETURNED'])
            ->assertStatus(200);
        $this->assertSame('AVAILABLE', $this->boatAsset->fresh()->status);

        // 6. Task Creation, Progress Update & Private Evidence Upload/Download
        $taskRes = $this->withToken($pioToken)->postJson("/api/v1/response/operations/{$opId}/tasks", [
            'activity_type_id' => $this->sarActivity->id,
            'response_team_id' => $teamId,
            'title_en' => 'Rescue stranded fishers at Sutarkhali canal',
            'priority' => 'CRITICAL',
        ]);
        $taskRes->assertStatus(201)->assertJsonPath('data.status', ResponseTask::STATUS_PENDING);
        $taskId = $taskRes->json('data.id');

        $this->withToken($pioToken)->putJson("/api/v1/response/tasks/{$taskId}", [
            'status' => 'COMPLETED',
        ])->assertStatus(200)->assertJsonPath('data.progress_percentage', 100);

        $evidence = UploadedFile::fake()->image('sar-mission.jpg', 400, 300);
        $attRes = $this->withToken($pioToken)->post("/api/v1/response/tasks/{$taskId}/attachments", [
            'file' => $evidence,
            'title' => 'SAR Boat Completion Photo',
        ]);
        $attRes->assertStatus(201);
        $attId = $attRes->json('data.id');

        $this->withToken($drroToken)->get("/api/v1/response/tasks/{$taskId}/attachments/{$attId}")->assertStatus(200);
        $this->withToken($barishalDrroToken)->get("/api/v1/response/tasks/{$taskId}/attachments/{$attId}")->assertStatus(403);

        // 7. Emergency Situation Report & GIS Location Privacy Check
        $this->withToken($pioToken)->postJson("/api/v1/response/operations/{$opId}/reports", [
            'report_type' => 'SOS',
            'title_en' => 'Embankment breach at Sutarkhali Ward 4',
            'summary' => '22 residents rescued by boat team; urgent drinking water requested.',
            'rescued_count' => 22,
            'casualties_injured' => 3,
            'emergency_requirements' => '2000L potable water & dry rations',
            'location_description' => 'Sutarkhali Polder 32 exact house cluster',
            'latitude' => 22.5489123,
            'longitude' => 89.5276543,
            'privacy_level' => 'RESTRICTED_EXACT',
        ])->assertStatus(201);

        // Commander (DRRO) sees exact coordinates; External Partner receives generalized (~2-decimal) coords & masked flag!
        $drroGeo = $this->withToken($drroToken)->getJson("/api/v1/response/gis/geojson?response_operation_id={$opId}");
        $drroGeo->assertStatus(200)->assertJsonPath('data.metadata.exact_gps_authorized', true);
        $drroReportFeat = collect($drroGeo->json('data.features'))->firstWhere('properties.feature_type', 'situation_report');
        $this->assertFalse($drroReportFeat['properties']['location_masked']);
        $this->assertEqualsWithDelta(22.5489123, $drroReportFeat['geometry']['coordinates'][1], 0.0001);

        $partnerGeo = $this->withToken($partnerToken)->getJson("/api/v1/response/gis/geojson?response_operation_id={$opId}");
        $partnerGeo->assertStatus(200)->assertJsonPath('data.metadata.exact_gps_authorized', false);
        $partnerReportFeat = collect($partnerGeo->json('data.features'))->firstWhere('properties.feature_type', 'situation_report');
        $this->assertTrue($partnerReportFeat['properties']['location_masked']);
        $this->assertSame(22.55, $partnerReportFeat['geometry']['coordinates'][1]);
        $this->assertSame(89.53, $partnerReportFeat['geometry']['coordinates'][0]);

        // 8. Dashboard & Close operation
        $this->withToken($drroToken)->getJson('/api/v1/response/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('data.summary.active_operations', 1)
            ->assertJsonPath('data.summary.completed_tasks', 1)
            ->assertJsonPath('data.summary.total_rescued', 22);

        $this->withToken($drroToken)
            ->postJson("/api/v1/response/operations/{$opId}/lifecycle", ['status' => 'CLOSED'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', ResponseOperation::STATUS_CLOSED);

        // Mutations on a CLOSED operation must return 422
        $this->withToken($pioToken)->postJson("/api/v1/response/operations/{$opId}/tasks", [
            'activity_type_id' => $this->sarActivity->id,
            'title_en' => 'Post Close Task',
            'priority' => 'LOW',
        ])->assertStatus(422);

        $this->withToken($drroToken)->putJson("/api/v1/response/operations/{$opId}", [
            'disaster_event_id' => $this->disaster->id,
            'administrative_unit_id' => $this->dacopeUpazila->id,
            'title_en' => 'Edited After Close',
            'priority' => 'HIGH',
            'severity_level' => 'DANGER',
            'start_date' => now()->toDateString(),
            'lead_organization' => 'DDM',
        ])->assertStatus(422);

        // External partner lookups redact registration_no and reports list masks coordinates
        $lookupsRes = $this->withToken($partnerToken)->getJson('/api/v1/response/lookups');
        $lookupsRes->assertStatus(200);
        $firstAsset = $lookupsRes->json('data.resource_assets.0');
        $this->assertNull($firstAsset['registration_no'] ?? null);

        $reportsRes = $this->withToken($partnerToken)->getJson('/api/v1/response/reports');
        $reportsRes->assertStatus(200)->assertJsonPath('data.0.location_masked', true);
    }

    public function test_operation_update_soft_delete_and_asset_auto_release_on_close(): void
    {
        $pioToken = $this->createToken([
            'sub' => '66666666-6666-4666-8666-666666666666',
            'preferred_username' => 'pio_crud_res',
            'email' => 'pio.crud.res@modmr.gov.bd',
            'roles' => ['ROLE_PIO'],
            'tier_level' => 'UPAZILA',
            'ancestry_path' => '/40/47/12/',
            'scope_id' => $this->dacopeUpazila->id,
        ]);

        $drroToken = $this->createToken([
            'sub' => '77777777-7777-4777-8777-777777777777',
            'preferred_username' => 'drro_crud_res',
            'email' => 'drro.crud.res@modmr.gov.bd',
            'roles' => ['ROLE_DRRO'],
            'tier_level' => 'DISTRICT',
            'ancestry_path' => '/40/47/',
            'scope_id' => $this->khulnaDistrict->id,
        ]);

        // Create Planned Op 1 -> Edit -> Delete
        $op1 = $this->withToken($pioToken)->postJson('/api/v1/response/operations', [
            'disaster_event_id' => $this->disaster->id,
            'administrative_unit_id' => $this->dacopeUpazila->id,
            'title_en' => 'Draft Response Op',
            'priority' => 'MEDIUM',
            'severity_level' => 'WARNING',
            'start_date' => now()->toDateString(),
            'lead_organization' => 'Dacope DMC',
        ]);
        $op1->assertStatus(201);
        $op1Id = $op1->json('data.id');

        $this->withToken($pioToken)->putJson("/api/v1/response/operations/{$op1Id}", [
            'disaster_event_id' => $this->disaster->id,
            'administrative_unit_id' => $this->dacopeUpazila->id,
            'title_en' => 'Updated Response Op',
            'priority' => 'HIGH',
            'severity_level' => 'DANGER',
            'start_date' => now()->toDateString(),
            'lead_organization' => 'Dacope Upazila DMC',
        ])->assertStatus(200)->assertJsonPath('data.title_en', 'Updated Response Op');

        $this->withToken($pioToken)->deleteJson("/api/v1/response/operations/{$op1Id}")
            ->assertStatus(200)
            ->assertJsonPath('data.deleted', true);

        // Create Op 2 -> Link boat -> Activate -> Cannot Delete Active (422) -> Close auto-releases boat!
        $op2 = $this->withToken($pioToken)->postJson('/api/v1/response/operations', [
            'disaster_event_id' => $this->disaster->id,
            'administrative_unit_id' => $this->dacopeUpazila->id,
            'title_en' => 'Auto Release Asset Op',
            'priority' => 'HIGH',
            'severity_level' => 'DANGER',
            'start_date' => now()->toDateString(),
            'lead_organization' => 'Dacope DMC',
        ]);
        $op2Id = $op2->json('data.id');

        $this->withToken($drroToken)->postJson("/api/v1/response/operations/{$op2Id}/resources", [
            'resource_asset_id' => $this->boatAsset->id,
            'requirement_title' => 'Boat Unit',
            'quantity_needed' => 1,
            'priority' => 'HIGH',
        ])->assertStatus(201);
        $this->assertSame('DEPLOYED', $this->boatAsset->fresh()->status);

        $this->withToken($drroToken)->postJson("/api/v1/response/operations/{$op2Id}/lifecycle", [
            'status' => 'ACTIVATED',
        ])->assertStatus(200);

        $this->withToken($drroToken)->deleteJson("/api/v1/response/operations/{$op2Id}")->assertStatus(422);

        $this->withToken($drroToken)->postJson("/api/v1/response/operations/{$op2Id}/lifecycle", [
            'status' => 'CLOSED',
        ])->assertStatus(200);

        $this->assertSame('AVAILABLE', $this->boatAsset->fresh()->status);
    }
}
