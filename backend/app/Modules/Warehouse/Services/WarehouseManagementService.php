<?php

namespace App\Modules\Warehouse\Services;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\AuditLog;
use App\Core\Models\User;
use App\Core\Services\AdministrativeScopeService;
use App\Modules\Relief\Models\ReliefAllocation;
use App\Modules\Relief\Models\ReliefAllocationItem;
use App\Modules\Warehouse\Models\Commodity;
use App\Modules\Warehouse\Models\StockReceipt;
use App\Modules\Warehouse\Models\StockReceiptItem;
use App\Modules\Warehouse\Models\StockTransaction;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Warehouse\Models\WarehouseInventory;
use App\Modules\Warehouse\Models\WarehouseTransfer;
use App\Modules\Warehouse\Models\WarehouseTransferItem;
use App\Modules\Warehouse\Policies\WarehousePolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WarehouseManagementService
{
    public const DASHBOARD_CACHE_VERSION_KEY = 'warehouse:dashboard:version';

    public function __construct(
        protected InventoryLedgerService $ledgerService,
        protected WarehouseSpatialService $spatialService,
        protected AdministrativeScopeService $scopeService
    ) {}

    public function bumpDashboardCache(): void
    {
        $current = (int) Cache::get(self::DASHBOARD_CACHE_VERSION_KEY, 1);
        Cache::put(self::DASHBOARD_CACHE_VERSION_KEY, $current + 1, now()->addDays(7));
    }

    // -------------------------------------------------------------------------
    // 1. Warehouse Facility Management
    // -------------------------------------------------------------------------

    public function createWarehouse(array $data, User $actor): Warehouse
    {
        $adminUnit = AdministrativeUnit::findOrFail($data['administrative_unit_id']);
        if (! $this->scopeService->canAccessScope($actor, $adminUnit->ancestry_path ?? '/')) {
            throw new AuthorizationException('Jurisdiction access denied: cannot register a warehouse outside your administrative hierarchy.');
        }

        $warehouse = DB::transaction(function () use ($data, $actor) {
            $warehouse = Warehouse::create([
                'administrative_unit_id' => $data['administrative_unit_id'],
                'warehouse_code' => strtoupper(trim($data['warehouse_code'])),
                'warehouse_name' => trim($data['warehouse_name']),
                'warehouse_name_bn' => $data['warehouse_name_bn'] ?? null,
                'tier' => $data['tier'],
                'warehouse_type' => $data['warehouse_type'] ?? Warehouse::TYPE_RELIEF_GODOWN,
                'owning_organization' => $data['owning_organization'] ?? null,
                'responsible_officer_name' => $data['responsible_officer_name'] ?? null,
                'responsible_officer_contact' => $data['responsible_officer_contact'] ?? null,
                'responsible_user_id' => $data['responsible_user_id'] ?? null,
                'address' => $data['address'] ?? null,
                'contact_phone' => $data['contact_phone'] ?? null,
                'contact_email' => $data['contact_email'] ?? null,
                'capacity_mt' => $data['capacity_mt'],
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->spatialService->syncLocationGeometry($warehouse);

            AuditLog::log(
                'WAREHOUSE_FACILITY_CREATED',
                Warehouse::class,
                $warehouse->id,
                null,
                $warehouse->only(['warehouse_code', 'warehouse_name', 'tier', 'capacity_mt', 'administrative_unit_id']),
                $actor,
                $warehouse->administrative_unit_id
            );

            return $warehouse;
        });

        $this->bumpDashboardCache();

        return $warehouse->load(['administrativeUnit', 'inventories', 'responsibleUser']);
    }

    public function updateWarehouse(Warehouse $warehouse, array $data, User $actor): Warehouse
    {
        if (array_key_exists('administrative_unit_id', $data)) {
            $targetUnit = AdministrativeUnit::findOrFail($data['administrative_unit_id']);
            if (! $this->scopeService->canAccessScope($actor, $targetUnit->ancestry_path ?? '/')) {
                throw new AuthorizationException('Jurisdiction access denied: cannot move a warehouse outside your administrative hierarchy.');
            }
        }

        $warehouse = DB::transaction(function () use ($warehouse, $data, $actor) {
            $oldValues = $warehouse->only([
                'warehouse_code',
                'warehouse_name',
                'tier',
                'warehouse_type',
                'capacity_mt',
                'is_active',
                'administrative_unit_id',
            ]);

            if (array_key_exists('warehouse_code', $data)) {
                $data['warehouse_code'] = strtoupper(trim($data['warehouse_code']));
            }

            $data['updated_by'] = $actor->id;
            $warehouse->update($data);
            $this->spatialService->syncLocationGeometry($warehouse);

            AuditLog::log(
                'WAREHOUSE_FACILITY_UPDATED',
                Warehouse::class,
                $warehouse->id,
                $oldValues,
                $warehouse->only(array_keys($oldValues)),
                $actor,
                $warehouse->administrative_unit_id
            );

            return $warehouse;
        });

        $this->bumpDashboardCache();

        return $warehouse->load(['administrativeUnit', 'inventories', 'responsibleUser']);
    }

    // -------------------------------------------------------------------------
    // 2. Item / Commodity Management
    // -------------------------------------------------------------------------

    public function createCommodity(array $data, User $actor): Commodity
    {
        $commodity = DB::transaction(function () use ($data, $actor) {
            $commodity = Commodity::create([
                'relief_category_id' => $data['relief_category_id'] ?? null,
                'commodity_code' => strtoupper(trim($data['commodity_code'])),
                'name_en' => trim($data['name_en']),
                'name_bn' => trim($data['name_bn']),
                'category' => strtoupper(trim($data['category'])),
                'unit_of_measure' => strtoupper(trim($data['unit_of_measure'])),
                'description' => $data['description'] ?? null,
                'minimum_stock_level' => $data['minimum_stock_level'] ?? 0,
                'is_perishable' => (bool) ($data['is_perishable'] ?? false),
                'is_active' => $data['is_active'] ?? true,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            AuditLog::log(
                'WAREHOUSE_COMMODITY_CREATED',
                Commodity::class,
                $commodity->id,
                null,
                $commodity->only(['commodity_code', 'name_en', 'unit_of_measure', 'minimum_stock_level']),
                $actor
            );

            return $commodity;
        });

        $this->bumpDashboardCache();

        return $commodity->load('reliefCategory');
    }

    public function updateCommodity(Commodity $commodity, array $data, User $actor): Commodity
    {
        $commodity = DB::transaction(function () use ($commodity, $data, $actor) {
            $oldValues = $commodity->only(['commodity_code', 'name_en', 'name_bn', 'unit_of_measure', 'minimum_stock_level', 'is_perishable', 'is_active']);

            if (array_key_exists('commodity_code', $data)) {
                $data['commodity_code'] = strtoupper(trim($data['commodity_code']));
            }
            if (array_key_exists('category', $data)) {
                $data['category'] = strtoupper(trim($data['category']));
            }
            if (array_key_exists('unit_of_measure', $data)) {
                $data['unit_of_measure'] = strtoupper(trim($data['unit_of_measure']));
            }

            $data['updated_by'] = $actor->id;
            $commodity->update($data);

            AuditLog::log(
                'WAREHOUSE_COMMODITY_UPDATED',
                Commodity::class,
                $commodity->id,
                $oldValues,
                $commodity->only(array_keys($oldValues)),
                $actor
            );

            return $commodity;
        });

        $this->bumpDashboardCache();

        return $commodity->load('reliefCategory');
    }

    // -------------------------------------------------------------------------
    // 3. Procurement & Receiving (GRN Workflow)
    // -------------------------------------------------------------------------

    public function createStockReceipt(array $data, User $actor, ?UploadedFile $attachment = null): StockReceipt
    {
        $idempotencyKey = ! empty($data['idempotency_key']) ? trim((string) $data['idempotency_key']) : null;
        if ($idempotencyKey) {
            $existing = StockReceipt::where('idempotency_key', $idempotencyKey)
                ->with(['warehouse', 'items.commodity', 'receiver', 'verifier'])
                ->first();
            if ($existing) {
                if ($existing->warehouse_id !== $data['warehouse_id']) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => ['Idempotency key already used for a different warehouse receipt.'],
                    ]);
                }

                return $existing;
            }
        }

        $warehouse = Warehouse::with('administrativeUnit')->findOrFail($data['warehouse_id']);
        Gate::forUser($actor)->authorize('receiveStock', [StockReceipt::class, $warehouse]);

        $attachmentMeta = [
            'attachment_path' => null,
            'attachment_original_name' => null,
            'attachment_mime' => null,
            'attachment_size_bytes' => null,
            'attachment_sha256' => null,
        ];

        if ($attachment) {
            $storedPath = $attachment->store('warehouse_grn', 'local');
            $rawContents = Storage::disk('local')->get($storedPath);
            $attachmentMeta = [
                'attachment_path' => $storedPath,
                'attachment_original_name' => $attachment->getClientOriginalName(),
                'attachment_mime' => $attachment->getMimeType() ?: $attachment->getClientMimeType(),
                'attachment_size_bytes' => $attachment->getSize(),
                'attachment_sha256' => hash('sha256', (string) $rawContents),
            ];
        }

        $receipt = DB::transaction(function () use ($data, $actor, $warehouse, $idempotencyKey, $attachmentMeta) {
            $receiptNo = $this->generateUniqueSerial('GRN', StockReceipt::class, 'receipt_no');

            $receipt = StockReceipt::create(array_merge([
                'receipt_no' => $receiptNo,
                'warehouse_id' => $warehouse->id,
                'supplier_name' => trim($data['supplier_name']),
                'supplier_contact' => $data['supplier_contact'] ?? null,
                'purchase_order_no' => $data['purchase_order_no'] ?? null,
                'received_date' => $data['received_date'],
                'status' => StockReceipt::STATUS_PENDING_VERIFICATION,
                'idempotency_key' => $idempotencyKey,
                'received_by' => $actor->id,
                'notes' => $data['notes'] ?? null,
            ], $attachmentMeta));

            foreach ($data['items'] as $item) {
                StockReceiptItem::create([
                    'stock_receipt_id' => $receipt->id,
                    'commodity_id' => $item['commodity_id'],
                    'batch_number' => strtoupper(trim($item['batch_number'])),
                    'quantity_received' => round((float) $item['quantity_received'], 2),
                    'unit_cost_bdt' => isset($item['unit_cost_bdt']) ? round((float) $item['unit_cost_bdt'], 2) : null,
                    'expiry_date' => $item['expiry_date'] ?? null,
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            AuditLog::log(
                'WAREHOUSE_GRN_CREATED',
                StockReceipt::class,
                $receipt->id,
                null,
                ['receipt_no' => $receipt->receipt_no, 'warehouse_id' => $warehouse->id, 'supplier_name' => $receipt->supplier_name],
                $actor,
                $warehouse->administrative_unit_id
            );

            return $receipt;
        });

        $this->bumpDashboardCache();

        return $receipt->load(['warehouse', 'items.commodity', 'receiver', 'verifier']);
    }

    public function verifyStockReceipt(StockReceipt $receipt, User $actor, ?string $verificationNotes = null): StockReceipt
    {
        Gate::forUser($actor)->authorize('verifyReceipt', $receipt);

        if ($receipt->status !== StockReceipt::STATUS_PENDING_VERIFICATION) {
            throw ValidationException::withMessages([
                'status' => ["Only GRNs in PENDING_VERIFICATION status can be verified (current status: {$receipt->status})."],
            ]);
        }

        $receipt->loadMissing(['warehouse', 'items']);

        $verifiedReceipt = DB::transaction(function () use ($receipt, $actor, $verificationNotes) {
            $locked = StockReceipt::whereKey($receipt->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== StockReceipt::STATUS_PENDING_VERIFICATION) {
                throw ValidationException::withMessages([
                    'status' => ['This GRN has already been processed.'],
                ]);
            }

            foreach ($locked->items as $idx => $item) {
                $tx = $this->ledgerService->receiveStock(
                    $locked->warehouse_id,
                    $item->commodity_id,
                    $item->batch_number,
                    (float) $item->quantity_received,
                    $locked->receipt_no,
                    $actor,
                    $item->expiry_date?->toDateString(),
                    $item->unit_cost_bdt !== null ? (float) $item->unit_cost_bdt : null,
                    $item->notes ?? "Verified GRN {$locked->receipt_no} from {$locked->supplier_name}",
                    $locked->id,
                    $locked->idempotency_key ? "{$locked->idempotency_key}-VERIFY-{$idx}" : null
                );

                $item->update([
                    'warehouse_inventory_id' => $tx->warehouse_inventory_id,
                ]);
            }

            $locked->update([
                'status' => StockReceipt::STATUS_VERIFIED,
                'verified_by' => $actor->id,
                'verified_at' => now(),
                'verification_notes' => $verificationNotes,
            ]);

            AuditLog::log(
                'WAREHOUSE_GRN_VERIFIED',
                StockReceipt::class,
                $locked->id,
                ['status' => StockReceipt::STATUS_PENDING_VERIFICATION],
                ['status' => StockReceipt::STATUS_VERIFIED, 'verified_by' => $actor->id],
                $actor,
                $receipt->warehouse?->administrative_unit_id
            );

            return $locked;
        });

        $this->bumpDashboardCache();

        return $verifiedReceipt->load(['warehouse', 'items.commodity', 'receiver', 'verifier']);
    }

    public function rejectStockReceipt(StockReceipt $receipt, User $actor, string $reason): StockReceipt
    {
        Gate::forUser($actor)->authorize('verifyReceipt', $receipt);

        if ($receipt->status !== StockReceipt::STATUS_PENDING_VERIFICATION) {
            throw ValidationException::withMessages([
                'status' => ['Only pending GRNs can be rejected.'],
            ]);
        }

        $receipt->update([
            'status' => StockReceipt::STATUS_REJECTED,
            'verified_by' => $actor->id,
            'verified_at' => now(),
            'verification_notes' => $reason,
        ]);

        AuditLog::log(
            'WAREHOUSE_GRN_REJECTED',
            StockReceipt::class,
            $receipt->id,
            ['status' => StockReceipt::STATUS_PENDING_VERIFICATION],
            ['status' => StockReceipt::STATUS_REJECTED, 'verification_notes' => $reason],
            $actor,
            $receipt->warehouse?->administrative_unit_id
        );

        $this->bumpDashboardCache();

        return $receipt->load(['warehouse', 'items.commodity', 'receiver', 'verifier']);
    }

    // -------------------------------------------------------------------------
    // 4. Inter-Warehouse Stock Transfer Workflow
    // -------------------------------------------------------------------------

    public function requestTransfer(array $data, User $actor): WarehouseTransfer
    {
        $idempotencyKey = ! empty($data['idempotency_key']) ? trim((string) $data['idempotency_key']) : null;
        if ($idempotencyKey) {
            $existing = WarehouseTransfer::where('idempotency_key', $idempotencyKey)
                ->with(['sourceWarehouse', 'destinationWarehouse', 'items.commodity', 'requester', 'approver', 'dispatcher', 'receiver'])
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        $source = Warehouse::with('administrativeUnit')->findOrFail($data['source_warehouse_id']);
        $destination = Warehouse::with('administrativeUnit')->findOrFail($data['destination_warehouse_id']);

        Gate::forUser($actor)->authorize('requestTransfer', [WarehouseTransfer::class, $source, $destination]);

        // Pre-validate unreserved availability in source warehouse
        foreach ($data['items'] as $idx => $item) {
            $available = $this->ledgerService->getUnreservedStock($source->id, $item['commodity_id']);
            if ($available + 0.0001 < (float) $item['quantity_requested']) {
                throw ValidationException::withMessages([
                    "items.{$idx}.quantity_requested" => [
                        sprintf(
                            'Insufficient unreserved stock in %s (requested %.2f, available %.2f).',
                            $source->warehouse_name,
                            (float) $item['quantity_requested'],
                            $available
                        ),
                    ],
                ]);
            }
        }

        $transfer = DB::transaction(function () use ($data, $actor, $source, $destination, $idempotencyKey) {
            $transferNo = $this->generateUniqueSerial('TRF', WarehouseTransfer::class, 'transfer_no');

            $transfer = WarehouseTransfer::create([
                'transfer_no' => $transferNo,
                'source_warehouse_id' => $source->id,
                'destination_warehouse_id' => $destination->id,
                'status' => WarehouseTransfer::STATUS_REQUESTED,
                'requested_date' => $data['requested_date'],
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'vehicle_info' => $data['vehicle_info'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'requested_by' => $actor->id,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                WarehouseTransferItem::create([
                    'warehouse_transfer_id' => $transfer->id,
                    'commodity_id' => $item['commodity_id'],
                    'batch_number' => ! empty($item['batch_number']) ? strtoupper(trim($item['batch_number'])) : null,
                    'quantity_requested' => round((float) $item['quantity_requested'], 2),
                    'quantity_dispatched' => 0,
                    'quantity_received' => 0,
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            AuditLog::log(
                'WAREHOUSE_TRANSFER_REQUESTED',
                WarehouseTransfer::class,
                $transfer->id,
                null,
                ['transfer_no' => $transfer->transfer_no, 'source_warehouse_id' => $source->id, 'destination_warehouse_id' => $destination->id],
                $actor,
                $source->administrative_unit_id
            );

            return $transfer;
        });

        $this->bumpDashboardCache();

        return $transfer->load(['sourceWarehouse', 'destinationWarehouse', 'items.commodity', 'requester', 'approver', 'dispatcher', 'receiver']);
    }

    public function approveTransfer(WarehouseTransfer $transfer, User $actor): WarehouseTransfer
    {
        Gate::forUser($actor)->authorize('approveTransfer', $transfer);

        if ($transfer->status !== WarehouseTransfer::STATUS_REQUESTED) {
            throw ValidationException::withMessages([
                'status' => ["Only REQUESTED transfers can be approved (current: {$transfer->status})."],
            ]);
        }

        $transfer->update([
            'status' => WarehouseTransfer::STATUS_APPROVED,
            'approved_by' => $actor->id,
            'approved_at' => now(),
        ]);

        AuditLog::log(
            'WAREHOUSE_TRANSFER_APPROVED',
            WarehouseTransfer::class,
            $transfer->id,
            ['status' => WarehouseTransfer::STATUS_REQUESTED],
            ['status' => WarehouseTransfer::STATUS_APPROVED, 'approved_by' => $actor->id],
            $actor,
            $transfer->sourceWarehouse?->administrative_unit_id
        );

        $this->bumpDashboardCache();

        return $transfer->load(['sourceWarehouse', 'destinationWarehouse', 'items.commodity', 'requester', 'approver', 'dispatcher', 'receiver']);
    }

    public function dispatchTransfer(
        WarehouseTransfer $transfer,
        User $actor,
        ?string $dispatchReference = null,
        ?string $vehicleInfo = null
    ): WarehouseTransfer {
        Gate::forUser($actor)->authorize('dispatchTransfer', $transfer);

        if ($transfer->status !== WarehouseTransfer::STATUS_APPROVED) {
            throw ValidationException::withMessages([
                'status' => ["Transfer must be APPROVED before dispatch (current: {$transfer->status})."],
            ]);
        }

        $dispatched = DB::transaction(function () use ($transfer, $actor, $dispatchReference, $vehicleInfo) {
            $locked = WarehouseTransfer::with(['sourceWarehouse', 'items'])->whereKey($transfer->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== WarehouseTransfer::STATUS_APPROVED) {
                throw ValidationException::withMessages([
                    'status' => ['Transfer has already been dispatched or modified.'],
                ]);
            }

            foreach ($locked->items as $item) {
                $batches = $this->ledgerService->transferOut(
                    $locked,
                    $locked->source_warehouse_id,
                    $item->commodity_id,
                    (float) $item->quantity_requested,
                    $actor,
                    $item->batch_number,
                    $item->notes
                );

                $firstBatch = $batches[0] ?? null;
                $item->update([
                    'quantity_dispatched' => (float) $item->quantity_requested,
                    'batch_number' => $item->batch_number ?: ($firstBatch['batch_number'] ?? null),
                    'unit_cost_bdt' => $firstBatch['unit_cost_bdt'] ?? null,
                    'expiry_date' => $firstBatch['expiry_date'] ?? null,
                ]);
            }

            $locked->update([
                'status' => WarehouseTransfer::STATUS_DISPATCHED,
                'dispatch_reference' => $dispatchReference ?? $locked->dispatch_reference,
                'vehicle_info' => $vehicleInfo ?? $locked->vehicle_info,
                'dispatched_by' => $actor->id,
                'dispatched_at' => now(),
            ]);

            AuditLog::log(
                'WAREHOUSE_TRANSFER_DISPATCHED',
                WarehouseTransfer::class,
                $locked->id,
                ['status' => WarehouseTransfer::STATUS_APPROVED],
                ['status' => WarehouseTransfer::STATUS_DISPATCHED, 'dispatched_by' => $actor->id],
                $actor,
                $locked->sourceWarehouse?->administrative_unit_id
            );

            return $locked;
        });

        $this->bumpDashboardCache();

        return $dispatched->load(['sourceWarehouse', 'destinationWarehouse', 'items.commodity', 'requester', 'approver', 'dispatcher', 'receiver']);
    }

    public function receiveTransfer(WarehouseTransfer $transfer, User $actor, array $receivedQuantities = [], ?string $notes = null): WarehouseTransfer
    {
        Gate::forUser($actor)->authorize('receiveTransfer', $transfer);

        if ($transfer->status !== WarehouseTransfer::STATUS_DISPATCHED) {
            throw ValidationException::withMessages([
                'status' => ["Transfer must be DISPATCHED before receipt confirmation (current: {$transfer->status})."],
            ]);
        }

        $received = DB::transaction(function () use ($transfer, $actor, $receivedQuantities, $notes) {
            $locked = WarehouseTransfer::with(['destinationWarehouse', 'items'])->whereKey($transfer->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== WarehouseTransfer::STATUS_DISPATCHED) {
                throw ValidationException::withMessages([
                    'status' => ['Transfer has already been confirmed as received.'],
                ]);
            }

            foreach ($locked->items as $item) {
                $qtyToReceive = array_key_exists($item->id, $receivedQuantities)
                    ? round((float) $receivedQuantities[$item->id], 2)
                    : (float) $item->quantity_dispatched;

                if ($qtyToReceive <= 0 || $qtyToReceive > (float) $item->quantity_dispatched + 0.0001) {
                    throw ValidationException::withMessages([
                        'items' => [
                            sprintf(
                                'Received quantity (%.2f) must be > 0 and cannot exceed dispatched quantity (%.2f).',
                                $qtyToReceive,
                                (float) $item->quantity_dispatched
                            ),
                        ],
                    ]);
                }

                $destBatch = $item->batch_number ?: ($locked->transfer_no . '-' . substr($item->id, 0, 6));

                $this->ledgerService->transferIn(
                    $locked,
                    $locked->destination_warehouse_id,
                    $item->commodity_id,
                    $destBatch,
                    $qtyToReceive,
                    $actor,
                    $item->expiry_date?->toDateString(),
                    $item->unit_cost_bdt !== null ? (float) $item->unit_cost_bdt : null,
                    $notes
                );

                $item->update([
                    'quantity_received' => $qtyToReceive,
                ]);
            }

            $locked->update([
                'status' => WarehouseTransfer::STATUS_RECEIVED,
                'received_by' => $actor->id,
                'received_at' => now(),
                'notes' => $notes ? trim(($locked->notes ? $locked->notes . ' | ' : '') . $notes) : $locked->notes,
            ]);

            AuditLog::log(
                'WAREHOUSE_TRANSFER_RECEIVED',
                WarehouseTransfer::class,
                $locked->id,
                ['status' => WarehouseTransfer::STATUS_DISPATCHED],
                ['status' => WarehouseTransfer::STATUS_RECEIVED, 'received_by' => $actor->id],
                $actor,
                $locked->destinationWarehouse?->administrative_unit_id
            );

            return $locked;
        });

        $this->bumpDashboardCache();

        return $received->load(['sourceWarehouse', 'destinationWarehouse', 'items.commodity', 'requester', 'approver', 'dispatcher', 'receiver']);
    }

    public function cancelTransfer(WarehouseTransfer $transfer, User $actor, string $reason): WarehouseTransfer
    {
        Gate::forUser($actor)->authorize('approveTransfer', $transfer);

        if (! in_array($transfer->status, [WarehouseTransfer::STATUS_REQUESTED, WarehouseTransfer::STATUS_APPROVED], true)) {
            throw ValidationException::withMessages([
                'status' => ['Only REQUESTED or APPROVED transfers can be cancelled before dispatch.'],
            ]);
        }

        $oldStatus = $transfer->status;
        $transfer->update([
            'status' => WarehouseTransfer::STATUS_CANCELLED,
            'cancellation_reason' => $reason,
        ]);

        AuditLog::log(
            'WAREHOUSE_TRANSFER_CANCELLED',
            WarehouseTransfer::class,
            $transfer->id,
            ['status' => $oldStatus],
            ['status' => WarehouseTransfer::STATUS_CANCELLED, 'cancellation_reason' => $reason],
            $actor,
            $transfer->sourceWarehouse?->administrative_unit_id
        );

        $this->bumpDashboardCache();

        return $transfer->load(['sourceWarehouse', 'destinationWarehouse', 'items.commodity', 'requester', 'approver', 'dispatcher', 'receiver']);
    }

    // -------------------------------------------------------------------------
    // 5. Stock Adjustments, Opening Balance, Returns & Dual-Auth Write-offs
    // -------------------------------------------------------------------------

    public function recordManualStockMovement(array $data, User $actor): StockTransaction
    {
        $warehouse = Warehouse::with('administrativeUnit')->findOrFail($data['warehouse_id']);
        $type = $data['transaction_type'];
        $quantity = round((float) $data['quantity'], 2);
        $batch = strtoupper(trim($data['batch_number']));
        $idempotencyKey = ! empty($data['idempotency_key']) ? trim((string) $data['idempotency_key']) : null;

        if (in_array($type, [StockTransaction::TYPE_OPENING_STOCK, StockTransaction::TYPE_RETURN_IN], true)) {
            if ($quantity <= 0) {
                throw ValidationException::withMessages([
                    'quantity' => ['Quantity must be greater than zero for opening stock and returns.'],
                ]);
            }

            Gate::forUser($actor)->authorize('receiveStock', [StockReceipt::class, $warehouse]);

            $tx = $type === StockTransaction::TYPE_OPENING_STOCK
                ? $this->ledgerService->recordOpeningStock(
                    $warehouse->id,
                    $data['commodity_id'],
                    $batch,
                    $quantity,
                    $data['reference_voucher_no'],
                    $actor,
                    $data['expiry_date'] ?? null,
                    isset($data['unit_cost_bdt']) ? (float) $data['unit_cost_bdt'] : null,
                    $data['reason_code'],
                    $data['notes'],
                    $idempotencyKey
                )
                : $this->ledgerService->recordReturnStock(
                    $warehouse->id,
                    $data['commodity_id'],
                    $batch,
                    $quantity,
                    $data['reference_voucher_no'],
                    $data['reason_code'],
                    $actor,
                    $data['expiry_date'] ?? null,
                    isset($data['unit_cost_bdt']) ? (float) $data['unit_cost_bdt'] : null,
                    $data['notes'],
                    $idempotencyKey
                );
        } elseif (in_array($type, [StockTransaction::TYPE_WRITE_OFF_DAMAGED, StockTransaction::TYPE_EXPIRED_STOCK], true)) {
            if ($quantity <= 0) {
                throw ValidationException::withMessages([
                    'quantity' => ['Write-off quantity must be a positive magnitude (system records the deduction).'],
                ]);
            }

            $approver = ! empty($data['approved_by']) ? User::find($data['approved_by']) : null;
            Gate::forUser($actor)->authorize('writeOffOrAdjust', [$warehouse, $approver, true]);

            $tx = $this->ledgerService->markDamagedOrExpired(
                $warehouse->id,
                $data['commodity_id'],
                $batch,
                $quantity,
                $type,
                $data['reference_voucher_no'],
                $data['reason_code'],
                $actor,
                $approver,
                $data['notes'],
                $idempotencyKey
            );
        } else {
            // STOCK_ADJUSTMENT: requires writeoff permission; negative or all adjustments require dual auth
            $approver = ! empty($data['approved_by']) ? User::find($data['approved_by']) : null;
            Gate::forUser($actor)->authorize('writeOffOrAdjust', [$warehouse, $approver, true]);

            $tx = $this->ledgerService->adjustStock(
                $warehouse->id,
                $data['commodity_id'],
                $batch,
                $quantity,
                $data['reference_voucher_no'],
                $data['reason_code'],
                $actor,
                $approver,
                $data['notes'],
                $data['expiry_date'] ?? null,
                isset($data['unit_cost_bdt']) ? (float) $data['unit_cost_bdt'] : null,
                $idempotencyKey
            );
        }

        AuditLog::log(
            'WAREHOUSE_LEDGER_' . $type,
            StockTransaction::class,
            $tx->id,
            null,
            [
                'warehouse_id' => $warehouse->id,
                'commodity_id' => $data['commodity_id'],
                'batch_number' => $batch,
                'quantity' => $tx->quantity,
                'balance_after' => $tx->balance_after,
                'reason_code' => $data['reason_code'],
            ],
            $actor,
            $warehouse->administrative_unit_id
        );

        $this->bumpDashboardCache();

        return $tx->load(['warehouseInventory.warehouse', 'warehouseInventory.commodity', 'warehouse', 'commodity', 'authorizer', 'approver']);
    }

    // -------------------------------------------------------------------------
    // 6. Relief Allocation Integration (Prevent Double-Deduction)
    // -------------------------------------------------------------------------

    public function issueAgainstReliefAllocation(ReliefAllocation $allocation, array $data, User $actor): ReliefAllocation
    {
        $allocation->loadMissing(['warehouse.administrativeUnit', 'items.commodity', 'administrativeUnit']);

        if (! $allocation->warehouse) {
            throw ValidationException::withMessages([
                'warehouse_id' => ['Relief allocation has no assigned source warehouse.'],
            ]);
        }

        Gate::forUser($actor)->authorize('issueStock', [Warehouse::class, $allocation->warehouse]);

        if (! in_array($allocation->status, [ReliefAllocation::STATUS_SANCTIONED, ReliefAllocation::STATUS_DISPATCHED], true)) {
            throw ValidationException::withMessages([
                'status' => ["Stock can only be issued against SANCTIONED or DISPATCHED relief allocations (current status: {$allocation->status})."],
            ]);
        }

        $idempotencyKey = ! empty($data['idempotency_key']) ? trim((string) $data['idempotency_key']) : null;
        if ($idempotencyKey && StockTransaction::where('idempotency_key', $idempotencyKey)->exists()) {
            return $allocation->fresh(['warehouse', 'administrativeUnit', 'disasterEvent', 'items.commodity']);
        }

        $updatedAllocation = DB::transaction(function () use ($allocation, $data, $actor, $idempotencyKey) {
            $lockedAlloc = ReliefAllocation::with(['warehouse', 'items'])->whereKey($allocation->id)->lockForUpdate()->firstOrFail();

            if (! in_array($lockedAlloc->status, [ReliefAllocation::STATUS_SANCTIONED, ReliefAllocation::STATUS_DISPATCHED], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Allocation has already been completed or cancelled.'],
                ]);
            }

            $voucherRef = ! empty($data['reference_voucher_no'])
                ? trim($data['reference_voucher_no'])
                : ($lockedAlloc->allocation_order_no . '-ISSUE');

            $itemsById = $lockedAlloc->items->keyBy('id');

            foreach ($data['items'] as $idx => $reqItem) {
                $allocItemId = $reqItem['relief_allocation_item_id'];
                /** @var ReliefAllocationItem|null $allocItem */
                $allocItem = $itemsById->get($allocItemId);

                if (! $allocItem) {
                    throw ValidationException::withMessages([
                        "items.{$idx}.relief_allocation_item_id" => ['Allocation line item does not belong to this relief allocation order.'],
                    ]);
                }

                $qtyToIssue = round((float) $reqItem['quantity_to_issue'], 2);
                $remaining = max(
                    0.0,
                    round((float) $allocItem->quantity_allocated - (float) $allocItem->quantity_distributed - (float) $allocItem->quantity_cancelled, 2)
                );

                if ($qtyToIssue > $remaining + 0.0001) {
                    throw ValidationException::withMessages([
                        "items.{$idx}.quantity_to_issue" => [
                            sprintf(
                                'Cannot issue %.2f units — only %.2f remaining on allocation %s (prevents double-deduction).',
                                $qtyToIssue,
                                $remaining,
                                $lockedAlloc->allocation_order_no
                            ),
                        ],
                    ]);
                }

                $itemIdemKey = $idempotencyKey ? ($idx === 0 ? $idempotencyKey : "{$idempotencyKey}-{$idx}") : null;

                $this->ledgerService->deductStock(
                    $lockedAlloc->warehouse_id,
                    $allocItem->commodity_id,
                    $qtyToIssue,
                    $voucherRef,
                    $actor,
                    $allocItem->id,
                    null,
                    $data['notes'] ?? "Warehouse stock issued against allocation {$lockedAlloc->allocation_order_no}",
                    StockTransaction::TYPE_STOCK_ISSUE,
                    'RELIEF_ALLOCATION',
                    $itemIdemKey
                );

                $allocItem->update([
                    'quantity_distributed' => round((float) $allocItem->quantity_distributed + $qtyToIssue, 2),
                ]);
            }

            $lockedAlloc->refresh();
            $allSettled = $lockedAlloc->items->every(function (ReliefAllocationItem $item) {
                $rem = (float) $item->quantity_allocated - (float) $item->quantity_distributed - (float) $item->quantity_cancelled;

                return $rem <= 0.0001;
            });

            $oldStatus = $lockedAlloc->status;
            $newStatus = $allSettled ? ReliefAllocation::STATUS_COMPLETED : ReliefAllocation::STATUS_DISPATCHED;

            $lockedAlloc->update([
                'status' => $newStatus,
                'updated_by' => $actor->id,
            ]);

            AuditLog::log(
                'WAREHOUSE_RELIEF_STOCK_ISSUED',
                ReliefAllocation::class,
                $lockedAlloc->id,
                ['status' => $oldStatus],
                ['status' => $newStatus, 'reference_voucher_no' => $voucherRef],
                $actor,
                $lockedAlloc->warehouse?->administrative_unit_id
            );

            return $lockedAlloc;
        });

        $this->bumpDashboardCache();

        return $updatedAllocation->load(['warehouse', 'administrativeUnit', 'disasterEvent', 'items.commodity']);
    }

    // -------------------------------------------------------------------------
    // 7. Real-Time Alerts, Dashboard Analytics & 6 Reporting Views
    // -------------------------------------------------------------------------

    public function getStockAlerts(User $user, int $expiryWarningDays = 30): array
    {
        $today = now()->startOfDay();
        $warningCutoff = $today->copy()->addDays(max(1, $expiryWarningDays));

        $warehouseIds = Warehouse::query()
            ->supplyingJurisdiction($user)
            ->pluck('id');

        $lots = WarehouseInventory::query()
            ->whereIn('warehouse_id', $warehouseIds)
            ->with(['warehouse', 'commodity'])
            ->get();

        $lowStockAlerts = [];
        $outOfStockAlerts = [];
        $expiredAlerts = [];
        $expiringSoonAlerts = [];

        // Check lot-level expiry first
        foreach ($lots as $lot) {
            if ((float) $lot->current_quantity <= 0 || ! $lot->expiry_date) {
                continue;
            }

            $expiry = $lot->expiry_date->copy()->startOfDay();
            $daysRemaining = (int) $today->diffInDays($expiry, false);

            $basePayload = [
                'warehouse_id' => $lot->warehouse_id,
                'warehouse_code' => $lot->warehouse?->warehouse_code,
                'warehouse_name' => $lot->warehouse?->warehouse_name,
                'warehouse_name_bn' => $lot->warehouse?->warehouse_name_bn,
                'commodity_id' => $lot->commodity_id,
                'commodity_code' => $lot->commodity?->commodity_code,
                'commodity_name_en' => $lot->commodity?->name_en,
                'commodity_name_bn' => $lot->commodity?->name_bn,
                'unit_of_measure' => $lot->commodity?->unit_of_measure,
                'batch_number' => $lot->batch_number,
                'current_quantity' => (float) $lot->current_quantity,
                'available_quantity' => $lot->available_quantity,
                'expiry_date' => $lot->expiry_date->toDateString(),
                'days_to_expiry' => $daysRemaining,
            ];

            if ($expiry->lt($today)) {
                $expiredAlerts[] = array_merge($basePayload, ['alert_type' => 'EXPIRED', 'severity' => 'CRITICAL']);
            } elseif ($expiry->lte($warningCutoff)) {
                $expiringSoonAlerts[] = array_merge($basePayload, ['alert_type' => 'EXPIRING_SOON', 'severity' => 'WARNING']);
            }
        }

        // Check commodity stock thresholds across scoped warehouses + stocked lots
        $commodities = Commodity::query()->where('is_active', true)->get();
        $lotsByWarehouseAndCommodity = $lots->groupBy(fn ($lot) => $lot->warehouse_id . '::' . $lot->commodity_id);

        foreach ($lotsByWarehouseAndCommodity as $groupLots) {
            $first = $groupLots->first();
            $commodity = $first?->commodity;
            $warehouse = $first?->warehouse;
            if (! $commodity || ! $warehouse) {
                continue;
            }

            $minLevel = (float) ($commodity->minimum_stock_level ?? 0);
            $physical = round((float) $groupLots->sum('current_quantity'), 2);
            $reserved = round((float) $groupLots->sum('reserved_quantity'), 2);
            $available = max(0.0, round($physical - $reserved, 2));

            if ($available <= 0.0001) {
                $outOfStockAlerts[] = [
                    'alert_type' => 'OUT_OF_STOCK',
                    'severity' => 'CRITICAL',
                    'warehouse_id' => $warehouse->id,
                    'warehouse_code' => $warehouse->warehouse_code,
                    'warehouse_name' => $warehouse->warehouse_name,
                    'warehouse_name_bn' => $warehouse->warehouse_name_bn,
                    'commodity_id' => $commodity->id,
                    'commodity_code' => $commodity->commodity_code,
                    'commodity_name_en' => $commodity->name_en,
                    'commodity_name_bn' => $commodity->name_bn,
                    'unit_of_measure' => $commodity->unit_of_measure,
                    'current_quantity' => $physical,
                    'available_quantity' => $available,
                    'minimum_stock_level' => $minLevel,
                ];
            } elseif ($minLevel > 0 && $available < $minLevel) {
                $lowStockAlerts[] = [
                    'alert_type' => 'LOW_STOCK',
                    'severity' => 'WARNING',
                    'warehouse_id' => $warehouse->id,
                    'warehouse_code' => $warehouse->warehouse_code,
                    'warehouse_name' => $warehouse->warehouse_name,
                    'warehouse_name_bn' => $warehouse->warehouse_name_bn,
                    'commodity_id' => $commodity->id,
                    'commodity_code' => $commodity->commodity_code,
                    'commodity_name_en' => $commodity->name_en,
                    'commodity_name_bn' => $commodity->name_bn,
                    'unit_of_measure' => $commodity->unit_of_measure,
                    'current_quantity' => $physical,
                    'available_quantity' => $available,
                    'minimum_stock_level' => $minLevel,
                ];
            }
        }

        // Also report any active commodity with minimum_stock_level > 0 that has zero total available across scoped warehouses
        $trackedCommodityIdsWithWarehouseAlerts = collect($outOfStockAlerts)
            ->merge($lowStockAlerts)
            ->pluck('commodity_id')
            ->unique();

        foreach ($commodities as $commodity) {
            $minLevel = (float) ($commodity->minimum_stock_level ?? 0);
            if ($minLevel <= 0 || $trackedCommodityIdsWithWarehouseAlerts->contains($commodity->id)) {
                continue;
            }

            $totalPhysical = round((float) $lots->where('commodity_id', $commodity->id)->sum('current_quantity'), 2);
            $totalReserved = round((float) $lots->where('commodity_id', $commodity->id)->sum('reserved_quantity'), 2);
            $totalAvailable = max(0.0, round($totalPhysical - $totalReserved, 2));

            if ($totalAvailable <= 0.0001) {
                $outOfStockAlerts[] = [
                    'alert_type' => 'OUT_OF_STOCK',
                    'severity' => 'CRITICAL',
                    'warehouse_id' => null,
                    'warehouse_code' => 'SCOPED_NETWORK',
                    'warehouse_name' => 'All Scoped Warehouses',
                    'warehouse_name_bn' => 'সকল আওতাভুক্ত গুদাম',
                    'commodity_id' => $commodity->id,
                    'commodity_code' => $commodity->commodity_code,
                    'commodity_name_en' => $commodity->name_en,
                    'commodity_name_bn' => $commodity->name_bn,
                    'unit_of_measure' => $commodity->unit_of_measure,
                    'current_quantity' => $totalPhysical,
                    'available_quantity' => $totalAvailable,
                    'minimum_stock_level' => $minLevel,
                ];
            } elseif ($totalAvailable < $minLevel) {
                $lowStockAlerts[] = [
                    'alert_type' => 'LOW_STOCK',
                    'severity' => 'WARNING',
                    'warehouse_id' => null,
                    'warehouse_code' => 'SCOPED_NETWORK',
                    'warehouse_name' => 'All Scoped Warehouses',
                    'warehouse_name_bn' => 'সকল আওতাভুক্ত গুদাম',
                    'commodity_id' => $commodity->id,
                    'commodity_code' => $commodity->commodity_code,
                    'commodity_name_en' => $commodity->name_en,
                    'commodity_name_bn' => $commodity->name_bn,
                    'unit_of_measure' => $commodity->unit_of_measure,
                    'current_quantity' => $totalPhysical,
                    'available_quantity' => $totalAvailable,
                    'minimum_stock_level' => $minLevel,
                ];
            }
        }

        // Unusual stock adjustments / write-offs in last 7 days
        $unusualAdjustments = StockTransaction::query()
            ->whereIn('transaction_type', [
                StockTransaction::TYPE_STOCK_ADJUSTMENT,
                StockTransaction::TYPE_WRITE_OFF_DAMAGED,
                StockTransaction::TYPE_EXPIRED_STOCK,
            ])
            ->where('created_at', '>=', now()->subDays(7))
            ->whereHas('warehouseInventory', fn ($q) => $q->whereIn('warehouse_id', $warehouseIds))
            ->with(['warehouseInventory.warehouse', 'warehouseInventory.commodity'])
            ->latest('created_at')
            ->get()
            ->filter(function (StockTransaction $tx) {
                $commodity = $tx->warehouseInventory?->commodity;
                $minLevel = (float) ($commodity?->minimum_stock_level ?? 0);
                $absQty = abs((float) $tx->quantity);

                return $absQty >= 50 || ($minLevel > 0 && $absQty >= ($minLevel * 0.25));
            })
            ->map(function (StockTransaction $tx) {
                $wh = $tx->warehouseInventory?->warehouse;
                $com = $tx->warehouseInventory?->commodity;

                return [
                    'alert_type' => 'UNUSUAL_ADJUSTMENT',
                    'severity' => 'HIGH',
                    'transaction_id' => $tx->id,
                    'transaction_type' => $tx->transaction_type,
                    'reference_voucher_no' => $tx->reference_voucher_no,
                    'reason_code' => $tx->reason_code,
                    'quantity' => (float) $tx->quantity,
                    'warehouse_id' => $wh?->id,
                    'warehouse_name' => $wh?->warehouse_name,
                    'commodity_id' => $com?->id,
                    'commodity_name_en' => $com?->name_en,
                    'commodity_name_bn' => $com?->name_bn,
                    'created_at' => $tx->created_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        return [
            'summary' => [
                'low_stock_count' => count($lowStockAlerts),
                'out_of_stock_count' => count($outOfStockAlerts),
                'expiring_soon_count' => count($expiringSoonAlerts),
                'expired_count' => count($expiredAlerts),
                'unusual_adjustment_count' => count($unusualAdjustments),
                'total_alerts' => count($lowStockAlerts)
                    + count($outOfStockAlerts)
                    + count($expiringSoonAlerts)
                    + count($expiredAlerts)
                    + count($unusualAdjustments),
            ],
            'low_stock' => $lowStockAlerts,
            'out_of_stock' => $outOfStockAlerts,
            'expiring_soon' => $expiringSoonAlerts,
            'expired' => $expiredAlerts,
            'unusual_adjustments' => $unusualAdjustments,
        ];
    }

    public function getDashboardAnalytics(User $user): array
    {
        $version = Cache::get(self::DASHBOARD_CACHE_VERSION_KEY, 1);
        $scopePath = $user->getAdministrativeScope()['ancestry_path'] ?? '/';
        $cacheKey = 'warehouse:dashboard:v' . $version . ':' . md5($user->id . '|' . $scopePath);

        return Cache::remember($cacheKey, now()->addSeconds(120), function () use ($user) {
            $warehouses = Warehouse::query()
                ->supplyingJurisdiction($user)
                ->with(['inventories.commodity', 'administrativeUnit'])
                ->get();

            $warehouseIds = $warehouses->pluck('id');

            $totalPhysical = 0.0;
            $totalReserved = 0.0;
            $totalDamaged = 0.0;
            $totalExpired = 0.0;
            $totalValuation = 0.0;

            foreach ($warehouses as $wh) {
                foreach ($wh->inventories as $lot) {
                    $qty = (float) $lot->current_quantity;
                    $totalPhysical += $qty;
                    $totalReserved += (float) $lot->reserved_quantity;
                    $totalDamaged += (float) ($lot->damaged_quantity ?? 0);
                    $totalExpired += (float) ($lot->expired_quantity ?? 0);
                    $totalValuation += $qty * (float) ($lot->unit_cost_bdt ?? 0);
                }
            }

            $pendingGrnCount = StockReceipt::query()
                ->whereIn('warehouse_id', $warehouseIds)
                ->where('status', StockReceipt::STATUS_PENDING_VERIFICATION)
                ->count();

            $activeTransferCount = WarehouseTransfer::query()
                ->where(function ($q) use ($warehouseIds) {
                    $q->whereIn('source_warehouse_id', $warehouseIds)
                        ->orWhereIn('destination_warehouse_id', $warehouseIds);
                })
                ->whereIn('status', [
                    WarehouseTransfer::STATUS_REQUESTED,
                    WarehouseTransfer::STATUS_APPROVED,
                    WarehouseTransfer::STATUS_DISPATCHED,
                ])
                ->count();

            $alerts = $this->getStockAlerts($user);

            $recentTransactions = StockTransaction::query()
                ->whereHas('warehouseInventory', fn ($q) => $q->whereIn('warehouse_id', $warehouseIds))
                ->with(['warehouseInventory.warehouse', 'warehouseInventory.commodity', 'authorizer'])
                ->latest('created_at')
                ->limit(10)
                ->get()
                ->map(fn (StockTransaction $tx) => [
                    'id' => $tx->id,
                    'transaction_type' => $tx->transaction_type,
                    'quantity' => (float) $tx->quantity,
                    'balance_after' => (float) $tx->balance_after,
                    'reference_voucher_no' => $tx->reference_voucher_no,
                    'warehouse_name' => $tx->warehouseInventory?->warehouse?->warehouse_name,
                    'commodity_name_en' => $tx->warehouseInventory?->commodity?->name_en,
                    'commodity_name_bn' => $tx->warehouseInventory?->commodity?->name_bn,
                    'unit_of_measure' => $tx->warehouseInventory?->commodity?->unit_of_measure,
                    'created_at' => $tx->created_at?->toIso8601String(),
                ])
                ->values()
                ->all();

            return [
                'kpis' => [
                    'total_warehouses' => $warehouses->count(),
                    'active_warehouses' => $warehouses->where('is_active', true)->count(),
                    'total_capacity_mt' => round((float) $warehouses->sum('capacity_mt'), 2),
                    'total_physical_stock' => round($totalPhysical, 2),
                    'total_reserved_stock' => round($totalReserved, 2),
                    'total_available_stock' => max(0.0, round($totalPhysical - $totalReserved, 2)),
                    'total_damaged_stock' => round($totalDamaged, 2),
                    'total_expired_stock' => round($totalExpired, 2),
                    'total_valuation_bdt' => round($totalValuation, 2),
                    'pending_grn_verifications' => $pendingGrnCount,
                    'active_transfers' => $activeTransferCount,
                    'total_alerts' => $alerts['summary']['total_alerts'],
                ],
                'alerts_summary' => $alerts['summary'],
                'warehouses_by_tier' => $warehouses->groupBy('tier')->map->count(),
                'recent_transactions' => $recentTransactions,
            ];
        });
    }

    public function generateReport(string $reportType, User $user, array $filters = []): array
    {
        $warehouseIds = Warehouse::query()
            ->supplyingJurisdiction($user)
            ->when(! empty($filters['warehouse_id']), fn ($q) => $q->where('id', $filters['warehouse_id']))
            ->pluck('id');

        $normalized = strtoupper(trim($reportType));

        return match ($normalized) {
            'STOCK_BALANCE' => $this->reportStockBalance($warehouseIds, $filters),
            'STOCK_MOVEMENT' => $this->reportStockMovement($warehouseIds, $filters),
            'WAREHOUSE_INVENTORY' => $this->reportWarehouseInventory($warehouseIds),
            'ITEM_INVENTORY' => $this->reportItemInventory($warehouseIds, $filters),
            'EXPIRY' => $this->reportExpiry($warehouseIds, (int) ($filters['days'] ?? 60)),
            'TRANSACTION_HISTORY' => $this->reportTransactionHistory($warehouseIds, $filters),
            default => throw ValidationException::withMessages([
                'report_type' => ["Unsupported report type: {$reportType}"],
            ]),
        };
    }

    public function generateLedgerCsv(User $user, array $filters = []): string
    {
        $warehouseIds = Warehouse::query()
            ->supplyingJurisdiction($user)
            ->when(! empty($filters['warehouse_id']), fn ($q) => $q->where('id', $filters['warehouse_id']))
            ->pluck('id');

        $rows = StockTransaction::query()
            ->whereHas('warehouseInventory', fn ($q) => $q->whereIn('warehouse_id', $warehouseIds))
            ->when(! empty($filters['commodity_id']), fn ($q) => $q->whereHas('warehouseInventory', fn ($iq) => $iq->where('commodity_id', $filters['commodity_id'])))
            ->when(! empty($filters['transaction_type']), fn ($q) => $q->where('transaction_type', $filters['transaction_type']))
            ->when(! empty($filters['date_from']), fn ($q) => $q->whereDate('created_at', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($q) => $q->whereDate('created_at', '<=', $filters['date_to']))
            ->with(['warehouseInventory.warehouse', 'warehouseInventory.commodity', 'authorizer', 'approver'])
            ->latest('created_at')
            ->limit(5000)
            ->get();

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [
            'Transaction ID',
            'Timestamp (UTC)',
            'Warehouse Code',
            'Warehouse Name',
            'Commodity Code',
            'Commodity (EN)',
            'Commodity (BN)',
            'Batch Number',
            'Transaction Type',
            'Reference Type',
            'Reason Code',
            'Quantity',
            'Balance After',
            'Reference Voucher',
            'Authorizer',
            'Co-Approver',
            'Notes',
        ]);

        foreach ($rows as $tx) {
            $wh = $tx->warehouseInventory?->warehouse;
            $com = $tx->warehouseInventory?->commodity;
            fputcsv($handle, [
                $this->sanitizeCsvCell($tx->id),
                $this->sanitizeCsvCell($tx->created_at?->toIso8601String() ?? ''),
                $this->sanitizeCsvCell($wh?->warehouse_code ?? ''),
                $this->sanitizeCsvCell($wh?->warehouse_name ?? ''),
                $this->sanitizeCsvCell($com?->commodity_code ?? ''),
                $this->sanitizeCsvCell($com?->name_en ?? ''),
                $this->sanitizeCsvCell($com?->name_bn ?? ''),
                $this->sanitizeCsvCell($tx->warehouseInventory?->batch_number ?? ''),
                $this->sanitizeCsvCell($tx->transaction_type),
                $this->sanitizeCsvCell($tx->reference_type ?? ''),
                $this->sanitizeCsvCell($tx->reason_code ?? ''),
                $tx->quantity,
                $tx->balance_after,
                $this->sanitizeCsvCell($tx->reference_voucher_no),
                $this->sanitizeCsvCell($tx->authorizer?->full_name_en ?? ''),
                $this->sanitizeCsvCell($tx->approver?->full_name_en ?? ''),
                $this->sanitizeCsvCell($tx->notes ?? ''),
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $csv;
    }

    private function sanitizeCsvCell(?string $value): string
    {
        $str = (string) ($value ?? '');
        if ($str !== '' && preg_match('/^[=+\-@\t\r]/', $str)) {
            return "'" . $str;
        }

        return $str;
    }

    private function reportStockBalance($warehouseIds, array $filters): array
    {
        $lots = WarehouseInventory::query()
            ->whereIn('warehouse_id', $warehouseIds)
            ->when(! empty($filters['commodity_id']), fn ($q) => $q->where('commodity_id', $filters['commodity_id']))
            ->with(['warehouse', 'commodity'])
            ->get();

        return $lots->groupBy(fn ($l) => $l->warehouse_id . '::' . $l->commodity_id)
            ->map(function ($group) {
                $first = $group->first();
                $physical = round((float) $group->sum('current_quantity'), 2);
                $reserved = round((float) $group->sum('reserved_quantity'), 2);
                $valuation = round((float) $group->sum(fn ($l) => (float) $l->current_quantity * (float) ($l->unit_cost_bdt ?? 0)), 2);

                return [
                    'warehouse_id' => $first->warehouse_id,
                    'warehouse_code' => $first->warehouse?->warehouse_code,
                    'warehouse_name' => $first->warehouse?->warehouse_name,
                    'commodity_id' => $first->commodity_id,
                    'commodity_code' => $first->commodity?->commodity_code,
                    'commodity_name_en' => $first->commodity?->name_en,
                    'commodity_name_bn' => $first->commodity?->name_bn,
                    'unit_of_measure' => $first->commodity?->unit_of_measure,
                    'opening_quantity' => round((float) $group->sum('opening_quantity'), 2),
                    'current_quantity' => $physical,
                    'reserved_quantity' => $reserved,
                    'available_quantity' => max(0.0, round($physical - $reserved, 2)),
                    'damaged_quantity' => round((float) $group->sum('damaged_quantity'), 2),
                    'expired_quantity' => round((float) $group->sum('expired_quantity'), 2),
                    'valuation_bdt' => $valuation,
                ];
            })
            ->values()
            ->all();
    }

    private function reportStockMovement($warehouseIds, array $filters): array
    {
        $txs = StockTransaction::query()
            ->whereHas('warehouseInventory', fn ($q) => $q->whereIn('warehouse_id', $warehouseIds))
            ->when(! empty($filters['commodity_id']), fn ($q) => $q->whereHas('warehouseInventory', fn ($iq) => $iq->where('commodity_id', $filters['commodity_id'])))
            ->when(! empty($filters['date_from']), fn ($q) => $q->whereDate('created_at', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($q) => $q->whereDate('created_at', '<=', $filters['date_to']))
            ->with(['warehouseInventory.warehouse', 'warehouseInventory.commodity'])
            ->get();

        return $txs->groupBy(fn ($tx) => ($tx->warehouseInventory?->warehouse_id ?? '') . '::' . ($tx->warehouseInventory?->commodity_id ?? ''))
            ->map(function ($group) {
                $first = $group->first();
                $wh = $first->warehouseInventory?->warehouse;
                $com = $first->warehouseInventory?->commodity;
                $totalIn = round((float) $group->filter(fn ($t) => (float) $t->quantity > 0)->sum('quantity'), 2);
                $totalOut = round(abs((float) $group->filter(fn ($t) => (float) $t->quantity < 0)->sum('quantity')), 2);

                return [
                    'warehouse_id' => $wh?->id,
                    'warehouse_code' => $wh?->warehouse_code,
                    'warehouse_name' => $wh?->warehouse_name,
                    'commodity_id' => $com?->id,
                    'commodity_code' => $com?->commodity_code,
                    'commodity_name_en' => $com?->name_en,
                    'commodity_name_bn' => $com?->name_bn,
                    'unit_of_measure' => $com?->unit_of_measure,
                    'inflow_quantity' => $totalIn,
                    'outflow_quantity' => $totalOut,
                    'net_change' => round($totalIn - $totalOut, 2),
                    'transaction_count' => $group->count(),
                ];
            })
            ->values()
            ->all();
    }

    private function reportWarehouseInventory($warehouseIds): array
    {
        return Warehouse::query()
            ->whereIn('id', $warehouseIds)
            ->with(['administrativeUnit', 'inventories'])
            ->orderBy('warehouse_name')
            ->get()
            ->map(function (Warehouse $wh) {
                $physical = round((float) $wh->inventories->sum('current_quantity'), 2);
                $reserved = round((float) $wh->inventories->sum('reserved_quantity'), 2);
                $valuation = round((float) $wh->inventories->sum(fn ($l) => (float) $l->current_quantity * (float) ($l->unit_cost_bdt ?? 0)), 2);

                return [
                    'warehouse_id' => $wh->id,
                    'warehouse_code' => $wh->warehouse_code,
                    'warehouse_name' => $wh->warehouse_name,
                    'warehouse_name_bn' => $wh->warehouse_name_bn,
                    'tier' => $wh->tier,
                    'jurisdiction' => $wh->administrativeUnit?->name_en,
                    'capacity_mt' => (float) $wh->capacity_mt,
                    'current_quantity' => $physical,
                    'reserved_quantity' => $reserved,
                    'available_quantity' => max(0.0, round($physical - $reserved, 2)),
                    'damaged_quantity' => round((float) $wh->inventories->sum('damaged_quantity'), 2),
                    'expired_quantity' => round((float) $wh->inventories->sum('expired_quantity'), 2),
                    'valuation_bdt' => $valuation,
                    'active_lots' => $wh->inventories->where('current_quantity', '>', 0)->count(),
                ];
            })
            ->values()
            ->all();
    }

    private function reportItemInventory($warehouseIds, array $filters): array
    {
        $lots = WarehouseInventory::query()
            ->whereIn('warehouse_id', $warehouseIds)
            ->get();

        return Commodity::query()
            ->when(! empty($filters['commodity_id']), fn ($q) => $q->where('id', $filters['commodity_id']))
            ->orderBy('name_en')
            ->get()
            ->map(function (Commodity $commodity) use ($lots) {
                $cLots = $lots->where('commodity_id', $commodity->id);
                $physical = round((float) $cLots->sum('current_quantity'), 2);
                $reserved = round((float) $cLots->sum('reserved_quantity'), 2);
                $available = max(0.0, round($physical - $reserved, 2));
                $minLevel = (float) ($commodity->minimum_stock_level ?? 0);

                return [
                    'commodity_id' => $commodity->id,
                    'commodity_code' => $commodity->commodity_code,
                    'name_en' => $commodity->name_en,
                    'name_bn' => $commodity->name_bn,
                    'category' => $commodity->category,
                    'unit_of_measure' => $commodity->unit_of_measure,
                    'minimum_stock_level' => $minLevel,
                    'current_quantity' => $physical,
                    'reserved_quantity' => $reserved,
                    'available_quantity' => $available,
                    'damaged_quantity' => round((float) $cLots->sum('damaged_quantity'), 2),
                    'expired_quantity' => round((float) $cLots->sum('expired_quantity'), 2),
                    'valuation_bdt' => round((float) $cLots->sum(fn ($l) => (float) $l->current_quantity * (float) ($l->unit_cost_bdt ?? 0)), 2),
                    'warehouse_count' => $cLots->where('current_quantity', '>', 0)->pluck('warehouse_id')->unique()->count(),
                    'stock_status' => $available <= 0
                        ? 'OUT_OF_STOCK'
                        : ($minLevel > 0 && $available < $minLevel ? 'LOW_STOCK' : 'HEALTHY'),
                ];
            })
            ->values()
            ->all();
    }

    private function reportExpiry($warehouseIds, int $days): array
    {
        $today = now()->startOfDay();
        $cutoff = $today->copy()->addDays(max(1, $days));

        return WarehouseInventory::query()
            ->whereIn('warehouse_id', $warehouseIds)
            ->where('current_quantity', '>', 0)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', $cutoff->toDateString())
            ->with(['warehouse', 'commodity'])
            ->orderBy('expiry_date', 'asc')
            ->get()
            ->map(function (WarehouseInventory $lot) use ($today) {
                $daysToExpiry = (int) $today->diffInDays($lot->expiry_date->copy()->startOfDay(), false);

                return [
                    'inventory_id' => $lot->id,
                    'warehouse_id' => $lot->warehouse_id,
                    'warehouse_code' => $lot->warehouse?->warehouse_code,
                    'warehouse_name' => $lot->warehouse?->warehouse_name,
                    'commodity_id' => $lot->commodity_id,
                    'commodity_code' => $lot->commodity?->commodity_code,
                    'commodity_name_en' => $lot->commodity?->name_en,
                    'commodity_name_bn' => $lot->commodity?->name_bn,
                    'unit_of_measure' => $lot->commodity?->unit_of_measure,
                    'batch_number' => $lot->batch_number,
                    'current_quantity' => (float) $lot->current_quantity,
                    'expiry_date' => $lot->expiry_date?->toDateString(),
                    'days_to_expiry' => $daysToExpiry,
                    'expiry_state' => $daysToExpiry < 0 ? 'EXPIRED' : 'EXPIRING_SOON',
                    'valuation_bdt' => $lot->unit_cost_bdt !== null ? round((float) $lot->current_quantity * (float) $lot->unit_cost_bdt, 2) : null,
                ];
            })
            ->values()
            ->all();
    }

    private function reportTransactionHistory($warehouseIds, array $filters): array
    {
        return StockTransaction::query()
            ->whereHas('warehouseInventory', fn ($q) => $q->whereIn('warehouse_id', $warehouseIds))
            ->when(! empty($filters['commodity_id']), fn ($q) => $q->whereHas('warehouseInventory', fn ($iq) => $iq->where('commodity_id', $filters['commodity_id'])))
            ->when(! empty($filters['transaction_type']), fn ($q) => $q->where('transaction_type', $filters['transaction_type']))
            ->when(! empty($filters['date_from']), fn ($q) => $q->whereDate('created_at', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($q) => $q->whereDate('created_at', '<=', $filters['date_to']))
            ->with(['warehouseInventory.warehouse', 'warehouseInventory.commodity', 'authorizer', 'approver'])
            ->latest('created_at')
            ->limit(250)
            ->get()
            ->map(function (StockTransaction $tx) {
                return [
                    'id' => $tx->id,
                    'created_at' => $tx->created_at?->toIso8601String(),
                    'warehouse_name' => $tx->warehouseInventory?->warehouse?->warehouse_name,
                    'commodity_name_en' => $tx->warehouseInventory?->commodity?->name_en,
                    'commodity_name_bn' => $tx->warehouseInventory?->commodity?->name_bn,
                    'batch_number' => $tx->warehouseInventory?->batch_number,
                    'transaction_type' => $tx->transaction_type,
                    'reference_type' => $tx->reference_type,
                    'reason_code' => $tx->reason_code,
                    'quantity' => (float) $tx->quantity,
                    'balance_after' => (float) $tx->balance_after,
                    'reference_voucher_no' => $tx->reference_voucher_no,
                    'authorizer_name' => $tx->authorizer?->full_name_en,
                    'approver_name' => $tx->approver?->full_name_en,
                ];
            })
            ->values()
            ->all();
    }

    private function generateUniqueSerial(string $prefix, string $modelClass, string $column): string
    {
        for ($i = 0; $i < 10; $i++) {
            $candidate = sprintf('%s-%s-%s', $prefix, now()->format('Ymd'), strtoupper(Str::random(5)));
            if (! $modelClass::where($column, $candidate)->exists()) {
                return $candidate;
            }
        }

        return sprintf('%s-%s-%s', $prefix, now()->format('YmdHis'), strtoupper(Str::random(4)));
    }
}
