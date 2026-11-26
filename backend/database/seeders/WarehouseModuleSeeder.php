<?php

namespace Database\Seeders;

use App\Core\Models\User;
use App\Modules\Warehouse\Models\Commodity;
use App\Modules\Warehouse\Models\StockReceipt;
use App\Modules\Warehouse\Models\StockReceiptItem;
use App\Modules\Warehouse\Models\StockTransaction;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Warehouse\Models\WarehouseInventory;
use App\Modules\Warehouse\Models\WarehouseTransfer;
use App\Modules\Warehouse\Models\WarehouseTransferItem;
use Illuminate\Database\Seeder;

class WarehouseModuleSeeder extends Seeder
{
    public function run(): void
    {
        $systemUser = User::query()->where('role_code', 'ROLE_DDM_DIRECTOR_GENERAL')->first()
            ?? User::query()->where('role_code', 'ROLE_DDM_RELIEF_DIRECTOR')->first()
            ?? User::query()->first();

        // 1. Enrich commodities with descriptions and minimum_stock_level thresholds
        $commodityEnhancements = [
            'COM-RICE-GR' => [
                'description' => 'Fortified parboiled GR relief rice packed in 50 kg moisture-resistant woven bags.',
                'minimum_stock_level' => 50.00,
            ],
            'COM-DRY-FOOD' => [
                'description' => 'Sealed 5 kg dry food emergency ration (flattened rice, molasses, biscuit, salt).',
                'minimum_stock_level' => 300.00,
            ],
            'COM-WATER-5L' => [
                'description' => 'Food-grade sealed 5-liter drinking water jerrycan for flood-affected households.',
                'minimum_stock_level' => 250.00,
            ],
            'COM-CASH-GR' => [
                'description' => 'Gratuitous Relief (GR) cash disbursement unit tracked in BDT.',
                'minimum_stock_level' => 100000.00,
            ],
            'COM-BLANKET-STD' => [
                'description' => 'Heavyweight wool-blend thermal winter relief blanket.',
                'minimum_stock_level' => 150.00,
            ],
            'COM-CI-BUNDLE' => [
                'description' => 'Standard 72-foot corrugated iron roofing sheet bundle for emergency housing recovery.',
                'minimum_stock_level' => 40.00,
            ],
            'COM-ORS-KIT' => [
                'description' => 'WHO-formula oral rehydration salt and chlorine water purification tablet kit.',
                'minimum_stock_level' => 200.00,
            ],
        ];

        foreach ($commodityEnhancements as $code => $attrs) {
            Commodity::query()
                ->where('commodity_code', $code)
                ->update([
                    'description' => $attrs['description'],
                    'minimum_stock_level' => $attrs['minimum_stock_level'],
                    'updated_by' => $systemUser?->id,
                ]);
        }

        // 2. Enrich existing seeded warehouses with MoDMR metadata
        $warehouseMeta = [
            'WH-NAT-CSD-01' => [
                'warehouse_type' => Warehouse::TYPE_RELIEF_GODOWN,
                'owning_organization' => 'Department of Disaster Management (DDM)',
                'responsible_officer_name' => 'Md. Rafiqul Islam, Deputy Director (Logistics)',
                'responsible_officer_contact' => '+8801711-000101',
                'address' => 'Tejgaon Central Storage Depot, 92-93 Mohakhali C/A, Dhaka-1212',
                'contact_phone' => '+8802-222284101',
                'contact_email' => 'csd.dhaka@modmr.gov.bd',
            ],
            'WH-KHL-GODOWN-01' => [
                'warehouse_type' => Warehouse::TYPE_EMERGENCY_HUB,
                'owning_organization' => 'DRRO Office, Khulna',
                'responsible_officer_name' => 'Kazi Farhana Akter, DRRO Khulna',
                'responsible_officer_contact' => '+8801711-000202',
                'address' => 'Boyra Relief Godown Complex, Khulna Sadar, Khulna-9000',
                'contact_phone' => '+88041-761202',
                'contact_email' => 'godown.khulna@modmr.gov.bd',
            ],
            'WH-DAC-STORE-01' => [
                'warehouse_type' => Warehouse::TYPE_RELIEF_GODOWN,
                'owning_organization' => 'PIO Office, Savar Upazila',
                'responsible_officer_name' => 'Tariqul Hasan, Project Implementation Officer',
                'responsible_officer_contact' => '+8801711-000303',
                'address' => 'Savar Upazila Parishad Relief Store, Dhaka-1340',
                'contact_phone' => '+8802-7743103',
                'contact_email' => 'store.savar@modmr.gov.bd',
            ],
        ];

        foreach ($warehouseMeta as $code => $meta) {
            Warehouse::query()
                ->where('warehouse_code', $code)
                ->update(array_merge($meta, [
                    'responsible_user_id' => $systemUser?->id,
                    'updated_by' => $systemUser?->id,
                ]));
        }

        // 3. Backfill opening_quantity & warehouse_id/commodity_id on existing seeded stock transactions
        WarehouseInventory::query()
            ->where('opening_quantity', '<=', 0)
            ->chunkById(100, function ($lots): void {
                foreach ($lots as $lot) {
                    $lot->opening_quantity = $lot->current_quantity;
                    $lot->status = $lot->current_quantity > 0 ? WarehouseInventory::STATUS_ACTIVE : WarehouseInventory::STATUS_EXHAUSTED;
                    $lot->save();
                }
            });

        // 4. Add an expiring-soon lot and a low-stock commodity batch in Khulna to populate real alert panels
        $khulnaWh = Warehouse::query()->where('warehouse_code', 'WH-KHL-GODOWN-01')->first();
        $nationalWh = Warehouse::query()->where('warehouse_code', 'WH-NAT-CSD-01')->first();
        $ors = Commodity::query()->where('commodity_code', 'COM-ORS-KIT')->first();
        $dryFood = Commodity::query()->where('commodity_code', 'COM-DRY-FOOD')->first();
        $rice = Commodity::query()->where('commodity_code', 'COM-RICE-GR')->first();

        if ($khulnaWh && $ors) {
            $orsLot = WarehouseInventory::query()->firstOrCreate(
                [
                    'warehouse_id' => $khulnaWh->id,
                    'commodity_id' => $ors->id,
                    'batch_number' => 'LOT-KHL-ORS-2026-01',
                ],
                [
                    'opening_quantity' => 85.00,
                    'current_quantity' => 85.00,
                    'reserved_quantity' => 0.00,
                    'damaged_quantity' => 0.00,
                    'expired_quantity' => 0.00,
                    'unit_cost_bdt' => 45.00,
                    'received_date' => now()->subDays(45)->toDateString(),
                    'expiry_date' => now()->addDays(18)->toDateString(),
                    'status' => WarehouseInventory::STATUS_ACTIVE,
                    'notes' => 'Coastal emergency ORS & purification tablets (near expiry threshold).',
                ]
            );

            if ($systemUser && ! StockTransaction::query()->where('idempotency_key', 'SEED-OPENING-KHL-ORS-01')->exists()) {
                StockTransaction::create([
                    'warehouse_inventory_id' => $orsLot->id,
                    'warehouse_id' => $khulnaWh->id,
                    'commodity_id' => $ors->id,
                    'transaction_type' => StockTransaction::TYPE_OPENING_STOCK,
                    'reference_type' => 'OPENING_BALANCE',
                    'quantity' => 85.00,
                    'balance_after' => 85.00,
                    'reference_voucher_no' => 'OPEN-KHL-ORS-2026-01',
                    'idempotency_key' => 'SEED-OPENING-KHL-ORS-01',
                    'notes' => 'Seeded opening balance for Khulna Emergency Hub',
                    'authorized_by' => $systemUser->id,
                    'created_by' => $systemUser->id,
                ]);
            }
        }

        if (! $systemUser) {
            return;
        }

        // 5. Seed a verified GRN StockReceipt
        if ($nationalWh && $dryFood) {
            $grn = StockReceipt::query()->firstOrCreate(
                ['receipt_no' => 'GRN-2026-CSD-0001'],
                [
                    'warehouse_id' => $nationalWh->id,
                    'supplier_name' => 'Directorate General of Food Procurement Wing',
                    'supplier_contact' => '+8802-9550112',
                    'purchase_order_no' => 'PO-MODMR-2026-104',
                    'received_date' => now()->subDays(10)->toDateString(),
                    'status' => StockReceipt::STATUS_VERIFIED,
                    'verification_notes' => 'Verified weight and moisture test report upon CSD arrival.',
                    'idempotency_key' => 'SEED-GRN-2026-CSD-0001',
                    'received_by' => $systemUser->id,
                    'verified_by' => $systemUser->id,
                    'verified_at' => now()->subDays(10),
                    'notes' => 'Pre-monsoon dry food buffer procurement batch.',
                ]
            );

            $dryLot = WarehouseInventory::query()
                ->where('warehouse_id', $nationalWh->id)
                ->where('commodity_id', $dryFood->id)
                ->first();

            StockReceiptItem::query()->firstOrCreate(
                [
                    'stock_receipt_id' => $grn->id,
                    'commodity_id' => $dryFood->id,
                    'batch_number' => $dryLot?->batch_number ?? 'LOT-CSD-DF-2026-01',
                ],
                [
                    'quantity_received' => 500.00,
                    'unit_cost_bdt' => 420.00,
                    'expiry_date' => now()->addMonths(6)->toDateString(),
                    'warehouse_inventory_id' => $dryLot?->id,
                    'notes' => 'Standard 5 kg emergency dry food pack',
                ]
            );
        }

        // 6. Seed a sample requested WarehouseTransfer awaiting approval
        if ($nationalWh && $khulnaWh && $rice) {
            $transfer = WarehouseTransfer::query()->firstOrCreate(
                ['transfer_no' => 'TRF-2026-0001'],
                [
                    'source_warehouse_id' => $nationalWh->id,
                    'destination_warehouse_id' => $khulnaWh->id,
                    'status' => WarehouseTransfer::STATUS_REQUESTED,
                    'requested_date' => now()->toDateString(),
                    'expected_delivery_date' => now()->addDays(2)->toDateString(),
                    'vehicle_info' => 'DDM Logistics Convoy Truck #DHK-TA-11-4821',
                    'idempotency_key' => 'SEED-TRF-2026-0001',
                    'requested_by' => $systemUser->id,
                    'notes' => 'Coastal buffer replenishment from Tejgaon CSD to Khulna Emergency Hub.',
                ]
            );

            WarehouseTransferItem::query()->firstOrCreate(
                [
                    'warehouse_transfer_id' => $transfer->id,
                    'commodity_id' => $rice->id,
                ],
                [
                    'quantity_requested' => 25.00,
                    'quantity_dispatched' => 0.00,
                    'quantity_received' => 0.00,
                    'unit_cost_bdt' => 48000.00,
                    'notes' => '25 MT parboiled GR rice replenishment',
                ]
            );
        }
    }
}
