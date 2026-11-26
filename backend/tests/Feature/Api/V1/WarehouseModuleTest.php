<?php

namespace Tests\Feature\Api\V1;

use App\Core\Models\AdministrativeUnit;
use App\Modules\Disaster\Models\DisasterEvent;
use App\Modules\Relief\Models\ReliefAllocation;
use App\Modules\Relief\Models\ReliefAllocationItem;
use App\Modules\Warehouse\Models\Commodity;
use App\Modules\Warehouse\Models\StockReceipt;
use App\Modules\Warehouse\Models\StockTransaction;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Warehouse\Models\WarehouseInventory;
use App\Modules\Warehouse\Models\WarehouseTransfer;
use Database\Seeders\AdministrativeUnitSeeder;
use Database\Seeders\DisasterEventSeeder;
use Database\Seeders\ReliefCatalogAndWarehouseSeeder;
use Database\Seeders\WarehouseModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WarehouseModuleTest extends TestCase
{
    use RefreshDatabase;

    protected string $privateKey;
    protected string $cleanX5cCert;
    protected string $kid = 'idms-warehouse-test-key-2026';
    protected string $realm = 'idms-gov-realm';
    protected string $issuer = 'http://localhost:8080/realms/idms-gov-realm';
    protected string $audience = 'idms-backend-api';

    protected AdministrativeUnit $khulnaDistrict;
    protected AdministrativeUnit $dacopeUpazila;
    protected AdministrativeUnit $barishalDistrict;
    protected Warehouse $khulnaWarehouse;
    protected Warehouse $nationalWarehouse;
    protected Warehouse $barishalWarehouse;
    protected Commodity $rice;
    protected Commodity $ors;
    protected DisasterEvent $disaster;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->seed(AdministrativeUnitSeeder::class);
        $this->seed(DisasterEventSeeder::class);
        $this->seed(ReliefCatalogAndWarehouseSeeder::class);
        $this->seed(WarehouseModuleSeeder::class);

        $this->khulnaDistrict = AdministrativeUnit::where('code', '47')->firstOrFail();
        $this->dacopeUpazila = AdministrativeUnit::where('code', '12')->firstOrFail();
        $this->barishalDistrict = AdministrativeUnit::where('code', '10')->firstOrFail();
        $this->disaster = DisasterEvent::where('disaster_code', 'CYC-REMAL-2024')->firstOrFail();
        $this->khulnaWarehouse = Warehouse::where('warehouse_code', 'WH-KHL-GODOWN-01')->firstOrFail();
        $this->nationalWarehouse = Warehouse::where('warehouse_code', 'WH-NAT-CSD-01')->firstOrFail();
        $this->rice = Commodity::where('commodity_code', 'COM-RICE-GR')->firstOrFail();
        $this->ors = Commodity::where('commodity_code', 'COM-ORS-KIT')->firstOrFail();

        $this->barishalWarehouse = Warehouse::create([
            'administrative_unit_id' => $this->barishalDistrict->id,
            'warehouse_code' => 'WH-BAR-GODOWN-01',
            'warehouse_name' => 'Barishal District Godown',
            'warehouse_name_bn' => 'বরিশাল জেলা গুদাম',
            'tier' => Warehouse::TIER_DISTRICT_GODOWN,
            'warehouse_type' => Warehouse::TYPE_RELIEF_GODOWN,
            'owning_organization' => 'DRRO Barishal',
            'capacity_mt' => 450.00,
            'latitude' => 22.7010,
            'longitude' => 90.3535,
            'is_active' => true,
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
            ['-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----', "\r", "\n"],
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
            'sub' => 'f1111111-1111-4111-8111-111111111111',
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
        $sig = '';
        openssl_sign($signingInput, $sig, $this->privateKey, OPENSSL_ALGO_SHA256);
        $sigB64 = rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');

        return "{$signingInput}.{$sigB64}";
    }

    public function test_unauthenticated_requests_are_rejected_and_rbac_constraints_hold(): void
    {
        $this->getJson('/api/v1/warehouse/dashboard')->assertStatus(401);

        // Super Admin can view facilities & export ledger, but is blocked from transactional stock ops
        $superAdminToken = $this->createToken([
            'sub' => 'f2222222-2222-4222-8222-222222222222',
            'preferred_username' => 'super_admin_wh',
            'email' => 'admin.wh@modmr.gov.bd',
            'roles' => ['ROLE_SUPER_ADMIN'],
            'tier_level' => 'MINISTRY',
            'ancestry_path' => '/',
            'scope_id' => null,
        ]);

        $this->withToken($superAdminToken)->getJson('/api/v1/warehouse/facilities')->assertOk();
        $this->withToken($superAdminToken)->get('/api/v1/warehouse/export-ledger')->assertOk();

        $this->withToken($superAdminToken)->postJson('/api/v1/warehouse/receipts', [
            'warehouse_id' => $this->khulnaWarehouse->id,
            'supplier_name' => 'Blocked Supplier',
            'received_date' => now()->toDateString(),
            'items' => [['commodity_id' => $this->rice->id, 'batch_number' => 'B-1', 'quantity_received' => 10]],
        ])->assertStatus(403);

        $this->withToken($superAdminToken)->postJson('/api/v1/warehouse/transfers', [
            'source_warehouse_id' => $this->nationalWarehouse->id,
            'destination_warehouse_id' => $this->khulnaWarehouse->id,
            'requested_date' => now()->toDateString(),
            'items' => [['commodity_id' => $this->rice->id, 'quantity_requested' => 5]],
        ])->assertStatus(403);

        // External Partner gets masked contact fields and cannot export ledger
        $partnerToken = $this->createToken([
            'sub' => 'f3333333-3333-4333-8333-333333333333',
            'preferred_username' => 'ext_partner_wh',
            'email' => 'partner.wh@ngo.org',
            'roles' => ['ROLE_EXTERNAL_PARTNER'],
            'tier_level' => 'NATIONAL',
            'ancestry_path' => '/',
            'scope_id' => null,
        ]);

        $partnerRes = $this->withToken($partnerToken)->getJson('/api/v1/warehouse/facilities/' . $this->khulnaWarehouse->id);
        $partnerRes->assertOk();
        $this->assertNull($partnerRes->json('data.responsible_officer_contact'));
        $this->assertNull($partnerRes->json('data.contact_email'));

        $this->withToken($partnerToken)->get('/api/v1/warehouse/export-ledger')->assertStatus(403);
    }

    public function test_facility_and_item_management_with_jurisdiction_scoping(): void
    {
        $khulnaDrroToken = $this->createToken();

        // Khulna DRRO cannot see or update Barishal District warehouse
        $listRes = $this->withToken($khulnaDrroToken)->getJson('/api/v1/warehouse/facilities?per_page=50');
        $listRes->assertOk();
        $codes = collect($listRes->json('data'))->pluck('warehouse_code')->all();
        $this->assertContains('WH-KHL-GODOWN-01', $codes);
        $this->assertContains('WH-NAT-CSD-01', $codes);
        $this->assertNotContains('WH-BAR-GODOWN-01', $codes);

        $this->withToken($khulnaDrroToken)->getJson('/api/v1/warehouse/facilities/' . $this->barishalWarehouse->id)->assertStatus(403);
        $this->withToken($khulnaDrroToken)->putJson('/api/v1/warehouse/facilities/' . $this->barishalWarehouse->id, [
            'capacity_mt' => 999,
        ])->assertStatus(403);

        // Khulna DRRO cannot register a warehouse inside Barishal District (outside jurisdiction)
        $this->withToken($khulnaDrroToken)->postJson('/api/v1/warehouse/facilities', [
            'administrative_unit_id' => $this->barishalDistrict->id,
            'warehouse_code' => 'WH-BAR-UNAUTH-01',
            'warehouse_name' => 'Cross Scope Barishal Store',
            'tier' => Warehouse::TIER_DISTRICT_GODOWN,
            'capacity_mt' => 100,
        ])->assertStatus(403);

        // Khulna DRRO registers a new emergency godown inside Dacope Upazila (Khulna jurisdiction)
        $createWhRes = $this->withToken($khulnaDrroToken)->postJson('/api/v1/warehouse/facilities', [
            'administrative_unit_id' => $this->dacopeUpazila->id,
            'warehouse_code' => 'WH-DACOPE-09',
            'warehouse_name' => 'Dacope Coastal Sub-Depot',
            'warehouse_name_bn' => 'দাকোপ উপকূলীয় উপ-গুদাম',
            'tier' => Warehouse::TIER_UPAZILA_STORE,
            'warehouse_type' => Warehouse::TYPE_EMERGENCY_HUB,
            'owning_organization' => 'PIO Dacope',
            'responsible_officer_name' => 'Shamim Ahmed',
            'responsible_officer_contact' => '+8801711111111',
            'capacity_mt' => 180.00,
            'latitude' => 22.5732,
            'longitude' => 89.5125,
        ]);

        $createWhRes->assertStatus(201);
        $this->assertEquals('WH-DACOPE-09', $createWhRes->json('data.warehouse_code'));

        // Duplicate code rejected
        $this->withToken($khulnaDrroToken)->postJson('/api/v1/warehouse/facilities', [
            'administrative_unit_id' => $this->dacopeUpazila->id,
            'warehouse_code' => 'WH-DACOPE-09',
            'warehouse_name' => 'Duplicate Depot',
            'tier' => Warehouse::TIER_UPAZILA_STORE,
            'capacity_mt' => 50,
        ])->assertStatus(422);

        // Create and update a bilingual commodity item with minimum stock level
        $itemRes = $this->withToken($khulnaDrroToken)->postJson('/api/v1/warehouse/items', [
            'commodity_code' => 'COM-SOLAR-KIT',
            'name_en' => 'Emergency Solar Lantern Kit',
            'name_bn' => 'জরুরি সোলার লণ্ঠন কিট',
            'category' => 'SHELTER',
            'unit_of_measure' => 'PIECE',
            'description' => 'Rechargeable dual-panel disaster lantern',
            'minimum_stock_level' => 75.00,
            'is_perishable' => false,
        ]);

        $itemRes->assertStatus(201);
        $itemId = $itemRes->json('data.id');

        $this->withToken($khulnaDrroToken)->putJson("/api/v1/warehouse/items/{$itemId}", [
            'minimum_stock_level' => 90.00,
        ])->assertOk()->assertJsonPath('data.minimum_stock_level', 90);
    }

    public function test_grn_receiving_verification_with_4_eye_sod_attachment_and_idempotency(): void
    {
        $whMgrToken = $this->createToken([
            'sub' => 'f4444444-4444-4444-8444-444444444444',
            'preferred_username' => 'wh_mgr_khl',
            'email' => 'whmgr.khl@modmr.gov.bd',
            'roles' => ['ROLE_WAREHOUSE_MANAGER'],
        ]);

        $drroToken = $this->createToken();

        $stockBefore = (float) WarehouseInventory::where('warehouse_id', $this->khulnaWarehouse->id)
            ->where('commodity_id', $this->rice->id)
            ->sum('current_quantity');

        $grnRes = $this->withToken($whMgrToken)->post('/api/v1/warehouse/receipts', [
            'warehouse_id' => $this->khulnaWarehouse->id,
            'supplier_name' => 'Khulna Regional Food Supplier',
            'supplier_contact' => '+8801712223334',
            'purchase_order_no' => 'PO-KHL-2026-88',
            'received_date' => now()->toDateString(),
            'idempotency_key' => 'IDEM-GRN-TEST-01',
            'notes' => 'Delivered in sealed tarpaulin truck',
            'attachment' => UploadedFile::fake()->create('signed_chalan.pdf', 120, 'application/pdf'),
            'items' => [
                [
                    'commodity_id' => $this->rice->id,
                    'batch_number' => 'LOT-KHL-RC-99',
                    'quantity_received' => 30.00,
                    'unit_cost_bdt' => 49500.00,
                    'expiry_date' => now()->addMonths(8)->toDateString(),
                ],
            ],
        ], ['Accept' => 'application/json']);

        $grnRes->assertStatus(201);
        $receiptId = $grnRes->json('data.id');
        $this->assertEquals(StockReceipt::STATUS_PENDING_VERIFICATION, $grnRes->json('data.status'));
        $this->assertNotEmpty($grnRes->json('data.attachment_sha256'));

        // Stock must NOT change before verification
        $stockWhilePending = (float) WarehouseInventory::where('warehouse_id', $this->khulnaWarehouse->id)
            ->where('commodity_id', $this->rice->id)
            ->sum('current_quantity');
        $this->assertEquals($stockBefore, $stockWhilePending);

        // Idempotent replay returns existing GRN without duplication
        $replayRes = $this->withToken($whMgrToken)->postJson('/api/v1/warehouse/receipts', [
            'warehouse_id' => $this->khulnaWarehouse->id,
            'supplier_name' => 'Khulna Regional Food Supplier',
            'received_date' => now()->toDateString(),
            'idempotency_key' => 'IDEM-GRN-TEST-01',
            'items' => [
                ['commodity_id' => $this->rice->id, 'batch_number' => 'LOT-KHL-RC-99', 'quantity_received' => 30.00],
            ],
        ]);
        $replayRes->assertStatus(201);
        $this->assertEquals($receiptId, $replayRes->json('data.id'));

        // 4-eye SoD: receiver cannot self-verify
        $this->withToken($whMgrToken)->postJson("/api/v1/warehouse/receipts/{$receiptId}/verify", [
            'verification_notes' => 'Self verify must fail',
        ])->assertStatus(403);

        // Second officer (Khulna DRRO) verifies GRN -> stock is added & ledger created
        $verifyRes = $this->withToken($drroToken)->postJson("/api/v1/warehouse/receipts/{$receiptId}/verify", [
            'verification_notes' => 'Moisture and bag count verified',
        ]);
        $verifyRes->assertOk();
        $this->assertEquals(StockReceipt::STATUS_VERIFIED, $verifyRes->json('data.status'));

        $stockAfter = (float) WarehouseInventory::where('warehouse_id', $this->khulnaWarehouse->id)
            ->where('commodity_id', $this->rice->id)
            ->sum('current_quantity');
        $this->assertEquals($stockBefore + 30.00, $stockAfter);

        // Private attachment download works for authorized user
        $downloadRes = $this->withToken($drroToken)->get("/api/v1/warehouse/receipts/{$receiptId}/attachment");
        $downloadRes->assertOk();
    }

    public function test_inter_warehouse_transfer_request_approve_dispatch_and_receipt_workflow(): void
    {
        $nationalDirectorToken = $this->createToken([
            'sub' => 'f5555555-5555-4555-8555-555555555555',
            'preferred_username' => 'ddm_relief_dir',
            'email' => 'relief.dir@ddm.gov.bd',
            'roles' => ['ROLE_DDM_RELIEF_DIRECTOR'],
            'tier_level' => 'DIRECTORATE',
            'ancestry_path' => '/',
            'scope_id' => null,
        ]);

        $dgToken = $this->createToken([
            'sub' => 'f6666666-6666-4666-8666-666666666666',
            'preferred_username' => 'ddm_dg_approver',
            'email' => 'dg@ddm.gov.bd',
            'roles' => ['ROLE_DDM_DIRECTOR_GENERAL'],
            'tier_level' => 'DIRECTORATE',
            'ancestry_path' => '/',
            'scope_id' => null,
        ]);

        $khulnaDrroToken = $this->createToken();

        $csdRiceBefore = (float) WarehouseInventory::where('warehouse_id', $this->nationalWarehouse->id)
            ->where('commodity_id', $this->rice->id)
            ->sum('current_quantity');
        $khulnaRiceBefore = (float) WarehouseInventory::where('warehouse_id', $this->khulnaWarehouse->id)
            ->where('commodity_id', $this->rice->id)
            ->sum('current_quantity');

        // 1. Request transfer from National CSD to Khulna Godown
        $reqRes = $this->withToken($nationalDirectorToken)->postJson('/api/v1/warehouse/transfers', [
            'source_warehouse_id' => $this->nationalWarehouse->id,
            'destination_warehouse_id' => $this->khulnaWarehouse->id,
            'requested_date' => now()->toDateString(),
            'expected_delivery_date' => now()->addDays(2)->toDateString(),
            'vehicle_info' => 'Truck DHK-MA-14-9001',
            'notes' => 'Cyclone readiness stock transfer',
            'items' => [
                ['commodity_id' => $this->rice->id, 'quantity_requested' => 20.00],
            ],
        ]);
        $reqRes->assertStatus(201);
        $transferId = $reqRes->json('data.id');
        $this->assertEquals(WarehouseTransfer::STATUS_REQUESTED, $reqRes->json('data.status'));

        // 2. Self-approval blocked by 4-eye SoD
        $this->withToken($nationalDirectorToken)->postJson("/api/v1/warehouse/transfers/{$transferId}/approve")
            ->assertStatus(403);

        // 3. DG approves transfer
        $this->withToken($dgToken)->postJson("/api/v1/warehouse/transfers/{$transferId}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', WarehouseTransfer::STATUS_APPROVED);

        // 4. Dispatch from CSD deducts source stock
        $this->withToken($nationalDirectorToken)->postJson("/api/v1/warehouse/transfers/{$transferId}/dispatch", [
            'dispatch_reference' => 'WAYBILL-2026-501',
        ])->assertOk()->assertJsonPath('data.status', WarehouseTransfer::STATUS_DISPATCHED);

        $csdRiceAfterDispatch = (float) WarehouseInventory::where('warehouse_id', $this->nationalWarehouse->id)
            ->where('commodity_id', $this->rice->id)
            ->sum('current_quantity');
        $this->assertEquals($csdRiceBefore - 20.00, $csdRiceAfterDispatch);

        // 5. Destination officer (Khulna DRRO) confirms receipt -> credits Khulna warehouse
        $this->withToken($khulnaDrroToken)->postJson("/api/v1/warehouse/transfers/{$transferId}/receive", [
            'notes' => 'All 20 MT bags intact at Boyra godown',
        ])->assertOk()->assertJsonPath('data.status', WarehouseTransfer::STATUS_RECEIVED);

        $khulnaRiceAfterReceive = (float) WarehouseInventory::where('warehouse_id', $this->khulnaWarehouse->id)
            ->where('commodity_id', $this->rice->id)
            ->sum('current_quantity');
        $this->assertEquals($khulnaRiceBefore + 20.00, $khulnaRiceAfterReceive);

        // Ledger has both -20 TRANSFER_OUT and +20 TRANSFER_IN rows
        $txRows = StockTransaction::where('warehouse_transfer_id', $transferId)->get();
        $this->assertCount(2, $txRows);
        $this->assertEquals([-20.0, 20.0], $txRows->pluck('quantity')->sort()->values()->all());
    }

    public function test_relief_allocation_stock_issue_prevents_double_deduction_and_enforces_writeoff_dual_auth(): void
    {
        $drroToken = $this->createToken();

        // Sync creator + second DRRO officer in database so dual-auth user lookup works
        $this->withToken($drroToken)->postJson('/api/v1/auth/sync')->assertOk();
        $coApproverToken = $this->createToken([
            'sub' => 'f7777777-7777-4777-8777-777777777777',
            'preferred_username' => 'dc_khulna',
            'name' => 'Deputy Commissioner Khulna',
            'email' => 'dc.khulna@modmr.gov.bd',
            'roles' => ['ROLE_DEPUTY_COMMISSIONER'],
        ]);
        $coSyncRes = $this->withToken($coApproverToken)->postJson('/api/v1/auth/sync')->assertOk();
        $dcUserId = $coSyncRes->json('user.id');
        $drroUserId = \App\Core\Models\User::where('email', 'drro.khulna@modmr.gov.bd')->firstOrFail()->id;

        // Create a SANCTIONED relief allocation backed by Khulna Warehouse
        $allocation = ReliefAllocation::create([
            'disaster_event_id' => $this->disaster->id,
            'administrative_unit_id' => $this->dacopeUpazila->id,
            'warehouse_id' => $this->khulnaWarehouse->id,
            'allocation_order_no' => 'ALLOC-WH-TEST-01',
            'status' => ReliefAllocation::STATUS_SANCTIONED,
            'created_by' => $drroUserId,
            'approved_by' => $dcUserId,
            'approved_at' => now(),
        ]);

        $allocItem = ReliefAllocationItem::create([
            'relief_allocation_id' => $allocation->id,
            'commodity_id' => $this->rice->id,
            'quantity_allocated' => 15.00,
            'quantity_distributed' => 0.00,
            'quantity_cancelled' => 0.00,
        ]);

        // Reserve the 15 MT on inventory lot just like Module 04 approveAllocation does
        app(\App\Modules\Warehouse\Services\InventoryLedgerService::class)
            ->reserveStock($this->khulnaWarehouse->id, $this->rice->id, 15.00);

        $physicalBefore = (float) WarehouseInventory::where('warehouse_id', $this->khulnaWarehouse->id)
            ->where('commodity_id', $this->rice->id)
            ->sum('current_quantity');
        $reservedBefore = (float) WarehouseInventory::where('warehouse_id', $this->khulnaWarehouse->id)
            ->where('commodity_id', $this->rice->id)
            ->sum('reserved_quantity');

        // Issue 10 MT against allocation -> physical & reserved both decrease by 10, allocation goes DISPATCHED
        $issueRes = $this->withToken($drroToken)->postJson("/api/v1/warehouse/relief-allocations/{$allocation->id}/issue", [
            'reference_voucher_no' => 'ISSUE-KHL-001',
            'notes' => 'Partial release to Dacope field team',
            'items' => [
                ['relief_allocation_item_id' => $allocItem->id, 'quantity_to_issue' => 10.00],
            ],
        ]);
        $issueRes->assertOk();
        $this->assertEquals(ReliefAllocation::STATUS_DISPATCHED, $issueRes->json('data.status'));

        $this->assertEquals(
            $physicalBefore - 10.00,
            (float) WarehouseInventory::where('warehouse_id', $this->khulnaWarehouse->id)->where('commodity_id', $this->rice->id)->sum('current_quantity')
        );
        $this->assertEquals(
            $reservedBefore - 10.00,
            (float) WarehouseInventory::where('warehouse_id', $this->khulnaWarehouse->id)->where('commodity_id', $this->rice->id)->sum('reserved_quantity')
        );

        // Issuing 8 MT (exceeding remaining 5 MT) MUST fail to prevent double-deduction
        $this->withToken($drroToken)->postJson("/api/v1/warehouse/relief-allocations/{$allocation->id}/issue", [
            'items' => [
                ['relief_allocation_item_id' => $allocItem->id, 'quantity_to_issue' => 8.00],
            ],
        ])->assertStatus(422);

        // Issuing remaining 5 MT completes the allocation
        $completeRes = $this->withToken($drroToken)->postJson("/api/v1/warehouse/relief-allocations/{$allocation->id}/issue", [
            'items' => [
                ['relief_allocation_item_id' => $allocItem->id, 'quantity_to_issue' => 5.00],
            ],
        ]);
        $completeRes->assertOk();
        $this->assertEquals(ReliefAllocation::STATUS_COMPLETED, $completeRes->json('data.status'));

        // Dual-auth write-off without approved_by must be denied (403)
        $khulnaLot = WarehouseInventory::where('warehouse_id', $this->khulnaWarehouse->id)
            ->where('commodity_id', $this->ors->id)
            ->firstOrFail();

        $this->withToken($drroToken)->postJson('/api/v1/warehouse/transactions/adjustment', [
            'warehouse_id' => $this->khulnaWarehouse->id,
            'commodity_id' => $this->ors->id,
            'batch_number' => $khulnaLot->batch_number,
            'transaction_type' => StockTransaction::TYPE_WRITE_OFF_DAMAGED,
            'quantity' => 5.00,
            'reference_voucher_no' => 'WO-FAIL-NO-COAUTH',
            'reason_code' => 'CARTON_WATER_DAMAGE',
            'notes' => 'Carton damaged in rain',
        ])->assertStatus(403);

        // With DC as co-authorizer, write-off succeeds
        $woRes = $this->withToken($drroToken)->postJson('/api/v1/warehouse/transactions/adjustment', [
            'warehouse_id' => $this->khulnaWarehouse->id,
            'commodity_id' => $this->ors->id,
            'batch_number' => $khulnaLot->batch_number,
            'transaction_type' => StockTransaction::TYPE_WRITE_OFF_DAMAGED,
            'quantity' => 5.00,
            'reference_voucher_no' => 'WO-PASS-001',
            'reason_code' => 'CARTON_WATER_DAMAGE',
            'notes' => 'Verified damaged by DRRO and DC',
            'approved_by' => $dcUserId,
        ]);
        $woRes->assertStatus(201);
        $this->assertEquals(-5.00, $woRes->json('data.quantity'));
    }

    public function test_dashboard_alerts_reports_geojson_and_csv_ledger_export(): void
    {
        $drroToken = $this->createToken();

        $lookupsRes = $this->withToken($drroToken)->getJson('/api/v1/warehouse/lookups');
        $lookupsRes->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'warehouses',
                    'commodities',
                    'administrative_units' => [['id', 'code', 'name_en', 'name_bn', 'tier_level']],
                    'co_authorizers',
                ],
            ]);

        $this->withToken($drroToken)->getJson('/api/v1/warehouse/dashboard')
            ->assertOk()
            ->assertJsonStructure(['success', 'data' => ['kpis', 'alerts_summary', 'recent_transactions']]);

        $alertsRes = $this->withToken($drroToken)->getJson('/api/v1/warehouse/alerts?days=30');
        $alertsRes->assertOk();
        $this->assertGreaterThanOrEqual(1, $alertsRes->json('data.summary.expiring_soon_count'));

        $this->withToken($drroToken)->getJson('/api/v1/warehouse/gis/geojson')
            ->assertOk()
            ->assertJsonPath('data.type', 'FeatureCollection');

        foreach ([
            'STOCK_BALANCE',
            'STOCK_MOVEMENT',
            'WAREHOUSE_INVENTORY',
            'ITEM_INVENTORY',
            'EXPIRY',
            'TRANSACTION_HISTORY',
        ] as $reportType) {
            $this->withToken($drroToken)->getJson("/api/v1/warehouse/reports?type={$reportType}")
                ->assertOk()
                ->assertJsonPath('report_type', $reportType);
        }

        $csvRes = $this->withToken($drroToken)->get('/api/v1/warehouse/export-ledger');
        $csvRes->assertOk();
        $this->assertStringContainsString('text/csv', (string) $csvRes->headers->get('Content-Type'));
        $this->assertStringContainsString('Transaction ID', $csvRes->getContent());
    }
}
