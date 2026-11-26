<?php

namespace App\Modules\Warehouse\Services;

use App\Core\Models\User;
use App\Modules\Relief\Models\DistributionVoucher;
use App\Modules\Warehouse\Models\StockTransaction;
use App\Modules\Warehouse\Models\WarehouseInventory;
use App\Modules\Warehouse\Models\WarehouseTransfer;
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
        ?string $notes = null,
        ?string $stockReceiptId = null,
        ?string $idempotencyKey = null
    ): StockTransaction {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => ['Stock receipt quantity must be greater than zero.'],
            ]);
        }

        if ($idempotencyKey) {
            $existing = StockTransaction::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
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
            $notes,
            $stockReceiptId,
            $idempotencyKey
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
                    'opening_quantity' => 0,
                    'current_quantity' => 0,
                    'reserved_quantity' => 0,
                    'damaged_quantity' => 0,
                    'expired_quantity' => 0,
                    'unit_cost_bdt' => $unitCostBdt,
                    'received_date' => now()->toDateString(),
                    'expiry_date' => $expiryDate,
                    'status' => WarehouseInventory::STATUS_ACTIVE,
                ]);
                $inventory = WarehouseInventory::whereKey($inventory->id)->lockForUpdate()->first();
            }

            $newBalance = round((float) $inventory->current_quantity + $quantity, 2);
            $inventory->update([
                'current_quantity' => $newBalance,
                'expiry_date' => $expiryDate ?? $inventory->expiry_date,
                'unit_cost_bdt' => $unitCostBdt ?? $inventory->unit_cost_bdt,
                'status' => $newBalance > 0 ? WarehouseInventory::STATUS_ACTIVE : WarehouseInventory::STATUS_EXHAUSTED,
            ]);

            return StockTransaction::create([
                'warehouse_inventory_id' => $inventory->id,
                'warehouse_id' => $warehouseId,
                'commodity_id' => $commodityId,
                'stock_receipt_id' => $stockReceiptId,
                'transaction_type' => StockTransaction::TYPE_STOCK_IN_GRN,
                'reference_type' => 'GRN',
                'quantity' => round($quantity, 2),
                'balance_after' => $newBalance,
                'reference_voucher_no' => $referenceVoucherNo,
                'idempotency_key' => $idempotencyKey,
                'notes' => $notes,
                'authorized_by' => $actor->id,
                'created_by' => $actor->id,
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
        ?string $notes = null,
        string $transactionType = StockTransaction::TYPE_DISTRIBUTION_ISSUE,
        ?string $referenceType = null,
        ?string $idempotencyKey = null
    ): array {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity_disbursed' => ['Distribution quantity must be greater than zero.'],
            ]);
        }

        if ($idempotencyKey) {
            $existing = StockTransaction::where('idempotency_key', $idempotencyKey)->get();
            if ($existing->isNotEmpty()) {
                return $existing->all();
            }
        }

        return DB::transaction(function () use (
            $warehouseId,
            $commodityId,
            $quantity,
            $referenceVoucherNo,
            $actor,
            $allocationItemId,
            $distributionVoucherId,
            $notes,
            $transactionType,
            $referenceType,
            $idempotencyKey
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
            $lotIndex = 0;

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
                    'status' => $newBalance > 0 ? WarehouseInventory::STATUS_ACTIVE : WarehouseInventory::STATUS_EXHAUSTED,
                ]);

                $lotKey = $idempotencyKey
                    ? ($lotIndex === 0 ? $idempotencyKey : "{$idempotencyKey}-lot{$lotIndex}")
                    : null;

                $transactions[] = StockTransaction::create([
                    'warehouse_inventory_id' => $lot->id,
                    'warehouse_id' => $warehouseId,
                    'commodity_id' => $commodityId,
                    'transaction_type' => $transactionType,
                    'reference_type' => $referenceType ?? ($distributionVoucherId ? 'DISTRIBUTION_VOUCHER' : 'RELIEF_ALLOCATION'),
                    'quantity' => -round($take, 2),
                    'balance_after' => $newBalance,
                    'reference_voucher_no' => $referenceVoucherNo,
                    'idempotency_key' => $lotKey,
                    'relief_allocation_item_id' => $allocationItemId,
                    'distribution_voucher_id' => $distributionVoucherId,
                    'notes' => $notes,
                    'authorized_by' => $actor->id,
                    'created_by' => $actor->id,
                ]);

                $lotIndex++;
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
                    'warehouse_id' => $lot->warehouse_id,
                    'commodity_id' => $lot->commodity_id,
                    'transaction_type' => StockTransaction::TYPE_ALLOCATION_RETURN,
                    'reference_type' => 'DISTRIBUTION_VOID',
                    'quantity' => round($restoreQty, 2),
                    'balance_after' => $newBalance,
                    'reference_voucher_no' => $voucher->voucher_no . '-VOID',
                    'relief_allocation_item_id' => $issueTx->relief_allocation_item_id,
                    'distribution_voucher_id' => $voucher->id,
                    'notes' => 'Stock return from voided voucher: ' . $reason,
                    'authorized_by' => $actor->id,
                    'created_by' => $actor->id,
                ]);
            }

            return $reversalTxs;
        });
    }

    /**
     * Record opening balance stock for a warehouse lot (immutable ledger entry).
     */
    public function recordOpeningStock(
        string $warehouseId,
        string $commodityId,
        string $batchNumber,
        float $quantity,
        string $referenceVoucherNo,
        User $actor,
        ?string $expiryDate = null,
        ?float $unitCostBdt = null,
        string $reasonCode = 'OPENING_BALANCE',
        ?string $notes = null,
        ?string $idempotencyKey = null
    ): StockTransaction {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => ['Opening stock quantity must be greater than zero.'],
            ]);
        }

        if ($idempotencyKey) {
            $existing = StockTransaction::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
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
            $reasonCode,
            $notes,
            $idempotencyKey
        ) {
            $lot = WarehouseInventory::where('warehouse_id', $warehouseId)
                ->where('commodity_id', $commodityId)
                ->where('batch_number', $batchNumber)
                ->lockForUpdate()
                ->first();

            if (! $lot) {
                $lot = WarehouseInventory::create([
                    'warehouse_id' => $warehouseId,
                    'commodity_id' => $commodityId,
                    'batch_number' => $batchNumber,
                    'opening_quantity' => 0,
                    'current_quantity' => 0,
                    'reserved_quantity' => 0,
                    'damaged_quantity' => 0,
                    'expired_quantity' => 0,
                    'unit_cost_bdt' => $unitCostBdt,
                    'received_date' => now()->toDateString(),
                    'expiry_date' => $expiryDate,
                    'status' => WarehouseInventory::STATUS_ACTIVE,
                ]);
                $lot = WarehouseInventory::whereKey($lot->id)->lockForUpdate()->first();
            }

            $newBalance = round((float) $lot->current_quantity + $quantity, 2);
            $newOpening = round((float) $lot->opening_quantity + $quantity, 2);

            $lot->update([
                'opening_quantity' => $newOpening,
                'current_quantity' => $newBalance,
                'expiry_date' => $expiryDate ?? $lot->expiry_date,
                'unit_cost_bdt' => $unitCostBdt ?? $lot->unit_cost_bdt,
                'status' => WarehouseInventory::STATUS_ACTIVE,
            ]);

            return StockTransaction::create([
                'warehouse_inventory_id' => $lot->id,
                'warehouse_id' => $warehouseId,
                'commodity_id' => $commodityId,
                'transaction_type' => StockTransaction::TYPE_OPENING_STOCK,
                'reference_type' => 'OPENING_BALANCE',
                'reason_code' => $reasonCode,
                'quantity' => round($quantity, 2),
                'balance_after' => $newBalance,
                'reference_voucher_no' => $referenceVoucherNo,
                'idempotency_key' => $idempotencyKey,
                'notes' => $notes,
                'authorized_by' => $actor->id,
                'created_by' => $actor->id,
            ]);
        });
    }

    /**
     * Record a signed stock adjustment (positive or negative) as an immutable ledger correction.
     */
    public function adjustStock(
        string $warehouseId,
        string $commodityId,
        string $batchNumber,
        float $deltaQuantity,
        string $referenceVoucherNo,
        string $reasonCode,
        User $actor,
        ?User $approver = null,
        ?string $notes = null,
        ?string $expiryDate = null,
        ?float $unitCostBdt = null,
        ?string $idempotencyKey = null
    ): StockTransaction {
        if (abs($deltaQuantity) < 0.0001) {
            throw ValidationException::withMessages([
                'quantity' => ['Stock adjustment delta cannot be zero.'],
            ]);
        }

        if ($idempotencyKey) {
            $existing = StockTransaction::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
        }

        return DB::transaction(function () use (
            $warehouseId,
            $commodityId,
            $batchNumber,
            $deltaQuantity,
            $referenceVoucherNo,
            $reasonCode,
            $actor,
            $approver,
            $notes,
            $expiryDate,
            $unitCostBdt,
            $idempotencyKey
        ) {
            $lot = WarehouseInventory::where('warehouse_id', $warehouseId)
                ->where('commodity_id', $commodityId)
                ->where('batch_number', $batchNumber)
                ->lockForUpdate()
                ->first();

            if (! $lot && $deltaQuantity < 0) {
                throw ValidationException::withMessages([
                    'batch_number' => ['Cannot perform a negative stock adjustment on a non-existent batch.'],
                ]);
            }

            if (! $lot) {
                $lot = WarehouseInventory::create([
                    'warehouse_id' => $warehouseId,
                    'commodity_id' => $commodityId,
                    'batch_number' => $batchNumber,
                    'opening_quantity' => 0,
                    'current_quantity' => 0,
                    'reserved_quantity' => 0,
                    'damaged_quantity' => 0,
                    'expired_quantity' => 0,
                    'unit_cost_bdt' => $unitCostBdt,
                    'received_date' => now()->toDateString(),
                    'expiry_date' => $expiryDate,
                    'status' => WarehouseInventory::STATUS_ACTIVE,
                ]);
                $lot = WarehouseInventory::whereKey($lot->id)->lockForUpdate()->first();
            }

            $currentQty = (float) $lot->current_quantity;
            $reservedQty = (float) $lot->reserved_quantity;
            $newBalance = round($currentQty + $deltaQuantity, 2);

            if ($newBalance < -0.0001) {
                throw ValidationException::withMessages([
                    'quantity' => [
                        sprintf('Adjustment would result in negative stock (current %.2f, delta %.2f). Negative stock is strictly prohibited.', $currentQty, $deltaQuantity),
                    ],
                ]);
            }

            if ($newBalance + 0.0001 < $reservedQty) {
                throw ValidationException::withMessages([
                    'quantity' => [
                        sprintf('Cannot reduce physical stock below reserved quantity (%.2f reserved for approved allocations).', $reservedQty),
                    ],
                ]);
            }

            $lot->update([
                'current_quantity' => max(0.0, $newBalance),
                'expiry_date' => $expiryDate ?? $lot->expiry_date,
                'unit_cost_bdt' => $unitCostBdt ?? $lot->unit_cost_bdt,
                'status' => $newBalance > 0 ? WarehouseInventory::STATUS_ACTIVE : WarehouseInventory::STATUS_EXHAUSTED,
            ]);

            return StockTransaction::create([
                'warehouse_inventory_id' => $lot->id,
                'warehouse_id' => $warehouseId,
                'commodity_id' => $commodityId,
                'transaction_type' => StockTransaction::TYPE_STOCK_ADJUSTMENT,
                'reference_type' => 'ADJUSTMENT',
                'reason_code' => $reasonCode,
                'quantity' => round($deltaQuantity, 2),
                'balance_after' => max(0.0, $newBalance),
                'reference_voucher_no' => $referenceVoucherNo,
                'idempotency_key' => $idempotencyKey,
                'notes' => $notes,
                'authorized_by' => $actor->id,
                'approved_by' => $approver?->id,
                'created_by' => $actor->id,
            ]);
        });
    }

    /**
     * Write off damaged or expired stock from an existing batch with 4-eye approval.
     */
    public function markDamagedOrExpired(
        string $warehouseId,
        string $commodityId,
        string $batchNumber,
        float $quantity,
        string $transactionType,
        string $referenceVoucherNo,
        string $reasonCode,
        User $actor,
        User $approver,
        ?string $notes = null,
        ?string $idempotencyKey = null
    ): StockTransaction {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => ['Write-off quantity must be greater than zero.'],
            ]);
        }

        if (! in_array($transactionType, [StockTransaction::TYPE_WRITE_OFF_DAMAGED, StockTransaction::TYPE_EXPIRED_STOCK], true)) {
            throw ValidationException::withMessages([
                'transaction_type' => ['Invalid write-off transaction type.'],
            ]);
        }

        if ($idempotencyKey) {
            $existing = StockTransaction::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
        }

        return DB::transaction(function () use (
            $warehouseId,
            $commodityId,
            $batchNumber,
            $quantity,
            $transactionType,
            $referenceVoucherNo,
            $reasonCode,
            $actor,
            $approver,
            $notes,
            $idempotencyKey
        ) {
            $lot = WarehouseInventory::where('warehouse_id', $warehouseId)
                ->where('commodity_id', $commodityId)
                ->where('batch_number', $batchNumber)
                ->lockForUpdate()
                ->first();

            if (! $lot) {
                throw ValidationException::withMessages([
                    'batch_number' => ['Inventory batch not found in this warehouse.'],
                ]);
            }

            $unreserved = max(0.0, (float) $lot->current_quantity - (float) $lot->reserved_quantity);
            if ($unreserved + 0.0001 < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => [
                        sprintf('Insufficient unreserved batch stock to write off (requested %.2f, unreserved %.2f).', $quantity, $unreserved),
                    ],
                ]);
            }

            $newBalance = max(0.0, round((float) $lot->current_quantity - $quantity, 2));
            $updates = [
                'current_quantity' => $newBalance,
            ];

            if ($transactionType === StockTransaction::TYPE_WRITE_OFF_DAMAGED) {
                $updates['damaged_quantity'] = round((float) $lot->damaged_quantity + $quantity, 2);
                $updates['status'] = $newBalance > 0 ? $lot->status : WarehouseInventory::STATUS_EXHAUSTED;
            } else {
                $updates['expired_quantity'] = round((float) $lot->expired_quantity + $quantity, 2);
                $updates['status'] = $newBalance > 0 ? $lot->status : WarehouseInventory::STATUS_EXPIRED;
            }

            $lot->update($updates);

            return StockTransaction::create([
                'warehouse_inventory_id' => $lot->id,
                'warehouse_id' => $warehouseId,
                'commodity_id' => $commodityId,
                'transaction_type' => $transactionType,
                'reference_type' => $transactionType === StockTransaction::TYPE_EXPIRED_STOCK ? 'EXPIRY_QUARANTINE' : 'DAMAGE_WRITEOFF',
                'reason_code' => $reasonCode,
                'quantity' => -round($quantity, 2),
                'balance_after' => $newBalance,
                'reference_voucher_no' => $referenceVoucherNo,
                'idempotency_key' => $idempotencyKey,
                'notes' => $notes,
                'authorized_by' => $actor->id,
                'approved_by' => $approver->id,
                'created_by' => $actor->id,
            ]);
        });
    }

    /**
     * Record a supply return back into warehouse inventory.
     */
    public function recordReturnStock(
        string $warehouseId,
        string $commodityId,
        string $batchNumber,
        float $quantity,
        string $referenceVoucherNo,
        string $reasonCode,
        User $actor,
        ?string $expiryDate = null,
        ?float $unitCostBdt = null,
        ?string $notes = null,
        ?string $idempotencyKey = null
    ): StockTransaction {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => ['Return quantity must be greater than zero.'],
            ]);
        }

        if ($idempotencyKey) {
            $existing = StockTransaction::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
        }

        return DB::transaction(function () use (
            $warehouseId,
            $commodityId,
            $batchNumber,
            $quantity,
            $referenceVoucherNo,
            $reasonCode,
            $actor,
            $expiryDate,
            $unitCostBdt,
            $notes,
            $idempotencyKey
        ) {
            $lot = WarehouseInventory::where('warehouse_id', $warehouseId)
                ->where('commodity_id', $commodityId)
                ->where('batch_number', $batchNumber)
                ->lockForUpdate()
                ->first();

            if (! $lot) {
                $lot = WarehouseInventory::create([
                    'warehouse_id' => $warehouseId,
                    'commodity_id' => $commodityId,
                    'batch_number' => $batchNumber,
                    'opening_quantity' => 0,
                    'current_quantity' => 0,
                    'reserved_quantity' => 0,
                    'damaged_quantity' => 0,
                    'expired_quantity' => 0,
                    'unit_cost_bdt' => $unitCostBdt,
                    'received_date' => now()->toDateString(),
                    'expiry_date' => $expiryDate,
                    'status' => WarehouseInventory::STATUS_ACTIVE,
                ]);
                $lot = WarehouseInventory::whereKey($lot->id)->lockForUpdate()->first();
            }

            $newBalance = round((float) $lot->current_quantity + $quantity, 2);
            $lot->update([
                'current_quantity' => $newBalance,
                'status' => WarehouseInventory::STATUS_ACTIVE,
            ]);

            return StockTransaction::create([
                'warehouse_inventory_id' => $lot->id,
                'warehouse_id' => $warehouseId,
                'commodity_id' => $commodityId,
                'transaction_type' => StockTransaction::TYPE_RETURN_IN,
                'reference_type' => 'RETURN',
                'reason_code' => $reasonCode,
                'quantity' => round($quantity, 2),
                'balance_after' => $newBalance,
                'reference_voucher_no' => $referenceVoucherNo,
                'idempotency_key' => $idempotencyKey,
                'notes' => $notes,
                'authorized_by' => $actor->id,
                'created_by' => $actor->id,
            ]);
        });
    }

    /**
     * Deduct unreserved stock from source warehouse during transfer dispatch.
     *
     * @return array<int, array{batch_number:string, quantity:float, unit_cost_bdt:?float, expiry_date:?string, transaction:StockTransaction}>
     */
    public function transferOut(
        WarehouseTransfer $transfer,
        string $warehouseId,
        string $commodityId,
        float $quantity,
        User $actor,
        ?string $preferredBatchNumber = null,
        ?string $notes = null
    ): array {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity_dispatched' => ['Transfer dispatch quantity must be greater than zero.'],
            ]);
        }

        return DB::transaction(function () use (
            $transfer,
            $warehouseId,
            $commodityId,
            $quantity,
            $actor,
            $preferredBatchNumber,
            $notes
        ) {
            $query = $this->fefoLotsQuery($warehouseId, $commodityId)
                ->where('current_quantity', '>', 0);

            if ($preferredBatchNumber) {
                $query->where('batch_number', $preferredBatchNumber);
            }

            $lots = $query->lockForUpdate()->get();
            $totalUnreserved = (float) $lots->sum(fn ($l) => max(0.0, (float) $l->current_quantity - (float) $l->reserved_quantity));

            if ($totalUnreserved + 0.0001 < $quantity) {
                throw ValidationException::withMessages([
                    'items' => [
                        sprintf(
                            'Insufficient unreserved stock in source warehouse to dispatch transfer %s (requested %.2f, unreserved %.2f).',
                            $transfer->transfer_no,
                            $quantity,
                            $totalUnreserved
                        ),
                    ],
                ]);
            }

            $remaining = $quantity;
            $dispatchedBatches = [];

            foreach ($lots as $lot) {
                if ($remaining <= 0.0001) {
                    break;
                }

                $unreservedInLot = max(0.0, (float) $lot->current_quantity - (float) $lot->reserved_quantity);
                if ($unreservedInLot <= 0) {
                    continue;
                }

                $take = min($unreservedInLot, $remaining);
                $newBalance = max(0.0, round((float) $lot->current_quantity - $take, 2));

                $lot->update([
                    'current_quantity' => $newBalance,
                    'status' => $newBalance > 0 ? WarehouseInventory::STATUS_ACTIVE : WarehouseInventory::STATUS_EXHAUSTED,
                ]);

                $tx = StockTransaction::create([
                    'warehouse_inventory_id' => $lot->id,
                    'warehouse_id' => $warehouseId,
                    'commodity_id' => $commodityId,
                    'warehouse_transfer_id' => $transfer->id,
                    'transaction_type' => StockTransaction::TYPE_INTER_WAREHOUSE_TRANSFER,
                    'reference_type' => 'TRANSFER_OUT',
                    'quantity' => -round($take, 2),
                    'balance_after' => $newBalance,
                    'reference_voucher_no' => $transfer->transfer_no,
                    'notes' => $notes ?? "Dispatched from source warehouse under transfer {$transfer->transfer_no}",
                    'authorized_by' => $actor->id,
                    'approved_by' => $transfer->approved_by,
                    'created_by' => $actor->id,
                ]);

                $dispatchedBatches[] = [
                    'batch_number' => $lot->batch_number,
                    'quantity' => round($take, 2),
                    'unit_cost_bdt' => $lot->unit_cost_bdt !== null ? (float) $lot->unit_cost_bdt : null,
                    'expiry_date' => $lot->expiry_date?->toDateString(),
                    'transaction' => $tx,
                ];

                $remaining = round($remaining - $take, 2);
            }

            return $dispatchedBatches;
        });
    }

    /**
     * Credit received stock into destination warehouse upon transfer receipt confirmation.
     */
    public function transferIn(
        WarehouseTransfer $transfer,
        string $warehouseId,
        string $commodityId,
        string $batchNumber,
        float $quantity,
        User $actor,
        ?string $expiryDate = null,
        ?float $unitCostBdt = null,
        ?string $notes = null
    ): StockTransaction {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity_received' => ['Transfer receipt quantity must be greater than zero.'],
            ]);
        }

        return DB::transaction(function () use (
            $transfer,
            $warehouseId,
            $commodityId,
            $batchNumber,
            $quantity,
            $actor,
            $expiryDate,
            $unitCostBdt,
            $notes
        ) {
            $lot = WarehouseInventory::where('warehouse_id', $warehouseId)
                ->where('commodity_id', $commodityId)
                ->where('batch_number', $batchNumber)
                ->lockForUpdate()
                ->first();

            if (! $lot) {
                $lot = WarehouseInventory::create([
                    'warehouse_id' => $warehouseId,
                    'commodity_id' => $commodityId,
                    'batch_number' => $batchNumber,
                    'opening_quantity' => 0,
                    'current_quantity' => 0,
                    'reserved_quantity' => 0,
                    'damaged_quantity' => 0,
                    'expired_quantity' => 0,
                    'unit_cost_bdt' => $unitCostBdt,
                    'received_date' => now()->toDateString(),
                    'expiry_date' => $expiryDate,
                    'status' => WarehouseInventory::STATUS_ACTIVE,
                ]);
                $lot = WarehouseInventory::whereKey($lot->id)->lockForUpdate()->first();
            }

            $newBalance = round((float) $lot->current_quantity + $quantity, 2);
            $lot->update([
                'current_quantity' => $newBalance,
                'expiry_date' => $expiryDate ?? $lot->expiry_date,
                'unit_cost_bdt' => $unitCostBdt ?? $lot->unit_cost_bdt,
                'status' => WarehouseInventory::STATUS_ACTIVE,
            ]);

            return StockTransaction::create([
                'warehouse_inventory_id' => $lot->id,
                'warehouse_id' => $warehouseId,
                'commodity_id' => $commodityId,
                'warehouse_transfer_id' => $transfer->id,
                'transaction_type' => StockTransaction::TYPE_INTER_WAREHOUSE_TRANSFER,
                'reference_type' => 'TRANSFER_IN',
                'quantity' => round($quantity, 2),
                'balance_after' => $newBalance,
                'reference_voucher_no' => $transfer->transfer_no,
                'notes' => $notes ?? "Received into destination warehouse under transfer {$transfer->transfer_no}",
                'authorized_by' => $actor->id,
                'approved_by' => $transfer->approved_by,
                'created_by' => $actor->id,
            ]);
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
