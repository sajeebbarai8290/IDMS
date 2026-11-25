<?php

namespace App\Modules\Warehouse\Services;

use App\Core\Models\User;
use App\Modules\Relief\Models\DistributionVoucher;
use App\Modules\Warehouse\Models\StockTransaction;
use App\Modules\Warehouse\Models\WarehouseInventory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryLedgerService
{
    /**
     * Get total physical stock on hand in a warehouse for a commodity.
     */
    public function getPhysicalStock(string $warehouseId, string $commodityId): float
    {
        return (float) WarehouseInventory::where('warehouse_id', $warehouseId)
            ->where('commodity_id', $commodityId)
            ->sum('current_quantity');
    }

    /**
     * Get unreserved stock available for new allocations.
     */
    public function getUnreservedStock(string $warehouseId, string $commodityId): float
    {
        $lots = WarehouseInventory::where('warehouse_id', $warehouseId)
            ->where('commodity_id', $commodityId)
            ->where('current_quantity', '>', 0)
            ->get(['current_quantity', 'reserved_quantity']);

        return (float) $lots->sum(fn ($lot) => max(0.0, (float) $lot->current_quantity - (float) $lot->reserved_quantity));
    }

    /**
     * Receive new stock into a batch and record immutable GRN ledger entry.
     */
    public function receiveStock(
        string $warehouseId,
        string $commodityId,
        string $batchNumber,
        float $quantity,
        string $referenceVoucherNo,
        User $actor,
        ?string $expiryDate = null,
        ?float $unitCostBdt = null,
        ?string $notes = null
    ): StockTransaction {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => ['Stock receipt quantity must be greater than zero.'],
            ]);
        }

        return DB::transaction(function () use (
            $warehouseId,
            $commodityId,
            $batchNumber,
            $quantity,
            $referenceVoucherNo,
            $actor,
            $expiryDate,
            $unitCostBdt,
            $notes
        ) {
            $inventory = WarehouseInventory::where('warehouse_id', $warehouseId)
                ->where('commodity_id', $commodityId)
                ->where('batch_number', $batchNumber)
                ->lockForUpdate()
                ->first();

            if (!$inventory) {
                $inventory = WarehouseInventory::create([
                    'warehouse_id' => $warehouseId,
                    'commodity_id' => $commodityId,
                    'batch_number' => $batchNumber,
                    'current_quantity' => 0,
                    'reserved_quantity' => 0,
                    'unit_cost_bdt' => $unitCostBdt,
                    'received_date' => now()->toDateString(),
                    'expiry_date' => $expiryDate,
                ]);
                $inventory = WarehouseInventory::whereKey($inventory->id)->lockForUpdate()->first();
            }

            $newBalance = round((float) $inventory->current_quantity + $quantity, 2);
            $inventory->update([
                'current_quantity' => $newBalance,
                'expiry_date' => $expiryDate ?? $inventory->expiry_date,
                'unit_cost_bdt' => $unitCostBdt ?? $inventory->unit_cost_bdt,
            ]);

            return StockTransaction::create([
                'warehouse_inventory_id' => $inventory->id,
                'transaction_type' => StockTransaction::TYPE_STOCK_IN_GRN,
                'quantity' => round($quantity, 2),
                'balance_after' => $newBalance,
                'reference_voucher_no' => $referenceVoucherNo,
                'notes' => $notes,
                'authorized_by' => $actor->id,
            ]);
        });
    }

    /**
     * Reserve unreserved warehouse stock across lots when sanctioning an allocation.
     */
    public function reserveStock(string $warehouseId, string $commodityId, float $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        DB::transaction(function () use ($warehouseId, $commodityId, $quantity) {
            $lots = $this->fefoLotsQuery($warehouseId, $commodityId)
                ->where('current_quantity', '>', 0)
                ->lockForUpdate()
                ->get();

            $totalUnreserved = $lots->sum(fn ($l) => max(0.0, (float) $l->current_quantity - (float) $l->reserved_quantity));
            if ($totalUnreserved + 0.0001 < $quantity) {
                throw ValidationException::withMessages([
                    'items' => [
                        sprintf(
                            'Insufficient unreserved warehouse stock to sanction allocation (requested %.2f, available %.2f).',
                            $quantity,
                            $totalUnreserved
                        ),
                    ],
                ]);
            }

            $remainingToReserve = $quantity;
            foreach ($lots as $lot) {
                if ($remainingToReserve <= 0.0001) {
                    break;
                }
                $availableInLot = max(0.0, (float) $lot->current_quantity - (float) $lot->reserved_quantity);
                if ($availableInLot <= 0) {
                    continue;
                }
                $take = min($availableInLot, $remainingToReserve);
                $lot->update([
                    'reserved_quantity' => round((float) $lot->reserved_quantity + $take, 2),
                ]);
                $remainingToReserve = round($remainingToReserve - $take, 2);
            }
        });
    }

    /**
     * Release reserved stock when an allocation or remaining line quantity is cancelled.
     */
    public function releaseReservation(string $warehouseId, string $commodityId, float $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        DB::transaction(function () use ($warehouseId, $commodityId, $quantity) {
            $lots = WarehouseInventory::where('warehouse_id', $warehouseId)
                ->where('commodity_id', $commodityId)
                ->where('reserved_quantity', '>', 0)
                ->orderByDesc('updated_at')
                ->lockForUpdate()
                ->get();

            $remainingToRelease = $quantity;
            foreach ($lots as $lot) {
                if ($remainingToRelease <= 0.0001) {
                    break;
                }
                $release = min((float) $lot->reserved_quantity, $remainingToRelease);
                $lot->update([
                    'reserved_quantity' => max(0.0, round((float) $lot->reserved_quantity - $release, 2)),
                ]);
                $remainingToRelease = round($remainingToRelease - $release, 2);
            }
        });
    }

    /**
     * Atomically deduct stock across batches using FEFO/FIFO order and record StockTransaction rows.
     * Enforces strict non-negative balance invariant.
     *
     * @return StockTransaction[]
     */
    public function deductStock(
        string $warehouseId,
        string $commodityId,
        float $quantity,
        string $referenceVoucherNo,
        User $actor,
        ?string $allocationItemId = null,
        ?string $distributionVoucherId = null,
        ?string $notes = null
    ): array {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity_disbursed' => ['Distribution quantity must be greater than zero.'],
            ]);
        }

        return DB::transaction(function () use (
            $warehouseId,
            $commodityId,
            $quantity,
            $referenceVoucherNo,
            $actor,
            $allocationItemId,
            $distributionVoucherId,
            $notes
        ) {
            $lots = $this->fefoLotsQuery($warehouseId, $commodityId)
                ->where('current_quantity', '>', 0)
                ->lockForUpdate()
                ->get();

            $totalPhysical = (float) $lots->sum('current_quantity');
            if ($totalPhysical + 0.0001 < $quantity) {
                throw ValidationException::withMessages([
                    'quantity_disbursed' => [
                        sprintf(
                            'Insufficient warehouse stock (requested %.2f, available %.2f). Negative inventory is prohibited.',
                            $quantity,
                            $totalPhysical
                        ),
                    ],
                ]);
            }

            $remaining = $quantity;
            $transactions = [];

            foreach ($lots as $lot) {
                if ($remaining <= 0.0001) {
                    break;
                }

                $lotAvailable = (float) $lot->current_quantity;
                if ($lotAvailable <= 0) {
                    continue;
                }

                $take = min($lotAvailable, $remaining);
                $newBalance = max(0.0, round($lotAvailable - $take, 2));
                $newReserved = max(0.0, round((float) $lot->reserved_quantity - $take, 2));

                $lot->update([
                    'current_quantity' => $newBalance,
                    'reserved_quantity' => $newReserved,
                ]);

                $transactions[] = StockTransaction::create([
                    'warehouse_inventory_id' => $lot->id,
                    'transaction_type' => StockTransaction::TYPE_DISTRIBUTION_ISSUE,
                    'quantity' => -round($take, 2),
                    'balance_after' => $newBalance,
                    'reference_voucher_no' => $referenceVoucherNo,
                    'relief_allocation_item_id' => $allocationItemId,
                    'distribution_voucher_id' => $distributionVoucherId,
                    'notes' => $notes,
                    'authorized_by' => $actor->id,
                ]);

                $remaining = round($remaining - $take, 2);
            }

            return $transactions;
        });
    }

    /**
     * Reverse stock deduction when a distribution voucher is voided.
     *
     * @return StockTransaction[]
     */
    public function reverseDistributionDeduction(
        DistributionVoucher $voucher,
        User $actor,
        string $reason
    ): array {
        return DB::transaction(function () use ($voucher, $actor, $reason) {
            $issueTxs = StockTransaction::where('distribution_voucher_id', $voucher->id)
                ->where('transaction_type', StockTransaction::TYPE_DISTRIBUTION_ISSUE)
                ->get();

            $reversalTxs = [];
            foreach ($issueTxs as $issueTx) {
                $lot = WarehouseInventory::whereKey($issueTx->warehouse_inventory_id)
                    ->lockForUpdate()
                    ->first();

                if (!$lot) {
                    continue;
                }

                $restoreQty = abs((float) $issueTx->quantity);
                $newBalance = round((float) $lot->current_quantity + $restoreQty, 2);
                $newReserved = round((float) $lot->reserved_quantity + $restoreQty, 2);

                $lot->update([
                    'current_quantity' => $newBalance,
                    'reserved_quantity' => $newReserved,
                ]);

                $reversalTxs[] = StockTransaction::create([
                    'warehouse_inventory_id' => $lot->id,
                    'transaction_type' => StockTransaction::TYPE_ALLOCATION_RETURN,
                    'quantity' => round($restoreQty, 2),
                    'balance_after' => $newBalance,
                    'reference_voucher_no' => $voucher->voucher_no . '-VOID',
                    'relief_allocation_item_id' => $issueTx->relief_allocation_item_id,
                    'distribution_voucher_id' => $voucher->id,
                    'notes' => 'Stock return from voided voucher: ' . $reason,
                    'authorized_by' => $actor->id,
                ]);
            }

            return $reversalTxs;
        });
    }

    /**
     * Portable FEFO / FIFO batch ordering across PostgreSQL and SQLite.
     */
    private function fefoLotsQuery(string $warehouseId, string $commodityId)
    {
        return WarehouseInventory::where('warehouse_id', $warehouseId)
            ->where('commodity_id', $commodityId)
            ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END ASC')
            ->orderBy('expiry_date', 'asc')
            ->orderByRaw('CASE WHEN received_date IS NULL THEN 1 ELSE 0 END ASC')
            ->orderBy('received_date', 'asc')
            ->orderBy('created_at', 'asc');
    }
}
