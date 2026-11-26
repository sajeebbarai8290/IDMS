<?php

namespace Tests\Unit\Warehouse;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Modules\Warehouse\Models\Commodity;
use App\Modules\Warehouse\Models\StockTransaction;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Warehouse\Services\InventoryLedgerService;
use Database\Seeders\AdministrativeUnitSeeder;
use Database\Seeders\ReliefCatalogAndWarehouseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class WarehouseLedgerImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    protected InventoryLedgerService $ledger;
    protected Warehouse $warehouse;
    protected Commodity $rice;
    protected User $officerA;
    protected User $officerB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AdministrativeUnitSeeder::class);
        $this->seed(ReliefCatalogAndWarehouseSeeder::class);

        $this->ledger = app(InventoryLedgerService::class);
        $this->warehouse = Warehouse::where('warehouse_code', 'WH-NAT-CSD-01')->firstOrFail();
        $this->rice = Commodity::where('commodity_code', 'COM-RICE-GR')->firstOrFail();

        $khulna = AdministrativeUnit::where('code', '47')->firstOrFail();

        $this->officerA = User::create([
            'keycloak_sub' => '00000000-0000-4000-8000-000000000101',
            'email' => 'wh1@modmr.gov.bd',
            'full_name_en' => 'Warehouse Manager 1',
            'role_code' => 'ROLE_WAREHOUSE_MANAGER',
            'administrative_unit_id' => $khulna->id,
            'is_active' => true,
        ]);

        $this->officerB = User::create([
            'keycloak_sub' => '00000000-0000-4000-8000-000000000102',
            'email' => 'drro2@modmr.gov.bd',
            'full_name_en' => 'DRRO Co-Authorizer',
            'role_code' => 'ROLE_DRRO',
            'administrative_unit_id' => $khulna->id,
            'is_active' => true,
        ]);
    }

    public function test_stock_transactions_are_strictly_immutable_on_update_and_delete(): void
    {
        $tx = $this->ledger->recordOpeningStock(
            $this->warehouse->id,
            $this->rice->id,
            'BATCH-UNIT-IMMUT-01',
            25.00,
            'OPEN-UNIT-001',
            $this->officerA,
            null,
            48000.00,
            'INITIAL_COUNT',
            'Opening count',
            'IDEM-UNIT-IMMUT-01'
        );

        $this->assertEquals(StockTransaction::TYPE_OPENING_STOCK, $tx->transaction_type);
        $this->assertEquals(25.00, (float) $tx->balance_after);

        // Idempotency key returns identical transaction without adding stock twice
        $replay = $this->ledger->recordOpeningStock(
            $this->warehouse->id,
            $this->rice->id,
            'BATCH-UNIT-IMMUT-01',
            25.00,
            'OPEN-UNIT-001',
            $this->officerA,
            null,
            48000.00,
            'INITIAL_COUNT',
            'Opening count',
            'IDEM-UNIT-IMMUT-01'
        );
        $this->assertEquals($tx->id, $replay->id);

        try {
            $tx->update(['quantity' => 999]);
            $this->fail('Expected LogicException when updating immutable StockTransaction.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('immutable', strtolower($e->getMessage()));
        }

        try {
            $tx->delete();
            $this->fail('Expected LogicException when deleting immutable StockTransaction.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('immutable', strtolower($e->getMessage()));
        }
    }

    public function test_adjustments_and_writeoffs_enforce_non_negative_and_unreserved_stock_invariants(): void
    {
        $this->ledger->receiveStock(
            $this->warehouse->id,
            $this->rice->id,
            'BATCH-UNIT-ADJ-01',
            40.00,
            'GRN-UNIT-ADJ-01',
            $this->officerA
        );

        // Attempting negative adjustment larger than batch balance fails
        try {
            $this->ledger->adjustStock(
                $this->warehouse->id,
                $this->rice->id,
                'BATCH-UNIT-ADJ-01',
                -45.00,
                'ADJ-FAIL-01',
                'AUDIT_RECOUNT',
                $this->officerA,
                $this->officerB,
                'Should fail'
            );
            $this->fail('Negative stock balance must throw ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('quantity', $e->errors());
        }

        // Valid write-off of 10 units succeeds and tracks damaged_quantity
        $writeoffTx = $this->ledger->markDamagedOrExpired(
            $this->warehouse->id,
            $this->rice->id,
            'BATCH-UNIT-ADJ-01',
            10.00,
            StockTransaction::TYPE_WRITE_OFF_DAMAGED,
            'WO-UNIT-01',
            'MOISTURE_DAMAGE',
            $this->officerA,
            $this->officerB,
            'Flooded corner bags'
        );

        $this->assertEquals(-10.00, (float) $writeoffTx->quantity);
        $this->assertEquals(30.00, (float) $writeoffTx->balance_after);
        $this->assertEquals($this->officerB->id, $writeoffTx->approved_by);

        $returnTx = $this->ledger->recordReturnStock(
            $this->warehouse->id,
            $this->rice->id,
            'BATCH-UNIT-ADJ-01',
            5.00,
            'RET-UNIT-01',
            'UNDISTRIBUTED_RETURN',
            $this->officerA,
            null,
            null,
            'Returned from camp'
        );

        $this->assertEquals(5.00, (float) $returnTx->quantity);
        $this->assertEquals(35.00, (float) $returnTx->balance_after);
    }
}
