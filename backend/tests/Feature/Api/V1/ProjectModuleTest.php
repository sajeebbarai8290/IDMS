<?php

namespace Tests\Feature\Api\V1;

use App\Core\Models\AdministrativeUnit;
use App\Modules\Disaster\Models\DisasterEvent;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectCategory;
use Database\Seeders\AdministrativeUnitSeeder;
use Database\Seeders\DisasterEventSeeder;
use Database\Seeders\ProjectSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectModuleTest extends TestCase
{
    use RefreshDatabase;

    protected string $privateKey;
    protected string $cleanX5cCert;
    protected string $kid = 'idms-project-test-key-2026';
    protected string $realm = 'idms-gov-realm';
    protected string $issuer = 'http://localhost:8080/realms/idms-gov-realm';
    protected string $audience = 'idms-backend-api';

    protected AdministrativeUnit $dacopeUpazila;
    protected AdministrativeUnit $chalnaUnion;
    protected AdministrativeUnit $khulnaDistrict;
    protected AdministrativeUnit $barishalDistrict;
    protected DisasterEvent $disaster;
    protected ProjectCategory $rehabCategory;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->seed(AdministrativeUnitSeeder::class);
        $this->seed(DisasterEventSeeder::class);
        $this->seed(ProjectSeeder::class);

        $this->dacopeUpazila = AdministrativeUnit::where('code', '12')->firstOrFail();
        $this->chalnaUnion = AdministrativeUnit::where('code', '35')->firstOrFail();
        $this->khulnaDistrict = AdministrativeUnit::where('code', '47')->firstOrFail();
        $this->barishalDistrict = AdministrativeUnit::where('code', '10')->firstOrFail();
        $this->disaster = DisasterEvent::where('disaster_code', 'CYC-REMAL-2024')->firstOrFail();
        $this->rehabCategory = ProjectCategory::where('code', 'REHABILITATION')->firstOrFail();

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
            'sub' => 'p1111111-1111-4111-8111-111111111111',
            'preferred_username' => 'drro_khulna',
            'name' => 'Md. Rafiqul Islam (DRRO)',
            'email' => 'drro.khulna@modmr.gov.bd',
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'iat' => $now,
            'nbf' => $now - 5,
            'exp' => $now + 3600,
            'roles' => ['ROLE_DRRO'],
            'tier_level' => 'DISTRICT',
            'ancestry_path' => $this->khulnaDistrict->ancestry_path,
            'scope_id' => $this->khulnaDistrict->id,
            'designation' => 'District Relief & Rehabilitation Officer',
        ], $overrides);

        $b64Header = rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '=');
        $b64Payload = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $signingInput = "{$b64Header}.{$b64Payload}";

        $signature = '';
        openssl_sign($signingInput, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);
        $b64Sig = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        return "{$signingInput}.{$b64Sig}";
    }

    public function test_unauthenticated_project_routes_return_401(): void
    {
        $this->getJson('/api/v1/project/dashboard')->assertStatus(401);
        $this->getJson('/api/v1/project/schemes')->assertStatus(401);
        $this->postJson('/api/v1/project/schemes', [])->assertStatus(401);
    }

    public function test_super_admin_blackout_allows_view_export_and_category_config_but_blocks_operations(): void
    {
        $adminToken = $this->createToken([
            'sub' => '99999999-1111-4111-8111-111111111111',
            'preferred_username' => 'super_admin',
            'email' => 'super.admin@modmr.gov.bd',
            'roles' => ['ROLE_SUPER_ADMIN'],
            'tier_level' => 'NATIONAL',
            'ancestry_path' => '/',
            'scope_id' => null,
        ]);

        $this->withToken($adminToken)->getJson('/api/v1/project/dashboard')->assertStatus(200);
        $this->withToken($adminToken)->get('/api/v1/project/export')->assertStatus(200);

        $this->withToken($adminToken)->postJson('/api/v1/project/categories', [
            'code' => 'CLIMATE_ADAPT',
            'name_en' => 'Climate Adaptation',
            'name_bn' => 'জলবায়ু অভিযোজন',
        ])->assertStatus(201);

        $this->withToken($adminToken)->postJson('/api/v1/project/schemes', [
            'category_id' => $this->rehabCategory->id,
            'administrative_unit_id' => $this->dacopeUpazila->id,
            'title_en' => 'Blocked Admin Project',
            'title_bn' => 'ব্লকড প্রকল্প',
            'implementing_organization' => 'DDM',
            'project_manager_name' => 'Admin',
            'funding_source' => 'GoB',
            'approved_budget_bdt' => 500000,
            'start_date' => '2026-06-01',
            'expected_end_date' => '2026-12-31',
        ])->assertStatus(403);
    }

    public function test_jurisdiction_scoping_and_external_partner_privacy(): void
    {
        $khulnaDrroToken = $this->createToken();
        $barishalProject = Project::where('project_code', 'PRJ-MODMR-2026-002')->firstOrFail();
        $khulnaProject = Project::where('project_code', 'PRJ-MODMR-2026-001')->firstOrFail();

        // Khulna DRRO sees Khulna project, blocked 403 from Barishal project
        $this->withToken($khulnaDrroToken)->getJson("/api/v1/project/schemes/{$khulnaProject->id}")->assertStatus(200);
        $this->withToken($khulnaDrroToken)->getJson("/api/v1/project/schemes/{$barishalProject->id}")->assertStatus(403);

        // External partner gets anonymized view (contact masked, internal attachment download denied)
        $partnerToken = $this->createToken([
            'sub' => 'e1111111-1111-4111-8111-111111111111',
            'preferred_username' => 'wfp_observer',
            'email' => 'observer@wfp.org',
            'roles' => ['ROLE_EXTERNAL_PARTNER'],
            'tier_level' => 'NATIONAL',
            'ancestry_path' => '/',
            'scope_id' => null,
        ]);

        $showRes = $this->withToken($partnerToken)
            ->getJson("/api/v1/project/schemes/{$khulnaProject->id}")
            ->assertStatus(200);

        $this->assertNull($showRes->json('data.project_manager_contact'));

        $disbursement = $khulnaProject->disbursements()->firstOrFail();
        $this->withToken($partnerToken)
            ->get("/api/v1/project/financials/{$disbursement->id}/document")
            ->assertStatus(403);
    }

    public function test_full_project_lifecycle_wbs_financials_monitoring_risks_and_reports(): void
    {
        $pioToken = $this->createToken([
            'sub' => 'p2222222-2222-4222-8222-222222222222',
            'preferred_username' => 'pio_dacope',
            'email' => 'pio.dacope@modmr.gov.bd',
            'roles' => ['ROLE_PIO'],
            'tier_level' => 'UPAZILA',
            'ancestry_path' => $this->dacopeUpazila->ancestry_path,
            'scope_id' => $this->dacopeUpazila->id,
        ]);

        $unoToken = $this->createToken([
            'sub' => 'p3333333-3333-4333-8333-333333333333',
            'preferred_username' => 'uno_dacope',
            'email' => 'uno.dacope@modmr.gov.bd',
            'roles' => ['ROLE_UNO'],
            'tier_level' => 'UPAZILA',
            'ancestry_path' => $this->dacopeUpazila->ancestry_path,
            'scope_id' => $this->dacopeUpazila->id,
        ]);

        $dcToken = $this->createToken([
            'sub' => 'p4444444-4444-4444-8444-444444444444',
            'preferred_username' => 'dc_khulna',
            'email' => 'dc.khulna@modmr.gov.bd',
            'roles' => ['ROLE_DEPUTY_COMMISSIONER'],
            'tier_level' => 'DISTRICT',
            'ancestry_path' => $this->khulnaDistrict->ancestry_path,
            'scope_id' => $this->khulnaDistrict->id,
        ]);

        // 1. Create Project (PROPOSED) with multi-locations & GIS
        $createRes = $this->withToken($pioToken)->postJson('/api/v1/project/schemes', [
            'category_id' => $this->rehabCategory->id,
            'disaster_event_id' => $this->disaster->id,
            'administrative_unit_id' => $this->dacopeUpazila->id,
            'reference_no' => 'DDM/DAC/2026/99',
            'title_en' => 'Chalna Cyclone Shelter Approach Culvert Reconstruction',
            'title_bn' => 'চালনা আশ্রয়কেন্দ্র সংযোগ কালভার্ট পুনর্নির্মাণ',
            'description' => 'Box culvert and paved approach reconstruction.',
            'objectives' => 'Ensure all-weather shelter access.',
            'implementing_organization' => 'Upazila PIO & LGED',
            'project_manager_name' => 'PIO Dacope',
            'project_manager_contact' => '01711223344',
            'funding_source' => 'GoB MoDMR',
            'approved_budget_bdt' => 2000000,
            'start_date' => '2026-05-01',
            'expected_end_date' => '2026-11-30',
            'ward_no' => 'Ward 04',
            'latitude' => 22.6021,
            'longitude' => 89.5312,
            'locations' => [
                [
                    'administrative_unit_id' => $this->chalnaUnion->id,
                    'ward_no' => 'Ward 04',
                    'site_name_en' => 'Chalna Bazar Culvert',
                    'site_name_bn' => 'চালনা বাজার কালভার্ট',
                    'latitude' => 22.6021,
                    'longitude' => 89.5312,
                ],
            ],
        ])->assertStatus(201);

        $projectId = $createRes->json('data.id');
        $this->assertSame('PROPOSED', $createRes->json('data.status'));
        $this->assertCount(1, $createRes->json('data.locations'));

        // 2. Submit for review -> UNDER_REVIEW
        $this->withToken($pioToken)->postJson("/api/v1/project/schemes/{$projectId}/lifecycle", [
            'status' => 'UNDER_REVIEW',
            'notes' => 'Submitted with engineering estimate.',
        ])->assertStatus(200)->assertJsonPath('data.status', 'UNDER_REVIEW');

        // 4-Eye SoD: UNO cannot approve if UNO was creator (here PIO created, test creator trying if UNO created, or test UNO approving vs PIO blocked)
        // PIO lacks scheme.approve role -> 403
        $this->withToken($pioToken)->postJson("/api/v1/project/schemes/{$projectId}/lifecycle", [
            'status' => 'APPROVED',
        ])->assertStatus(403);

        // UNO approves -> APPROVED
        $this->withToken($unoToken)->postJson("/api/v1/project/schemes/{$projectId}/lifecycle", [
            'status' => 'APPROVED',
            'notes' => 'Sanctioned by UzDMC.',
        ])->assertStatus(200)->assertJsonPath('data.status', 'APPROVED');

        // Start project -> ONGOING
        $this->withToken($pioToken)->postJson("/api/v1/project/schemes/{$projectId}/lifecycle", [
            'status' => 'ONGOING',
        ])->assertStatus(200)->assertJsonPath('data.status', 'ONGOING');

        // Suspend & Resume
        $this->withToken($unoToken)->postJson("/api/v1/project/schemes/{$projectId}/lifecycle", [
            'status' => 'SUSPENDED',
            'delay_reason' => 'Monsoon waterlogging',
        ])->assertStatus(200)->assertJsonPath('data.is_delayed', true);

        $this->withToken($unoToken)->postJson("/api/v1/project/schemes/{$projectId}/lifecycle", [
            'status' => 'ONGOING',
        ])->assertStatus(200)->assertJsonPath('data.status', 'ONGOING');

        // 3. WBS Milestones & Weighted Progress + 4-Eye Certification
        $m1Res = $this->withToken($pioToken)->postJson("/api/v1/project/schemes/{$projectId}/milestones", [
            'wbs_code' => '1.1',
            'milestone_title' => 'Foundation Piling',
            'milestone_title_bn' => 'ফাউন্ডেশন পাইলিং',
            'item_type' => 'ACTIVITY',
            'deliverables' => '12 RCC Piles',
            'weight_percentage' => 40,
            'target_percentage' => 100,
        ])->assertStatus(201);
        $m1Id = $m1Res->json('data.id');

        $this->withToken($pioToken)->postJson("/api/v1/project/schemes/{$projectId}/milestones", [
            'wbs_code' => '1.2',
            'milestone_title' => 'Deck Slab & Approach',
            'item_type' => 'MILESTONE',
            'weight_percentage' => 60,
            'target_percentage' => 100,
        ])->assertStatus(201);

        // Update milestone 1 progress to 100% -> project physical progress becomes 40%
        $this->withToken($pioToken)->putJson("/api/v1/project/milestones/{$m1Id}/progress", [
            'completion_percentage' => 100,
        ])->assertStatus(200);

        $this->withToken($pioToken)->getJson("/api/v1/project/schemes/{$projectId}")
            ->assertJsonPath('data.physical_progress_pct', 40);

        // 4-Eye SoD: PIO who updated progress cannot certify
        $this->withToken($pioToken)->postJson("/api/v1/project/milestones/{$m1Id}/certify", [
            'approval_status' => 'APPROVED_PIO',
        ])->assertStatus(403);

        // UNO certifies milestone
        $this->withToken($unoToken)->postJson("/api/v1/project/milestones/{$m1Id}/certify", [
            'approval_status' => 'APPROVED_PIO',
            'notes' => 'Field measurements verified.',
        ])->assertStatus(200)->assertJsonPath('data.approval_status', 'APPROVED_PIO');

        // 4. Financial Management: Ceiling guard, Fund Release, Expenditure, 4-Eye SoD, Supporting Doc
        // Over-budget expenditure rejected (422)
        $this->withToken($pioToken)->postJson("/api/v1/project/schemes/{$projectId}/financials", [
            'record_type' => 'EXPENDITURE',
            'amount_bdt' => 2500000,
        ])->assertStatus(422);

        $docFile = UploadedFile::fake()->create('bill-voucher.pdf', 120, 'application/pdf');
        $relRes = $this->withToken($pioToken)->post("/api/v1/project/schemes/{$projectId}/financials", [
            'project_milestone_id' => $m1Id,
            'record_type' => 'FUND_RELEASE',
            'fiscal_year' => '2025-2026',
            'amount_bdt' => 1000000,
            'description' => 'First tranche release',
            'supporting_doc' => $docFile,
        ])->assertStatus(201);

        $relId = $relRes->json('data.id');
        $this->assertTrue($relRes->json('data.has_supporting_doc'));
        $this->assertNotEmpty($relRes->json('data.supporting_doc_sha256'));

        // 4-Eye SoD: PIO cannot approve own financial voucher
        $this->withToken($pioToken)->postJson("/api/v1/project/financials/{$relId}/approve", [
            'payment_status' => 'RELEASED',
        ])->assertStatus(403);

        // DC approves fund release
        $this->withToken($dcToken)->postJson("/api/v1/project/financials/{$relId}/approve", [
            'payment_status' => 'RELEASED',
            'notes' => 'Released against milestone 1.1',
        ])->assertStatus(200)->assertJsonPath('data.payment_status', 'RELEASED');

        // Record & reconcile expenditure of 500,000 (25% of 2,000,000)
        $expRes = $this->withToken($pioToken)->postJson("/api/v1/project/schemes/{$projectId}/financials", [
            'project_milestone_id' => $m1Id,
            'record_type' => 'EXPENDITURE',
            'amount_bdt' => 500000,
            'description' => 'RA Bill-1 settlement',
        ])->assertStatus(201);

        $this->withToken($unoToken)->postJson("/api/v1/project/financials/{$expRes->json('data.id')}/approve", [
            'payment_status' => 'RECONCILED',
        ])->assertStatus(200);

        // Cannot reject an already RECONCILED financial voucher (state machine guard)
        $this->withToken($dcToken)->postJson("/api/v1/project/financials/{$expRes->json('data.id')}/approve", [
            'payment_status' => 'REJECTED',
        ])->assertStatus(422);

        // Cannot submit a BUDGET_REVISION lower than existing released funds / expenditure (1,000,000)
        $this->withToken($pioToken)->postJson("/api/v1/project/schemes/{$projectId}/financials", [
            'record_type' => 'BUDGET_REVISION',
            'amount_bdt' => 400000,
        ])->assertStatus(422);

        $afterFin = $this->withToken($unoToken)->getJson("/api/v1/project/schemes/{$projectId}")->assertStatus(200);
        $this->assertSame(1000000, $afterFin->json('data.released_funds_bdt'));
        $this->assertSame(500000, $afterFin->json('data.expenditure_bdt'));
        $this->assertSame(1500000, $afterFin->json('data.remaining_balance_bdt'));
        $this->assertSame(25, $afterFin->json('data.financial_progress_pct'));

        // Authorized supporting document download
        $this->withToken($dcToken)->get("/api/v1/project/financials/{$relId}/document")->assertStatus(200);

        // 5. Monitoring Report & Risks + GIS Boundary
        $monPhoto = UploadedFile::fake()->create('site-visit.jpg', 80, 'image/jpeg');
        $monRes = $this->withToken($unoToken)->post("/api/v1/project/schemes/{$projectId}/monitoring-reports", [
            'visit_date' => '2026-06-15',
            'observed_physical_pct' => 45,
            'delay_observed' => true,
            'delay_days' => 10,
            'delay_reason' => 'Tidal surge delay',
            'findings' => 'Piling quality satisfactory; dewatering required.',
            'recommendations' => 'Deploy extra pump.',
            'latitude' => 22.6021,
            'longitude' => 89.5312,
            'attachment' => $monPhoto,
        ])->assertStatus(201);

        $this->withToken($unoToken)->get("/api/v1/project/monitoring-reports/{$monRes->json('data.id')}/attachment")->assertStatus(200);

        $riskRes = $this->withToken($pioToken)->postJson("/api/v1/project/schemes/{$projectId}/risks", [
            'record_kind' => 'RISK',
            'title' => 'Tidal water intrusion',
            'severity' => 'HIGH',
            'likelihood' => 'MEDIUM',
            'mitigation_plan' => 'Sandbag cofferdam',
        ])->assertStatus(201);

        $this->withToken($pioToken)->putJson("/api/v1/project/risks/{$riskRes->json('data.id')}", [
            'status' => 'RESOLVED',
        ])->assertStatus(200)->assertJsonPath('data.status', 'RESOLVED');

        // GIS Boundary upload & GeoJSON endpoint
        $this->withToken($pioToken)->postJson("/api/v1/project/schemes/{$projectId}/boundary", [
            'boundary_geojson' => [
                'type' => 'Polygon',
                'coordinates' => [[[89.52, 22.59], [89.55, 22.59], [89.55, 22.62], [89.52, 22.62], [89.52, 22.59]]],
            ],
        ])->assertStatus(200);

        $this->withToken($unoToken)->getJson('/api/v1/project/gis/geojson')
            ->assertStatus(200)
            ->assertJsonPath('data.type', 'FeatureCollection');

        // 6. Dashboard & 5 Report Types & CSV Export
        $this->withToken($unoToken)->getJson('/api/v1/project/dashboard')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['summary', 'by_status', 'by_category', 'progress_by_location'],
            ]);

        foreach (['summary', 'financial', 'physical', 'milestones', 'monitoring'] as $repType) {
            $this->withToken($unoToken)->getJson("/api/v1/project/reports?type={$repType}")
                ->assertStatus(200)
                ->assertJsonPath('data.report_type', $repType);
        }

        // Update project and clear multi-locations with empty array []
        $this->withToken($pioToken)->putJson("/api/v1/project/schemes/{$projectId}", [
            'category_id' => $this->rehabCategory->id,
            'administrative_unit_id' => $this->dacopeUpazila->id,
            'title_en' => '=Chalna Cyclone Shelter Approach Culvert Reconstruction',
            'title_bn' => 'চালনা আশ্রয়কেন্দ্র সংযোগ কালভার্ট পুনর্নির্মাণ',
            'implementing_organization' => 'Upazila PIO & LGED',
            'project_manager_name' => 'PIO Dacope',
            'funding_source' => 'GoB MoDMR',
            'approved_budget_bdt' => 2000000,
            'start_date' => '2026-05-01',
            'expected_end_date' => '2026-11-30',
            'locations' => [],
        ])->assertStatus(200)->assertJsonCount(0, 'data.locations');

        $exportRes = $this->withToken($unoToken)->get('/api/v1/project/export')->assertStatus(200);
        $this->assertStringContainsString('text/csv', $exportRes->headers->get('Content-Type'));
        $csvBody = $exportRes->streamedContent();
        $this->assertStringContainsString("'=Chalna Cyclone Shelter Approach Culvert Reconstruction", $csvBody);

        // 7. Complete -> Close -> Immutable
        $this->withToken($pioToken)->postJson("/api/v1/project/schemes/{$projectId}/lifecycle", [
            'status' => 'COMPLETED',
        ])->assertStatus(200);

        $this->withToken($unoToken)->postJson("/api/v1/project/schemes/{$projectId}/lifecycle", [
            'status' => 'CLOSED',
        ])->assertStatus(200)->assertJsonPath('data.status', 'CLOSED');

        // Closed project cannot be updated
        $this->withToken($pioToken)->putJson("/api/v1/project/schemes/{$projectId}", [
            'category_id' => $this->rehabCategory->id,
            'administrative_unit_id' => $this->dacopeUpazila->id,
            'title_en' => 'Should Fail',
            'title_bn' => 'ব্যর্থ',
            'implementing_organization' => 'DDM',
            'project_manager_name' => 'PIO',
            'funding_source' => 'GoB',
            'approved_budget_bdt' => 2000000,
            'start_date' => '2026-05-01',
            'expected_end_date' => '2026-11-30',
        ])->assertStatus(422);

        // Cannot delete CLOSED project
        $this->withToken($pioToken)->deleteJson("/api/v1/project/schemes/{$projectId}")->assertStatus(422);
    }

    public function test_4_eye_sod_blocks_planner_from_approving_own_project_and_allows_draft_delete(): void
    {
        $plannerToken = $this->createToken([
            'sub' => 'p7777777-7777-4777-8777-777777777777',
            'preferred_username' => 'ddm_planner',
            'email' => 'planner@modmr.gov.bd',
            'roles' => ['ROLE_DDM_PLANNING_OFFICER'],
            'tier_level' => 'NATIONAL',
            'ancestry_path' => '/',
            'scope_id' => null,
        ]);

        $res = $this->withToken($plannerToken)->postJson('/api/v1/project/schemes', [
            'category_id' => $this->rehabCategory->id,
            'administrative_unit_id' => $this->dacopeUpazila->id,
            'title_en' => 'Planner Self-Approval Guard Test',
            'title_bn' => 'পরিকল্পনা অনুমোদন পরীক্ষা',
            'implementing_organization' => 'DDM',
            'project_manager_name' => 'Planner',
            'funding_source' => 'GoB',
            'approved_budget_bdt' => 1000000,
            'start_date' => '2026-06-01',
            'expected_end_date' => '2026-12-31',
        ])->assertStatus(201);

        $pid = $res->json('data.id');

        $this->withToken($plannerToken)->postJson("/api/v1/project/schemes/{$pid}/lifecycle", [
            'status' => 'UNDER_REVIEW',
        ])->assertStatus(200);

        // Same user who created the project cannot approve it (4-eye SoD -> 403)
        $this->withToken($plannerToken)->postJson("/api/v1/project/schemes/{$pid}/lifecycle", [
            'status' => 'APPROVED',
        ])->assertStatus(403);

        // Proposed/Under Review project can be soft-deleted
        $this->withToken($plannerToken)->deleteJson("/api/v1/project/schemes/{$pid}")->assertStatus(200);
    }
}
