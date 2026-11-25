<?php

namespace Tests\Feature\Api\V1;

use App\Core\Models\AdministrativeUnit;
use App\Modules\Beneficiary\Models\Household;
use App\Modules\Disaster\Models\DisasterEvent;
use App\Modules\Relief\Models\DistributionPoint;
use App\Modules\Relief\Models\DistributionVoucher;
use App\Modules\Relief\Models\ReliefAllocation;
use App\Modules\Relief\Models\ReliefCategory;
use App\Modules\Relief\Models\ReliefRequisition;
use App\Modules\Warehouse\Models\Commodity;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Warehouse\Models\WarehouseInventory;
use Database\Seeders\AdministrativeUnitSeeder;
use Database\Seeders\DisasterEventSeeder;
use Database\Seeders\ReliefCatalogAndWarehouseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReliefModuleTest extends TestCase
{
    use RefreshDatabase;

    protected string $privateKey;
    protected string $cleanX5cCert;
    protected string $kid = 'idms-relief-test-key-2026';
    protected string $realm = 'idms-gov-realm';
    protected string $issuer = 'http://localhost:8080/realms/idms-gov-realm';
    protected string $audience = 'idms-backend-api';

    protected AdministrativeUnit $chalnaUnion;
    protected AdministrativeUnit $dacopeUpazila;
    protected AdministrativeUnit $khulnaDistrict;
    protected AdministrativeUnit $barishalDistrict;
    protected DisasterEvent $disaster;
    protected Commodity $riceCommodity;
    protected Commodity $waterCommodity;
    protected Warehouse $dacopeWarehouse;
    protected DistributionPoint $dacopePoint;
    protected Household $eligibleHousehold;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->seed(AdministrativeUnitSeeder::class);
        $this->seed(DisasterEventSeeder::class);
        $this->seed(ReliefCatalogAndWarehouseSeeder::class);

        $this->chalnaUnion = AdministrativeUnit::where('code', '35')->firstOrFail();
        $this->dacopeUpazila = AdministrativeUnit::where('code', '12')->firstOrFail();
        $this->khulnaDistrict = AdministrativeUnit::where('code', '47')->firstOrFail();
        $this->barishalDistrict = AdministrativeUnit::where('code', '10')->firstOrFail();

        $this->disaster = DisasterEvent::where('disaster_code', 'CYC-REMAL-2024')->firstOrFail();
        $this->riceCommodity = Commodity::where('commodity_code', 'COM-RICE-GR')->firstOrFail();
        $this->waterCommodity = Commodity::where('commodity_code', 'COM-WATER-5L')->firstOrFail();
        $this->dacopeWarehouse = Warehouse::where('warehouse_code', 'WH-DAC-STORE-01')->firstOrFail();
        $this->dacopePoint = DistributionPoint::where('point_code', 'DP-DAC-01')->firstOrFail();

        $this->eligibleHousehold = Household::create([
            'id' => (string) Str::uuid(),
            'household_code' => 'HH-DAC-ELIG-001',
            'administrative_unit_id' => $this->chalnaUnion->id,
            'hoh_name_en' => 'Rahima Khatun',
            'hoh_name_bn' => 'রহিমা খাতুন',
            'hoh_nid_hash' => hash('sha256', '1988404712345'),
            'hoh_nid_encrypted' => Crypt::encryptString('1988404712345'),
            'hoh_nid_masked' => '1988******345',
            'verification_status' => 'APPROVED',
            'vulnerability_score' => 92.5,
        ]);

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
            'sub' => 'c222334c-687f-4ca7-b24e-4b6807890701',
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

    public function test_unauthenticated_requests_are_rejected_with_401(): void
    {
        $this->getJson('/api/v1/relief/dashboard')->assertStatus(401);
        $this->getJson('/api/v1/relief/requisitions')->assertStatus(401);
    }

    public function test_super_admin_operational_blackout_allows_categories_and_view_but_blocks_transactions(): void
    {
        $superAdminToken = $this->createToken([
            'sub' => 'a0000001-0000-4000-8000-000000000001',
            'preferred_username' => 'super_admin',
            'email' => 'super.admin@modmr.gov.bd',
            'roles' => ['ROLE_SUPER_ADMIN'],
            'tier_level' => 'NATIONAL',
            'ancestry_path' => '/',
        ]);

        // Can view dashboard & manage relief categories
        $this->withToken($superAdminToken)
            ->getJson('/api/v1/relief/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->withToken($superAdminToken)
            ->postJson('/api/v1/relief/categories', [
                'code' => 'BABY_NUTRITION',
                'name_en' => 'Infant & Maternal Nutrition',
                'name_bn' => 'শিশু ও মাতৃ পুষ্টি সহায়তা',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.code', 'BABY_NUTRITION');

        // Operational blackout: cannot create requisition or allocation
        $this->withToken($superAdminToken)
            ->postJson('/api/v1/relief/requisitions', [
                'disaster_event_id' => $this->disaster->id,
                'administrative_unit_id' => $this->dacopeUpazila->id,
                'estimated_beneficiaries' => 50,
                'priority' => 'HIGH',
                'request_date' => now()->toDateString(),
                'items' => [['commodity_id' => $this->riceCommodity->id, 'quantity_requested' => 500]],
            ])
            ->assertStatus(403);

        $this->withToken($superAdminToken)
            ->postJson('/api/v1/relief/allocations', [
                'disaster_event_id' => $this->disaster->id,
                'administrative_unit_id' => $this->dacopeUpazila->id,
                'warehouse_id' => $this->dacopeWarehouse->id,
                'items' => [['commodity_id' => $this->riceCommodity->id, 'quantity_allocated' => 100]],
            ])
            ->assertStatus(403);
    }

    public function test_full_requisition_allocation_distribution_sod_idempotency_and_duplicate_flow(): void
    {
        $pioToken = $this->createToken([
            'sub' => '11111111-1111-4111-8111-111111111111',
            'preferred_username' => 'pio_dacope',
            'email' => 'pio.dacope@modmr.gov.bd',
            'roles' => ['ROLE_PIO'],
            'ancestry_path' => '/40/47/12/',
            'scope_id' => $this->dacopeUpazila->id,
        ]);

        $unoToken = $this->createToken([
            'sub' => '22222222-2222-4222-8222-222222222222',
            'preferred_username' => 'uno_dacope',
            'email' => 'uno.dacope@modmr.gov.bd',
            'roles' => ['ROLE_UNO'],
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

        $tagOfficerToken = $this->createToken([
            'sub' => '44444444-4444-4444-8444-444444444444',
            'preferred_username' => 'tag_dacope',
            'email' => 'tag.dacope@modmr.gov.bd',
            'roles' => ['ROLE_TAG_OFFICER'],
            'ancestry_path' => '/40/47/12/',
            'scope_id' => $this->dacopeUpazila->id,
        ]);

        $volunteerToken = $this->createToken([
            'sub' => '55555555-5555-4555-8555-555555555555',
            'preferred_username' => 'vol_dacope',
            'email' => 'vol.dacope@modmr.gov.bd',
            'roles' => ['ROLE_VOLUNTEER'],
            'tier_level' => 'UNION',
            'ancestry_path' => '/40/47/12/35/',
            'scope_id' => $this->chalnaUnion->id,
        ]);

        $barishalDrroToken = $this->createToken([
            'sub' => '66666666-6666-4666-8666-666666666666',
            'preferred_username' => 'drro_barishal',
            'email' => 'drro.barishal@modmr.gov.bd',
            'roles' => ['ROLE_DRRO'],
            'tier_level' => 'DISTRICT',
            'ancestry_path' => '/10/',
            'scope_id' => $this->barishalDistrict->id,
        ]);

        // 1. PIO creates requisition with supporting doc & submits immediately
        $doc = UploadedFile::fake()->create('dacope-damage-letter.pdf', 120, 'application/pdf');
        $reqResponse = $this->withToken($pioToken)->post('/api/v1/relief/requisitions', [
            'disaster_event_id' => $this->disaster->id,
            'administrative_unit_id' => $this->chalnaUnion->id,
            'requesting_organization' => 'Chalna Union DMC',
            'estimated_beneficiaries' => 120,
            'priority' => 'CRITICAL',
            'request_date' => now()->toDateString(),
            'justification' => 'Post-cyclone acute food and safe drinking water shortage.',
            'submit_immediately' => '1',
            'supporting_doc' => $doc,
            'items' => json_encode([
                ['commodity_id' => $this->riceCommodity->id, 'quantity_requested' => 500],
                ['commodity_id' => $this->waterCommodity->id, 'quantity_requested' => 200],
            ]),
        ]);

        $reqResponse->assertStatus(201)
            ->assertJsonPath('data.status', ReliefRequisition::STATUS_SUBMITTED)
            ->assertJsonPath('data.has_supporting_doc', true);

        $reqId = $reqResponse->json('data.id');
        $riceReqItemId = $reqResponse->json('data.items.0.id');
        $waterReqItemId = $reqResponse->json('data.items.1.id');

        // Download supporting doc
        $this->withToken($unoToken)
            ->get("/api/v1/relief/requisitions/{$reqId}/document")
            ->assertStatus(200);

        // 4-eye SoD: PIO who created requisition CANNOT review or approve it
        $this->withToken($pioToken)
            ->postJson("/api/v1/relief/requisitions/{$reqId}/approve", [])
            ->assertStatus(403);

        // Cross-district officer CANNOT view or approve Dacope requisition
        $this->withToken($barishalDrroToken)
            ->getJson("/api/v1/relief/requisitions/{$reqId}")
            ->assertStatus(403);

        // UNO moves to UNDER_REVIEW, then DRRO partially approves
        $this->withToken($unoToken)
            ->postJson("/api/v1/relief/requisitions/{$reqId}/review", ['review_notes' => 'Field figures verified.'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', ReliefRequisition::STATUS_UNDER_REVIEW);

        $this->withToken($drroToken)
            ->postJson("/api/v1/relief/requisitions/{$reqId}/approve", [
                'review_notes' => 'Sanctioned full rice and half water based on current buffer.',
                'items' => [
                    ['id' => $riceReqItemId, 'quantity_approved' => 500],
                    ['id' => $waterReqItemId, 'quantity_approved' => 100],
                ],
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', ReliefRequisition::STATUS_PARTIALLY_APPROVED);

        // 2. PIO creates Allocation Order from partially approved requisition
        $allocRes = $this->withToken($pioToken)->postJson('/api/v1/relief/allocations', [
            'relief_requisition_id' => $reqId,
            'disaster_event_id' => $this->disaster->id,
            'administrative_unit_id' => $this->chalnaUnion->id,
            'distribution_point_id' => $this->dacopePoint->id,
            'warehouse_id' => $this->dacopeWarehouse->id,
            'duplicate_window_days' => 30,
            'items' => [
                ['commodity_id' => $this->riceCommodity->id, 'quantity_allocated' => 500],
            ],
            'beneficiary_household_ids' => [$this->eligibleHousehold->id],
        ]);

        $allocRes->assertStatus(201)->assertJsonPath('data.status', ReliefAllocation::STATUS_DRAFT);
        $allocId = $allocRes->json('data.id');
        $allocItemId = $allocRes->json('data.items.0.id');

        // 4-eye SoD: PIO cannot sanction own allocation
        $this->withToken($pioToken)
            ->postJson("/api/v1/relief/allocations/{$allocId}/approve")
            ->assertStatus(403);

        // UNO sanctions allocation -> reserves 500 KG in WH-DAC-STORE-01 & marks requisition COMPLETED
        $this->withToken($unoToken)
            ->postJson("/api/v1/relief/allocations/{$allocId}/approve")
            ->assertStatus(200)
            ->assertJsonPath('data.status', ReliefAllocation::STATUS_SANCTIONED);

        $this->assertSame(
            500.0,
            (float) WarehouseInventory::where('warehouse_id', $this->dacopeWarehouse->id)
                ->where('commodity_id', $this->riceCommodity->id)
                ->sum('reserved_quantity')
        );

        $this->withToken($unoToken)
            ->postJson("/api/v1/relief/allocations/{$allocId}/dispatch")
            ->assertStatus(200)
            ->assertJsonPath('data.status', ReliefAllocation::STATUS_DISPATCHED);

        // 3. Volunteer records physical relief distribution with photo evidence + GPS + idempotency key
        $stockBefore = (float) WarehouseInventory::where('warehouse_id', $this->dacopeWarehouse->id)
            ->where('commodity_id', $this->riceCommodity->id)
            ->sum('current_quantity');

        $idemKey = 'idem-' . Str::uuid()->toString();
        $photo = UploadedFile::fake()->image('handoff.jpg', 640, 480);

        $distRes = $this->withToken($volunteerToken)->post('/api/v1/relief/distributions', [
            'relief_allocation_item_id' => $allocItemId,
            'household_id' => $this->eligibleHousehold->id,
            'distribution_point_id' => $this->dacopePoint->id,
            'idempotency_key' => $idemKey,
            'quantity_disbursed' => 25,
            'verification_method' => 'SIGNATURE_MANUAL',
            'acknowledgment_signature' => 'Rahima Khatun (Thumbprint)',
            'photo_evidence' => $photo,
            'distribution_lat' => 22.5762,
            'distribution_lng' => 89.5145,
        ]);

        $distRes->assertStatus(201)
            ->assertJsonPath('idempotent_replay', false)
            ->assertJsonPath('data.has_photo_evidence', true)
            ->assertJsonPath('data.quantity_disbursed', 25);

        $voucherId = $distRes->json('data.id');

        $stockAfterFirst = (float) WarehouseInventory::where('warehouse_id', $this->dacopeWarehouse->id)
            ->where('commodity_id', $this->riceCommodity->id)
            ->sum('current_quantity');
        $this->assertSame($stockBefore - 25.0, $stockAfterFirst);

        // Idempotent replay with SAME key must return 200 and NOT deduct stock twice!
        $replayRes = $this->withToken($volunteerToken)->postJson('/api/v1/relief/distributions', [
            'relief_allocation_item_id' => $allocItemId,
            'household_id' => $this->eligibleHousehold->id,
            'distribution_point_id' => $this->dacopePoint->id,
            'idempotency_key' => $idemKey,
            'quantity_disbursed' => 25,
            'verification_method' => 'SIGNATURE_MANUAL',
        ]);

        $replayRes->assertStatus(200)
            ->assertJsonPath('idempotent_replay', true)
            ->assertJsonPath('data.id', $voucherId);

        $this->assertSame(
            $stockAfterFirst,
            (float) WarehouseInventory::where('warehouse_id', $this->dacopeWarehouse->id)
                ->where('commodity_id', $this->riceCommodity->id)
                ->sum('current_quantity')
        );

        // 4. Duplicate assistance block + authorized exception override
        $this->withToken($volunteerToken)->postJson('/api/v1/relief/distributions', [
            'relief_allocation_item_id' => $allocItemId,
            'household_id' => $this->eligibleHousehold->id,
            'distribution_point_id' => $this->dacopePoint->id,
            'idempotency_key' => 'idem-' . Str::uuid()->toString(),
            'quantity_disbursed' => 10,
            'verification_method' => 'SIGNATURE_MANUAL',
        ])->assertStatus(422);

        // Volunteer cannot authorize duplicate override -> 403
        $this->withToken($volunteerToken)->postJson('/api/v1/relief/distributions', [
            'relief_allocation_item_id' => $allocItemId,
            'household_id' => $this->eligibleHousehold->id,
            'distribution_point_id' => $this->dacopePoint->id,
            'idempotency_key' => 'idem-' . Str::uuid()->toString(),
            'quantity_disbursed' => 10,
            'verification_method' => 'SIGNATURE_MANUAL',
            'allow_duplicate_exception' => true,
            'exception_reason' => 'Emergency medical family extension authorized.',
        ])->assertStatus(403);

        // PIO CAN authorize duplicate exception with detailed documented reason -> 201
        $overrideRes = $this->withToken($pioToken)->postJson('/api/v1/relief/distributions', [
            'relief_allocation_item_id' => $allocItemId,
            'household_id' => $this->eligibleHousehold->id,
            'distribution_point_id' => $this->dacopePoint->id,
            'idempotency_key' => 'idem-' . Str::uuid()->toString(),
            'quantity_disbursed' => 10,
            'verification_method' => 'SIGNATURE_MANUAL',
            'allow_duplicate_exception' => true,
            'exception_reason' => 'House destroyed by second tidal surge; UNO supplemental ration approved.',
        ]);

        $overrideRes->assertStatus(201)->assertJsonPath('data.duplicate_override', true);
        $overrideVoucherId = $overrideRes->json('data.id');

        // 5. Tag Officer witness verification + 4-eye SoD (volunteer cannot self-verify)
        $this->withToken($volunteerToken)
            ->postJson("/api/v1/relief/distributions/{$voucherId}/verify")
            ->assertStatus(403);

        $this->withToken($tagOfficerToken)
            ->postJson("/api/v1/relief/distributions/{$voucherId}/verify")
            ->assertStatus(200)
            ->assertJsonPath('data.verifying_tag_officer.role_code', 'ROLE_TAG_OFFICER');

        // 6. Void second voucher -> restores 10 KG to warehouse inventory
        $this->withToken($unoToken)
            ->postJson("/api/v1/relief/distributions/{$overrideVoucherId}/void", [
                'void_reason' => 'Recorded against incorrect household voucher entry.',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.is_voided', true);

        $this->assertSame(
            $stockAfterFirst,
            (float) WarehouseInventory::where('warehouse_id', $this->dacopeWarehouse->id)
                ->where('commodity_id', $this->riceCommodity->id)
                ->sum('current_quantity')
        );

        // 7. Dashboard summary & CSV muster roll export
        $this->withToken($drroToken)
            ->getJson('/api/v1/relief/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('data.summary.total_requests', 1)
            ->assertJsonPath('data.summary.approved_allocations', 1)
            ->assertJsonPath('data.summary.vouchers_issued', 1)
            ->assertJsonPath('data.summary.beneficiaries_served', 1);

        $this->withToken($drroToken)
            ->get('/api/v1/relief/distributions/export-muster')
            ->assertStatus(200);

        // Cross-scope photo evidence & check-duplicate probe must be denied (403)
        $this->withToken($barishalDrroToken)
            ->get("/api/v1/relief/distributions/{$voucherId}/photo")
            ->assertStatus(403);

        $this->withToken($barishalDrroToken)
            ->getJson("/api/v1/relief/distributions/check-duplicate?relief_allocation_item_id={$allocItemId}&household_id={$this->eligibleHousehold->id}")
            ->assertStatus(403);

        // Cancel remaining 475 KG allocation balance -> releases reserved warehouse stock cleanly
        $this->withToken($unoToken)
            ->postJson("/api/v1/relief/allocations/{$allocId}/cancel", [
                'reason' => 'Tidal floodwaters receded; remaining grain returned to buffer stock.',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', ReliefAllocation::STATUS_COMPLETED)
            ->assertJsonPath('data.items.0.quantity_cancelled', 475);

        $this->assertSame(
            0.0,
            (float) WarehouseInventory::where('warehouse_id', $this->dacopeWarehouse->id)
                ->where('commodity_id', $this->riceCommodity->id)
                ->sum('reserved_quantity')
        );
    }

    public function test_cross_scope_creation_is_blocked_with_403(): void
    {
        $barishalDrroToken = $this->createToken([
            'sub' => '77777777-7777-4777-8777-777777777777',
            'preferred_username' => 'drro_barishal_2',
            'email' => 'drro.barishal2@modmr.gov.bd',
            'roles' => ['ROLE_DRRO'],
            'tier_level' => 'DISTRICT',
            'ancestry_path' => '/10/',
            'scope_id' => $this->barishalDistrict->id,
        ]);

        // Barishal DRRO attempting to create requisition for Dacope (Khulna) -> 403
        $this->withToken($barishalDrroToken)->postJson('/api/v1/relief/requisitions', [
            'disaster_event_id' => $this->disaster->id,
            'administrative_unit_id' => $this->dacopeUpazila->id,
            'estimated_beneficiaries' => 80,
            'priority' => 'MEDIUM',
            'request_date' => now()->toDateString(),
            'items' => [['commodity_id' => $this->riceCommodity->id, 'quantity_requested' => 200]],
        ])->assertStatus(403);

        // Barishal DRRO attempting to create allocation targeting Chalna (Khulna) -> 403
        $this->withToken($barishalDrroToken)->postJson('/api/v1/relief/allocations', [
            'disaster_event_id' => $this->disaster->id,
            'administrative_unit_id' => $this->chalnaUnion->id,
            'warehouse_id' => $this->dacopeWarehouse->id,
            'items' => [['commodity_id' => $this->riceCommodity->id, 'quantity_allocated' => 100]],
        ])->assertStatus(403);
    }

    public function test_requisition_draft_update_delete_submit_and_rejection_workflow(): void
    {
        $pioToken = $this->createToken([
            'sub' => '88888888-8888-4888-8888-888888888888',
            'preferred_username' => 'pio_dacope_crud',
            'email' => 'pio.crud@modmr.gov.bd',
            'roles' => ['ROLE_PIO'],
            'ancestry_path' => '/40/47/12/',
            'scope_id' => $this->dacopeUpazila->id,
        ]);

        $unoToken = $this->createToken([
            'sub' => '99999999-9999-4999-8999-999999999999',
            'preferred_username' => 'uno_dacope_crud',
            'email' => 'uno.crud@modmr.gov.bd',
            'roles' => ['ROLE_UNO'],
            'ancestry_path' => '/40/47/12/',
            'scope_id' => $this->dacopeUpazila->id,
        ]);

        // Create Draft 1 -> Update -> Delete
        $draft1 = $this->withToken($pioToken)->postJson('/api/v1/relief/requisitions', [
            'disaster_event_id' => $this->disaster->id,
            'administrative_unit_id' => $this->dacopeUpazila->id,
            'estimated_beneficiaries' => 40,
            'priority' => 'LOW',
            'request_date' => now()->toDateString(),
            'items' => [['commodity_id' => $this->riceCommodity->id, 'quantity_requested' => 100]],
        ]);
        $draft1->assertStatus(201)->assertJsonPath('data.status', ReliefRequisition::STATUS_DRAFT);
        $draft1Id = $draft1->json('data.id');

        $this->withToken($pioToken)->putJson("/api/v1/relief/requisitions/{$draft1Id}", [
            'disaster_event_id' => $this->disaster->id,
            'administrative_unit_id' => $this->dacopeUpazila->id,
            'estimated_beneficiaries' => 65,
            'priority' => 'HIGH',
            'request_date' => now()->toDateString(),
            'items' => [['commodity_id' => $this->riceCommodity->id, 'quantity_requested' => 250]],
        ])->assertStatus(200)
            ->assertJsonPath('data.estimated_beneficiaries', 65)
            ->assertJsonPath('data.items.0.quantity_requested', 250);

        $this->withToken($pioToken)
            ->deleteJson("/api/v1/relief/requisitions/{$draft1Id}")
            ->assertStatus(200)
            ->assertJsonPath('data.deleted', true);

        // Create Draft 2 -> Submit -> Cannot Delete Submitted (422) -> UNO Rejects (requires >= 10 char notes)
        $draft2 = $this->withToken($pioToken)->postJson('/api/v1/relief/requisitions', [
            'disaster_event_id' => $this->disaster->id,
            'administrative_unit_id' => $this->dacopeUpazila->id,
            'estimated_beneficiaries' => 90,
            'priority' => 'MEDIUM',
            'request_date' => now()->toDateString(),
            'items' => [['commodity_id' => $this->waterCommodity->id, 'quantity_requested' => 300]],
        ]);
        $draft2Id = $draft2->json('data.id');

        $this->withToken($pioToken)
            ->postJson("/api/v1/relief/requisitions/{$draft2Id}/submit")
            ->assertStatus(200)
            ->assertJsonPath('data.status', ReliefRequisition::STATUS_SUBMITTED);

        $this->withToken($pioToken)
            ->deleteJson("/api/v1/relief/requisitions/{$draft2Id}")
            ->assertStatus(422);

        $this->withToken($unoToken)
            ->postJson("/api/v1/relief/requisitions/{$draft2Id}/reject", ['review_notes' => 'short'])
            ->assertStatus(422);

        $this->withToken($unoToken)
            ->postJson("/api/v1/relief/requisitions/{$draft2Id}/reject", [
                'review_notes' => 'Duplicate request already covered by active Union allocation order.',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', ReliefRequisition::STATUS_REJECTED);
    }

    public function test_external_partner_pii_is_redacted_and_validation_rejects_invalid_payloads(): void
    {
        $unoToken = $this->createToken([
            'sub' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'preferred_username' => 'uno_partner_setup',
            'email' => 'uno.setup@modmr.gov.bd',
            'roles' => ['ROLE_UNO'],
            'ancestry_path' => '/40/47/12/',
            'scope_id' => $this->dacopeUpazila->id,
        ]);

        $partnerToken = $this->createToken([
            'sub' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'preferred_username' => 'ext_partner',
            'email' => 'partner@ngo.org',
            'roles' => ['ROLE_EXTERNAL_PARTNER'],
            'tier_level' => 'NATIONAL',
            'ancestry_path' => '/',
        ]);

        // Validation error on empty requisition payload
        $this->withToken($unoToken)->postJson('/api/v1/relief/allocations', [])->assertStatus(422);

        // Partner can view anonymized dashboard/allocations/distributions, never create, and sees redacted household fields
        $this->withToken($partnerToken)->getJson('/api/v1/relief/dashboard')->assertStatus(200);
        $this->withToken($partnerToken)->getJson('/api/v1/relief/eligible-households')->assertStatus(200)->assertJsonCount(0, 'data');
        $this->withToken($partnerToken)->postJson('/api/v1/relief/requisitions', [])->assertStatus(403);
    }

    public function test_field_enumerator_and_volunteer_can_view_allocations_households_and_distributions(): void
    {
        foreach (['ROLE_FIELD_ENUMERATOR', 'ROLE_VOLUNTEER'] as $idx => $role) {
            $token = $this->createToken([
                'sub' => "cccccccc-000{$idx}-4ccc-8ccc-cccccccccccc",
                'preferred_username' => strtolower($role),
                'email' => strtolower($role) . '@modmr.gov.bd',
                'roles' => [$role],
                'tier_level' => 'UPAZILA',
                'ancestry_path' => '/40/47/12/',
                'scope_id' => $this->dacopeUpazila->id,
            ]);

            $this->withToken($token)->getJson('/api/v1/relief/lookups')->assertStatus(200);
            $this->withToken($token)->getJson('/api/v1/relief/allocations?per_page=50')->assertStatus(200);
            $this->withToken($token)->getJson('/api/v1/relief/eligible-households')->assertStatus(200);
            $this->withToken($token)->getJson('/api/v1/relief/distributions?per_page=25')->assertStatus(200);

            // Still cannot create/approve allocations or requisitions
            $this->withToken($token)->postJson('/api/v1/relief/allocations', [])->assertStatus(403);
            $this->withToken($token)->postJson('/api/v1/relief/requisitions', [])->assertStatus(403);
        }
    }
}
