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

        if ($isPgsql) {
            DB::statement('CREATE SCHEMA IF NOT EXISTS logistics;');
            DB::statement('CREATE SCHEMA IF NOT EXISTS distribution;');
        }

        $usersTable = $isPgsql ? 'core.users' : 'users';
        $adminUnitsTable = $isPgsql ? 'core.administrative_units' : 'administrative_units';
        $disasterEventsTable = $isPgsql ? 'disaster.disaster_events' : 'disaster_events';
        $householdsTable = $isPgsql ? 'beneficiary.households' : 'households';

        $categoriesTable = $isPgsql ? 'logistics.relief_categories' : 'relief_categories';
        $commoditiesTable = $isPgsql ? 'logistics.commodities' : 'commodities';
        $warehousesTable = $isPgsql ? 'logistics.warehouses' : 'warehouses';
        $inventoriesTable = $isPgsql ? 'logistics.warehouse_inventories' : 'warehouse_inventories';
        $requisitionsTable = $isPgsql ? 'logistics.relief_requisitions' : 'relief_requisitions';
        $requisitionItemsTable = $isPgsql ? 'logistics.relief_requisition_items' : 'relief_requisition_items';
        $allocationsTable = $isPgsql ? 'logistics.relief_allocations' : 'relief_allocations';
        $allocationItemsTable = $isPgsql ? 'logistics.relief_allocation_items' : 'relief_allocation_items';
        $allocationBeneficiariesTable = $isPgsql ? 'logistics.relief_allocation_beneficiaries' : 'relief_allocation_beneficiaries';
        $distributionPointsTable = $isPgsql ? 'distribution.distribution_points' : 'distribution_points';
        $distributionVouchersTable = $isPgsql ? 'distribution.distribution_vouchers' : 'distribution_vouchers';
        $stockTransactionsTable = $isPgsql ? 'logistics.stock_transactions' : 'stock_transactions';

        // 1. Configurable Relief Categories Catalog
        Schema::create($categoriesTable, function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();
            $table->string('name_en', 120);
            $table->string('name_bn', 150);
            $table->text('description')->nullable();
            $table->string('icon', 60)->nullable();
            $table->string('color_hex', 20)->nullable();
            $table->boolean('is_system')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        // 2. Commodities Catalog
        Schema::create($commoditiesTable, function (Blueprint $table) use ($categoriesTable) {
            $table->uuid('id')->primary();
            $table->uuid('relief_category_id')->nullable()->index();
            $table->string('commodity_code', 30)->unique();
            $table->string('name_en', 150)->index();
            $table->string('name_bn', 200);
            $table->string('category', 50)->index();
            $table->string('unit_of_measure', 20);
            $table->boolean('is_perishable')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->foreign('relief_category_id')
                ->references('id')
                ->on($categoriesTable)
                ->onDelete('set null');
        });

        // 3. Warehouses / Godowns
        Schema::create($warehousesTable, function (Blueprint $table) use ($adminUnitsTable) {
            $table->uuid('id')->primary();
            $table->uuid('administrative_unit_id')->index();
            $table->string('warehouse_code', 30)->unique();
            $table->string('warehouse_name', 150)->index();
            $table->string('warehouse_name_bn', 200)->nullable();
            $table->string('tier', 30)->index();
            $table->decimal('capacity_mt', 10, 2)->default(0);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('administrative_unit_id')
                ->references('id')
                ->on($adminUnitsTable)
                ->onDelete('restrict');
        });

        if ($isPgsql) {
            DB::statement("ALTER TABLE {$warehousesTable} ADD COLUMN location_geom geometry(Point, 4326) NULL;");
            DB::statement("CREATE INDEX idx_warehouses_location_gist ON {$warehousesTable} USING GIST (location_geom);");
        }

        // 4. Warehouse Inventories (Batch-tracked stock balances)
        Schema::create($inventoriesTable, function (Blueprint $table) use ($warehousesTable, $commoditiesTable) {
            $table->uuid('id')->primary();
            $table->uuid('warehouse_id')->index();
            $table->uuid('commodity_id')->index();
            $table->string('batch_number', 50)->index();
            $table->decimal('current_quantity', 12, 2)->default(0)->index();
            $table->decimal('reserved_quantity', 12, 2)->default(0);
            $table->decimal('unit_cost_bdt', 12, 2)->nullable();
            $table->date('received_date')->nullable()->index();
            $table->date('expiry_date')->nullable()->index();
            $table->timestamps();

            $table->unique(['warehouse_id', 'commodity_id', 'batch_number'], 'uq_warehouse_commodity_batch');

            $table->foreign('warehouse_id')
                ->references('id')
                ->on($warehousesTable)
                ->onDelete('restrict');

            $table->foreign('commodity_id')
                ->references('id')
                ->on($commoditiesTable)
                ->onDelete('restrict');
        });

        if ($isPgsql) {
            DB::statement("ALTER TABLE {$inventoriesTable} ADD CONSTRAINT chk_wh_inv_current_non_negative CHECK (current_quantity >= 0);");
            DB::statement("ALTER TABLE {$inventoriesTable} ADD CONSTRAINT chk_wh_inv_reserved_non_negative CHECK (reserved_quantity >= 0);");
        }

        // 5. Distribution Points / Centers
        Schema::create($distributionPointsTable, function (Blueprint $table) use ($adminUnitsTable) {
            $table->uuid('id')->primary();
            $table->uuid('administrative_unit_id')->index();
            $table->string('point_code', 30)->unique();
            $table->string('point_name', 150);
            $table->string('point_name_bn', 200)->nullable();
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('administrative_unit_id')
                ->references('id')
                ->on($adminUnitsTable)
                ->onDelete('restrict');
        });

        if ($isPgsql) {
            DB::statement("ALTER TABLE {$distributionPointsTable} ADD COLUMN location_geom geometry(Point, 4326) NULL;");
            DB::statement("CREATE INDEX idx_distribution_points_location_gist ON {$distributionPointsTable} USING GIST (location_geom);");
        }

        // 6. Relief Requisitions (7-state approval lifecycle)
        Schema::create($requisitionsTable, function (Blueprint $table) use ($disasterEventsTable, $adminUnitsTable, $usersTable) {
            $table->uuid('id')->primary();
            $table->uuid('disaster_event_id')->index();
            $table->uuid('administrative_unit_id')->index();
            $table->string('requisition_no', 50)->unique();
            $table->string('requesting_organization', 150)->nullable();
            $table->integer('estimated_beneficiaries')->default(0);
            $table->string('priority', 20)->default('MEDIUM')->index();
            $table->date('request_date')->index();
            $table->date('needed_by_date')->nullable();
            $table->string('status', 30)->default('DRAFT')->index();
            $table->text('justification')->nullable();
            $table->text('review_notes')->nullable();
            $table->string('supporting_doc_path', 255)->nullable();
            $table->string('supporting_doc_name', 255)->nullable();
            $table->string('supporting_doc_mime', 100)->nullable();
            $table->string('supporting_doc_checksum', 64)->nullable();
            $table->uuid('submitted_by')->nullable()->index();
            $table->timestamp('submitted_at')->nullable()->index();
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->uuid('created_by')->index();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('disaster_event_id')->references('id')->on($disasterEventsTable)->onDelete('restrict');
            $table->foreign('administrative_unit_id')->references('id')->on($adminUnitsTable)->onDelete('restrict');
            $table->foreign('submitted_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('reviewed_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('created_by')->references('id')->on($usersTable)->onDelete('restrict');
            $table->foreign('updated_by')->references('id')->on($usersTable)->onDelete('set null');
        });

        // 7. Relief Requisition Items
        Schema::create($requisitionItemsTable, function (Blueprint $table) use ($requisitionsTable, $commoditiesTable) {
            $table->uuid('id')->primary();
            $table->uuid('relief_requisition_id')->index();
            $table->uuid('commodity_id')->index();
            $table->decimal('quantity_requested', 12, 2);
            $table->decimal('quantity_approved', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('relief_requisition_id')
                ->references('id')
                ->on($requisitionsTable)
                ->onDelete('cascade');

            $table->foreign('commodity_id')
                ->references('id')
                ->on($commoditiesTable)
                ->onDelete('restrict');
        });

        // 8. Relief Allocations
        Schema::create($allocationsTable, function (Blueprint $table) use (
            $requisitionsTable,
            $disasterEventsTable,
            $adminUnitsTable,
            $distributionPointsTable,
            $warehousesTable,
            $usersTable
        ) {
            $table->uuid('id')->primary();
            $table->uuid('relief_requisition_id')->nullable()->index();
            $table->uuid('disaster_event_id')->index();
            $table->uuid('administrative_unit_id')->index();
            $table->uuid('distribution_point_id')->nullable()->index();
            $table->uuid('warehouse_id')->index();
            $table->string('allocation_order_no', 50)->unique();
            $table->string('status', 30)->default('DRAFT')->index();
            $table->timestamp('scheduled_start_at')->nullable()->index();
            $table->timestamp('scheduled_end_at')->nullable();
            $table->integer('duplicate_window_days')->default(30);
            $table->text('remarks')->nullable();
            $table->uuid('created_by')->index();
            $table->uuid('updated_by')->nullable();
            $table->uuid('approved_by')->nullable()->index();
            $table->timestamp('approved_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('relief_requisition_id')->references('id')->on($requisitionsTable)->onDelete('restrict');
            $table->foreign('disaster_event_id')->references('id')->on($disasterEventsTable)->onDelete('restrict');
            $table->foreign('administrative_unit_id')->references('id')->on($adminUnitsTable)->onDelete('restrict');
            $table->foreign('distribution_point_id')->references('id')->on($distributionPointsTable)->onDelete('set null');
            $table->foreign('warehouse_id')->references('id')->on($warehousesTable)->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on($usersTable)->onDelete('restrict');
            $table->foreign('updated_by')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on($usersTable)->onDelete('set null');
        });

        // 9. Relief Allocation Items
        Schema::create($allocationItemsTable, function (Blueprint $table) use ($allocationsTable, $commoditiesTable) {
            $table->uuid('id')->primary();
            $table->uuid('relief_allocation_id')->index();
            $table->uuid('commodity_id')->index();
            $table->decimal('quantity_allocated', 12, 2);
            $table->decimal('quantity_distributed', 12, 2)->default(0);
            $table->decimal('quantity_cancelled', 12, 2)->default(0);
            $table->timestamps();

            $table->foreign('relief_allocation_id')
                ->references('id')
                ->on($allocationsTable)
                ->onDelete('cascade');

            $table->foreign('commodity_id')
                ->references('id')
                ->on($commoditiesTable)
                ->onDelete('restrict');
        });

        // 10. Relief Allocation Beneficiaries (Eligible beneficiary roster)
        Schema::create($allocationBeneficiariesTable, function (Blueprint $table) use (
            $allocationsTable,
            $householdsTable,
            $usersTable
        ) {
            $table->uuid('id')->primary();
            $table->uuid('relief_allocation_id')->index();
            $table->uuid('household_id')->index();
            $table->string('selection_status', 20)->default('SELECTED')->index();
            $table->uuid('assigned_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['relief_allocation_id', 'household_id'], 'uq_allocation_beneficiary');

            $table->foreign('relief_allocation_id')->references('id')->on($allocationsTable)->onDelete('cascade');
            $table->foreign('household_id')->references('id')->on($householdsTable)->onDelete('restrict');
            $table->foreign('assigned_by')->references('id')->on($usersTable)->onDelete('set null');
        });

        // 11. Distribution Vouchers (Hand-off evidence & idempotency)
        Schema::create($distributionVouchersTable, function (Blueprint $table) use (
            $allocationItemsTable,
            $householdsTable,
            $distributionPointsTable,
            $usersTable
        ) {
            $table->uuid('id')->primary();
            $table->uuid('relief_allocation_item_id')->index();
            $table->uuid('household_id')->index();
            $table->uuid('distribution_point_id')->index();
            $table->string('voucher_no', 60)->unique();
            $table->string('idempotency_key', 100)->unique();
            $table->decimal('quantity_disbursed', 12, 2);
            $table->string('verification_method', 30)->index();
            $table->string('verification_proof', 255)->nullable();
            $table->text('acknowledgment_signature')->nullable();
            $table->string('photo_evidence_path', 255)->nullable();
            $table->string('photo_evidence_mime', 100)->nullable();
            $table->string('photo_evidence_checksum', 64)->nullable();
            $table->decimal('distribution_lat', 10, 7)->nullable();
            $table->decimal('distribution_lng', 10, 7)->nullable();
            $table->boolean('duplicate_override')->default(false)->index();
            $table->text('duplicate_override_reason')->nullable();
            $table->uuid('distributed_by')->index();
            $table->timestamp('distributed_at')->index();
            $table->uuid('verified_by_tag_officer')->nullable()->index();
            $table->timestamp('verified_at')->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('is_voided')->default(false)->index();
            $table->text('void_reason')->nullable();
            $table->uuid('voided_by')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();

            $table->foreign('relief_allocation_item_id')
                ->references('id')
                ->on($allocationItemsTable)
                ->onDelete('restrict');

            $table->foreign('household_id')->references('id')->on($householdsTable)->onDelete('restrict');
            $table->foreign('distribution_point_id')->references('id')->on($distributionPointsTable)->onDelete('restrict');
            $table->foreign('distributed_by')->references('id')->on($usersTable)->onDelete('restrict');
            $table->foreign('verified_by_tag_officer')->references('id')->on($usersTable)->onDelete('set null');
            $table->foreign('voided_by')->references('id')->on($usersTable)->onDelete('set null');
        });

        // 12. Immutable Stock Ledger Transactions
        Schema::create($stockTransactionsTable, function (Blueprint $table) use (
            $inventoriesTable,
            $allocationItemsTable,
            $distributionVouchersTable,
            $usersTable
        ) {
            $table->uuid('id')->primary();
            $table->uuid('warehouse_inventory_id')->index();
            $table->string('transaction_type', 35)->index();
            $table->decimal('quantity', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->string('reference_voucher_no', 60)->index();
            $table->uuid('relief_allocation_item_id')->nullable()->index();
            $table->uuid('distribution_voucher_id')->nullable()->index();
            $table->text('notes')->nullable();
            $table->uuid('authorized_by')->index();
            $table->timestamps();

            $table->foreign('warehouse_inventory_id')
                ->references('id')
                ->on($inventoriesTable)
                ->onDelete('restrict');

            $table->foreign('relief_allocation_item_id')
                ->references('id')
                ->on($allocationItemsTable)
                ->onDelete('set null');

            $table->foreign('distribution_voucher_id')
                ->references('id')
                ->on($distributionVouchersTable)
                ->onDelete('set null');

            $table->foreign('authorized_by')->references('id')->on($usersTable)->onDelete('restrict');
        });

        if ($isPgsql) {
            DB::statement("ALTER TABLE {$stockTransactionsTable} ADD CONSTRAINT chk_stock_tx_qty_nonzero CHECK (quantity <> 0);");
            DB::statement("ALTER TABLE {$stockTransactionsTable} ADD CONSTRAINT chk_stock_tx_balance_non_negative CHECK (balance_after >= 0);");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';

        $tables = [
            $isPgsql ? 'logistics.stock_transactions' : 'stock_transactions',
            $isPgsql ? 'distribution.distribution_vouchers' : 'distribution_vouchers',
            $isPgsql ? 'logistics.relief_allocation_beneficiaries' : 'relief_allocation_beneficiaries',
            $isPgsql ? 'logistics.relief_allocation_items' : 'relief_allocation_items',
            $isPgsql ? 'logistics.relief_allocations' : 'relief_allocations',
            $isPgsql ? 'logistics.relief_requisition_items' : 'relief_requisition_items',
            $isPgsql ? 'logistics.relief_requisitions' : 'relief_requisitions',
            $isPgsql ? 'distribution.distribution_points' : 'distribution_points',
            $isPgsql ? 'logistics.warehouse_inventories' : 'warehouse_inventories',
            $isPgsql ? 'logistics.warehouses' : 'warehouses',
            $isPgsql ? 'logistics.commodities' : 'commodities',
            $isPgsql ? 'logistics.relief_categories' : 'relief_categories',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
    }
};
