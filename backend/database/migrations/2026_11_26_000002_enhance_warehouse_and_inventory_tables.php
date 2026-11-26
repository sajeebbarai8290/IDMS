<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';

        $usersTable = $isPgsql ? 'core.users' : 'users';
        $commoditiesTable = $isPgsql ? 'logistics.commodities' : 'commodities';
        $warehousesTable = $isPgsql ? 'logistics.warehouses' : 'warehouses';
        $inventoriesTable = $isPgsql ? 'logistics.warehouse_inventories' : 'warehouse_inventories';
        $stockTransactionsTable = $isPgsql ? 'logistics.stock_transactions' : 'stock_transactions';
        $stockReceiptsTable = $isPgsql ? 'logistics.stock_receipts' : 'stock_receipts';
        $stockReceiptItemsTable = $isPgsql ? 'logistics.stock_receipt_items' : 'stock_receipt_items';
        $warehouseTransfersTable = $isPgsql ? 'logistics.warehouse_transfers' : 'warehouse_transfers';
        $warehouseTransferItemsTable = $isPgsql ? 'logistics.warehouse_transfer_items' : 'warehouse_transfer_items';

        // 1. Extend commodities with description, minimum_stock_level, audit user links & soft deletes
        Schema::table($commoditiesTable, function (Blueprint $table) use ($usersTable) {
            $table->text('description')->nullable()->after('unit_of_measure');
            $table->decimal('minimum_stock_level', 12, 2)->default(0)->after('description');
            $table->uuid('created_by')->nullable()->after('is_active');
            $table->uuid('updated_by')->nullable()->after('created_by');
            $table->softDeletes();

            $table->foreign('created_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on($usersTable)->onDelete('set null');
        });

        if ($isPgsql) {
            DB::statement("ALTER TABLE {$commoditiesTable} ADD CONSTRAINT chk_commodities_min_stock_non_negative CHECK (minimum_stock_level >= 0);");
        }

        // 2. Extend warehouses with operational type, organization, officer, contact & audit fields
        Schema::table($warehousesTable, function (Blueprint $table) use ($usersTable) {
            $table->string('warehouse_type', 40)->default('RELIEF_GODOWN')->after('tier')->index();
            $table->string('owning_organization', 160)->nullable()->after('warehouse_type');
            $table->string('responsible_officer_name', 120)->nullable()->after('owning_organization');
            $table->string('responsible_officer_contact', 40)->nullable()->after('responsible_officer_name');
            $table->uuid('responsible_user_id')->nullable()->after('responsible_officer_contact')->index();
            $table->text('address')->nullable()->after('responsible_user_id');
            $table->string('contact_phone', 40)->nullable()->after('address');
            $table->string('contact_email', 150)->nullable()->after('contact_phone');
            $table->uuid('created_by')->nullable()->after('is_active');
            $table->uuid('updated_by')->nullable()->after('created_by');

            $table->foreign('responsible_user_id')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('created_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on($usersTable)->onDelete('set null');
        });

        // 3. Extend warehouse_inventories with opening, damaged, expired quantities, lot status & notes
        Schema::table($inventoriesTable, function (Blueprint $table) {
            $table->decimal('opening_quantity', 12, 2)->default(0)->after('batch_number');
            $table->decimal('damaged_quantity', 12, 2)->default(0)->after('reserved_quantity');
            $table->decimal('expired_quantity', 12, 2)->default(0)->after('damaged_quantity');
            $table->string('status', 30)->default('ACTIVE')->after('expiry_date')->index();
            $table->text('notes')->nullable()->after('status');
        });

        if ($isPgsql) {
            DB::statement("ALTER TABLE {$inventoriesTable} ADD CONSTRAINT chk_wh_inv_opening_non_negative CHECK (opening_quantity >= 0);");
            DB::statement("ALTER TABLE {$inventoriesTable} ADD CONSTRAINT chk_wh_inv_damaged_non_negative CHECK (damaged_quantity >= 0);");
            DB::statement("ALTER TABLE {$inventoriesTable} ADD CONSTRAINT chk_wh_inv_expired_non_negative CHECK (expired_quantity >= 0);");
        }

        // 4. Procurement & Receiving (GRN)
        Schema::create($stockReceiptsTable, function (Blueprint $table) use ($warehousesTable, $usersTable) {
            $table->uuid('id')->primary();
            $table->string('receipt_no', 60)->unique();
            $table->uuid('warehouse_id')->index();
            $table->string('supplier_name', 180);
            $table->string('supplier_contact', 80)->nullable();
            $table->string('purchase_order_no', 80)->nullable()->index();
            $table->date('received_date')->index();
            $table->string('status', 30)->default('PENDING_VERIFICATION')->index();
            $table->text('verification_notes')->nullable();
            $table->string('attachment_path', 255)->nullable();
            $table->string('attachment_original_name', 200)->nullable();
            $table->string('attachment_mime', 100)->nullable();
            $table->unsignedBigInteger('attachment_size_bytes')->nullable();
            $table->string('attachment_sha256', 64)->nullable();
            $table->string('idempotency_key', 120)->nullable()->unique();
            $table->uuid('received_by')->index();
            $table->uuid('verified_by')->nullable()->index();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('warehouse_id')->references('id')->on($warehousesTable)->onDelete('restrict');
            $table->foreign('received_by')->references('id')->on($usersTable)->onDelete('restrict');
            $table->foreign('verified_by')->references('id')->on($usersTable)->onDelete('set null');
        });

        Schema::create($stockReceiptItemsTable, function (Blueprint $table) use ($stockReceiptsTable, $commoditiesTable, $inventoriesTable) {
            $table->uuid('id')->primary();
            $table->uuid('stock_receipt_id')->index();
            $table->uuid('commodity_id')->index();
            $table->string('batch_number', 50)->index();
            $table->decimal('quantity_received', 12, 2);
            $table->decimal('unit_cost_bdt', 12, 2)->nullable();
            $table->date('expiry_date')->nullable();
            $table->uuid('warehouse_inventory_id')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('stock_receipt_id')->references('id')->on($stockReceiptsTable)->onDelete('cascade');
            $table->foreign('commodity_id')->references('id')->on($commoditiesTable)->onDelete('restrict');
            $table->foreign('warehouse_inventory_id')->references('id')->on($inventoriesTable)->onDelete('set null');
        });

        if ($isPgsql) {
            DB::statement("ALTER TABLE {$stockReceiptItemsTable} ADD CONSTRAINT chk_grn_item_qty_positive CHECK (quantity_received > 0);");
        }

        // 5. Inter-Warehouse Transfers
        Schema::create($warehouseTransfersTable, function (Blueprint $table) use ($warehousesTable, $usersTable) {
            $table->uuid('id')->primary();
            $table->string('transfer_no', 60)->unique();
            $table->uuid('source_warehouse_id')->index();
            $table->uuid('destination_warehouse_id')->index();
            $table->string('status', 25)->default('REQUESTED')->index();
            $table->date('requested_date')->index();
            $table->date('expected_delivery_date')->nullable();
            $table->string('vehicle_info', 180)->nullable();
            $table->string('dispatch_reference', 100)->nullable();
            $table->string('idempotency_key', 120)->nullable()->unique();
            $table->uuid('requested_by')->index();
            $table->uuid('approved_by')->nullable()->index();
            $table->timestamp('approved_at')->nullable();
            $table->uuid('dispatched_by')->nullable()->index();
            $table->timestamp('dispatched_at')->nullable();
            $table->uuid('received_by')->nullable()->index();
            $table->timestamp('received_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('source_warehouse_id')->references('id')->on($warehousesTable)->onDelete('restrict');
            $table->foreign('destination_warehouse_id')->references('id')->on($warehousesTable)->onDelete('restrict');
            $table->foreign('requested_by')->references('id')->on($usersTable)->onDelete('restrict');
            $table->foreign('approved_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('dispatched_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('received_by')->references('id')->on($usersTable)->onDelete('set null');
        });

        Schema::create($warehouseTransferItemsTable, function (Blueprint $table) use ($warehouseTransfersTable, $commoditiesTable) {
            $table->uuid('id')->primary();
            $table->uuid('warehouse_transfer_id')->index();
            $table->uuid('commodity_id')->index();
            $table->string('batch_number', 50)->nullable();
            $table->decimal('quantity_requested', 12, 2);
            $table->decimal('quantity_dispatched', 12, 2)->default(0);
            $table->decimal('quantity_received', 12, 2)->default(0);
            $table->decimal('unit_cost_bdt', 12, 2)->nullable();
            $table->date('expiry_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('warehouse_transfer_id')->references('id')->on($warehouseTransfersTable)->onDelete('cascade');
            $table->foreign('commodity_id')->references('id')->on($commoditiesTable)->onDelete('restrict');
        });

        if ($isPgsql) {
            DB::statement("ALTER TABLE {$warehouseTransferItemsTable} ADD CONSTRAINT chk_transfer_item_qty_positive CHECK (quantity_requested > 0);");
        }

        // 6. Extend immutable stock_transactions ledger with idempotency, direct warehouse/commodity refs, receipt/transfer FKs, and dual-auth fields
        Schema::table($stockTransactionsTable, function (Blueprint $table) use (
            $warehousesTable,
            $commoditiesTable,
            $stockReceiptsTable,
            $warehouseTransfersTable,
            $usersTable
        ) {
            $table->uuid('warehouse_id')->nullable()->after('warehouse_inventory_id')->index();
            $table->uuid('commodity_id')->nullable()->after('warehouse_id')->index();
            $table->uuid('stock_receipt_id')->nullable()->after('commodity_id')->index();
            $table->uuid('warehouse_transfer_id')->nullable()->after('stock_receipt_id')->index();
            $table->string('reference_type', 40)->nullable()->after('transaction_type')->index();
            $table->string('reason_code', 60)->nullable()->after('reference_type');
            $table->string('idempotency_key', 120)->nullable()->unique()->after('reference_voucher_no');
            $table->uuid('approved_by')->nullable()->after('authorized_by')->index();
            $table->uuid('created_by')->nullable()->after('approved_by');

            $table->foreign('warehouse_id')->references('id')->on($warehousesTable)->onDelete('restrict');
            $table->foreign('commodity_id')->references('id')->on($commoditiesTable)->onDelete('restrict');
            $table->foreign('stock_receipt_id')->references('id')->on($stockReceiptsTable)->onDelete('set null');
            $table->foreign('warehouse_transfer_id')->references('id')->on($warehouseTransfersTable)->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('created_by')->references('id')->on($usersTable)->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';

        $commoditiesTable = $isPgsql ? 'logistics.commodities' : 'commodities';
        $warehousesTable = $isPgsql ? 'logistics.warehouses' : 'warehouses';
        $inventoriesTable = $isPgsql ? 'logistics.warehouse_inventories' : 'warehouse_inventories';
        $stockTransactionsTable = $isPgsql ? 'logistics.stock_transactions' : 'stock_transactions';
        $stockReceiptsTable = $isPgsql ? 'logistics.stock_receipts' : 'stock_receipts';
        $stockReceiptItemsTable = $isPgsql ? 'logistics.stock_receipt_items' : 'stock_receipt_items';
        $warehouseTransfersTable = $isPgsql ? 'logistics.warehouse_transfers' : 'warehouse_transfers';
        $warehouseTransferItemsTable = $isPgsql ? 'logistics.warehouse_transfer_items' : 'warehouse_transfer_items';

        Schema::table($stockTransactionsTable, function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropForeign(['commodity_id']);
            $table->dropForeign(['stock_receipt_id']);
            $table->dropForeign(['warehouse_transfer_id']);
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['created_by']);
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn([
                'warehouse_id',
                'commodity_id',
                'stock_receipt_id',
                'warehouse_transfer_id',
                'reference_type',
                'reason_code',
                'idempotency_key',
                'approved_by',
                'created_by',
            ]);
        });

        Schema::dropIfExists($warehouseTransferItemsTable);
        Schema::dropIfExists($warehouseTransfersTable);
        Schema::dropIfExists($stockReceiptItemsTable);
        Schema::dropIfExists($stockReceiptsTable);

        if ($isPgsql) {
            DB::statement("ALTER TABLE {$inventoriesTable} DROP CONSTRAINT IF EXISTS chk_wh_inv_opening_non_negative;");
            DB::statement("ALTER TABLE {$inventoriesTable} DROP CONSTRAINT IF EXISTS chk_wh_inv_damaged_non_negative;");
            DB::statement("ALTER TABLE {$inventoriesTable} DROP CONSTRAINT IF EXISTS chk_wh_inv_expired_non_negative;");
        }

        Schema::table($inventoriesTable, function (Blueprint $table) {
            $table->dropColumn(['opening_quantity', 'damaged_quantity', 'expired_quantity', 'status', 'notes']);
        });

        Schema::table($warehousesTable, function (Blueprint $table) {
            $table->dropForeign(['responsible_user_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropColumn([
                'warehouse_type',
                'owning_organization',
                'responsible_officer_name',
                'responsible_officer_contact',
                'responsible_user_id',
                'address',
                'contact_phone',
                'contact_email',
                'created_by',
                'updated_by',
            ]);
        });

        if ($isPgsql) {
            DB::statement("ALTER TABLE {$commoditiesTable} DROP CONSTRAINT IF EXISTS chk_commodities_min_stock_non_negative;");
        }

        Schema::table($commoditiesTable, function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropSoftDeletes();
            $table->dropColumn(['description', 'minimum_stock_level', 'created_by', 'updated_by']);
        });
    }
};
