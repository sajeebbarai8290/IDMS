<?php

namespace Tests\Unit\Relief;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Modules\Beneficiary\Models\Household;
use App\Modules\Disaster\Models\DisasterEvent;
use App\Modules\Relief\Models\DistributionPoint;
use App\Modules\Relief\Models\DistributionVoucher;
use App\Modules\Relief\Models\ReliefAllocation;
use App\Modules\Relief\Models\ReliefAllocationItem;
use App\Modules\Warehouse\Models\Commodity;
use App\Modules\Warehouse\Models\StockTransaction;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Warehouse\Models\WarehouseInventory;
use App\Modules\Warehouse\Services\InventoryLedgerService;
use Database\Seeders\AdministrativeUnitSeeder;
use Database\Seeders\DisasterEventSeeder;
use Database\Seeders\ReliefCatalogAndWarehouseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected InventoryLedgerService $ledger;
    protected User $actor;
    protected Warehouse $warehouse;
    protected Commodity $commodity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AdministrativeUnitSeeder::class);
        $this->seed(DisasterEventSeeder::class);
        $this->seed(ReliefCatalogAndWarehouseSeeder::class);

        $this->ledger = app(InventoryLedgerService::class);
        $dacope = AdministrativeUnit::where('code', '12')->firstOrFail();

        $this->actor = User::create([
            'id' => (string) Str::uuid(),
            'keycloak_sub' => (string) Str::uuid(),
            'full_name_en' => 'Warehouse Manager Dacope',
            'full_name_bn' => 'গুদাম ব্যবস্থাপক দাকোপ',
            'email' => 'wh.dacope@modmr.gov.bd',
            'role_code' => 'ROLE_WAREHOUSE_MANAGER',
            'administrative_unit_id' => $dacope->id,
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::where('warehouse_code', 'WH-DAC-STORE-01')->firstOrFail();
        $this->commodity = Commodity::where('commodity_code', 'COM-DRY-FOOD')->firstOrFail();

        // Reset existing seeded inventory for deterministic FEFO test
        WarehouseInventory::where('warehouse_id', $this->warehouse->id)
            ->where('commodity_id', $this->commodity->id)
            ->delete();
    }

    public function test_fefo_deducts_soonest_expiring_batch_first_and_logs_immutable_transactions(): void
    {
        $batchLater = WarehouseInventory::create([
            'warehouse_id' => $this->warehouse->id,
            'commodity_id' => $this->commodity->id,
            'batch_number' => 'LOT-LATER',
            'current_quantity' => 50,
            'reserved_quantity' => 0,
            'received_date' => now()->subDays(20)->toDateString(),
            'expiry_date' => now()->addMonths(6)->toDateString(),
        ]);

        $batchSooner = WarehouseInventory::create([
            'warehouse_id' => $this->warehouse->id,
            'commodity_id' => $this->commodity->id,
            'batch_number' => 'LOT-SOONER',
            'current_quantity' => 20,
            'reserved_quantity' => 0,
            'received_date' => now()->subDays(5)->toDateString(),
            'expiry_date' => now()->addDays(15)->toDateString(),
        ]);

        $txs = $this->ledger->deductStock(
            $this->warehouse->id,
            $this->commodity->id,
            30,
            'VCH-FEFO-001',
            $this->actor
        );

        $this->assertCount(2, $txs);
        $this->assertSame(0.0, (float) $batchSooner->fresh()->current_quantity);
        $this->assertSame(40.0, (float) $batchLater->fresh()->current_quantity);
        $this->assertSame(-20.0, (float) $txs[0]->quantity);
        $this->assertSame(0.0, (float) $txs[0]->balance_after);
        $this->assertSame(-10.0, (float) $txs[1]->quantity);
        $this->assertSame(40.0, (float) $txs[1]->balance_after);
    }

    public function test_negative_stock_is_strictly_prohibited(): void
    {
        $this->ledger->receiveStock(
            $this->warehouse->id,
            $this->commodity->id,
            'LOT-ONLY-10',
            10,
            'GRN-TEST-01',
            $this->actor
        );

        $this->expectException(ValidationException::class);

        $this->ledger->deductStock(
            $this->warehouse->id,
            $this->commodity->id,
            15,
            'VCH-OVERDRAW-001',
            $this->actor
        );
    }

    public function test_reserve_release_and_void_reversal_maintain_exact_balances(): void
    {
        $this->ledger->receiveStock(
            $this->warehouse->id,
            $this->commodity->id,
            'LOT-RES-01',
            100,
            'GRN-RES-01',
            $this->actor
        );

        $this->ledger->reserveStock($this->warehouse->id, $this->commodity->id, 60);
        $this->assertSame(40.0, $this->ledger->getUnreservedStock($this->warehouse->id, $this->commodity->id));
        $this->assertSame(100.0, $this->ledger->getPhysicalStock($this->warehouse->id, $this->commodity->id));

        $disaster = DisasterEvent::firstOrFail();
        $point = DistributionPoint::firstOrFail();
        $union = AdministrativeUnit::where('code', '35')->firstOrFail();

        $hh = Household::create([
            'id' => (string) Str::uuid(),
            'household_code' => 'HH-UNIT-001',
            'administrative_unit_id' => $union->id,
            'hoh_name_en' => 'Karim Uddin',
            'hoh_name_bn' => 'করিম উদ্দিন',
            'hoh_nid_hash' => hash('sha256', '1990123456789'),
            'hoh_nid_encrypted' => Crypt::encryptString('1990123456789'),
            'hoh_nid_masked' => '1990******789',
            'verification_status' => 'APPROVED',
            'vulnerability_score' => 80,
            'created_by' => $this->actor->id,
        ]);

        $alloc = ReliefAllocation::create([
            'disaster_event_id' => $disaster->id,
            'administrative_unit_id' => $union->id,
            'distribution_point_id' => $point->id,
            'warehouse_id' => $this->warehouse->id,
            'allocation_order_no' => 'ALLOC-UNIT-01',
            'status' => ReliefAllocation::STATUS_SANCTIONED,
            'created_by' => $this->actor->id,
        ]);

        $allocItem = ReliefAllocationItem::create([
            'relief_allocation_id' => $alloc->id,
            'commodity_id' => $this->commodity->id,
            'quantity_allocated' => 60,
        ]);

        $voucher = DistributionVoucher::create([
            'relief_allocation_item_id' => $allocItem->id,
            'household_id' => $hh->id,
            'distribution_point_id' => $point->id,
            'voucher_no' => 'VCH-UNIT-01',
            'idempotency_key' => (string) Str::uuid(),
            'quantity_disbursed' => 25,
            'verification_method' => 'SIGNATURE_MANUAL',
            'distributed_by' => $this->actor->id,
            'distributed_at' => now(),
        ]);

        $this->ledger->deductStock(
            $this->warehouse->id,
            $this->commodity->id,
            25,
            $voucher->voucher_no,
            $this->actor,
            $allocItem->id,
            $voucher->id
        );

        $this->assertSame(75.0, $this->ledger->getPhysicalStock($this->warehouse->id, $this->commodity->id));
        $this->assertSame(40.0, $this->ledger->getUnreservedStock($this->warehouse->id, $this->commodity->id));

        // Reverse deduction on void
        $reversals = $this->ledger->reverseDistributionDeduction($voucher, $this->actor, 'Duplicate entry');
        $this->assertCount(1, $reversals);
        $this->assertSame(StockTransaction::TYPE_ALLOCATION_RETURN, $reversals[0]->transaction_type);
        $this->assertSame(100.0, $this->ledger->getPhysicalStock($this->warehouse->id, $this->commodity->id));

        $this->ledger->releaseReservation($this->warehouse->id, $this->commodity->id, 60);
        $this->assertSame(100.0, $this->ledger->getUnreservedStock($this->warehouse->id, $this->commodity->id));
    }
}
