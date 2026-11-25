<?php

namespace App\Modules\Relief\Services;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\AuditLog;
use App\Core\Models\User;
use App\Core\Services\AdministrativeScopeService;
use App\Modules\Beneficiary\Models\DisasterEligibility;
use App\Modules\Beneficiary\Models\Household;
use App\Modules\Disaster\Models\DisasterEvent;
use App\Modules\Relief\Models\DistributionPoint;
use App\Modules\Relief\Models\DistributionVoucher;
use App\Modules\Relief\Models\ReliefAllocation;
use App\Modules\Relief\Models\ReliefAllocationBeneficiary;
use App\Modules\Relief\Models\ReliefAllocationItem;
use App\Modules\Relief\Models\ReliefCategory;
use App\Modules\Relief\Models\ReliefRequisition;
use App\Modules\Relief\Models\ReliefRequisitionItem;
use App\Modules\Warehouse\Models\Commodity;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Warehouse\Services\InventoryLedgerService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReliefManagementService
{
    public function __construct(
        protected InventoryLedgerService $inventoryLedger,
        protected ReliefDuplicateService $duplicateService,
        protected AdministrativeScopeService $scopeService
    ) {}

    /**
     * Create a new relief requisition with line items and optional supporting document.
     */
    public function createRequisition(array $data, User $actor, ?UploadedFile $supportingDoc = null): ReliefRequisition
    {
        $this->assertUnitInUserScope($actor, $data['administrative_unit_id'], 'requisition');

        return DB::transaction(function () use ($data, $actor, $supportingDoc) {
            $status = ($data['submit_immediately'] ?? false)
                ? ReliefRequisition::STATUS_SUBMITTED
                : ReliefRequisition::STATUS_DRAFT;

            $reqNo = $this->generateOrderNo('REQ');

            $req = ReliefRequisition::create([
                'disaster_event_id' => $data['disaster_event_id'],
                'administrative_unit_id' => $data['administrative_unit_id'],
                'requisition_no' => $reqNo,
                'requesting_organization' => $data['requesting_organization'] ?? null,
                'estimated_beneficiaries' => (int) ($data['estimated_beneficiaries'] ?? 0),
                'priority' => $data['priority'] ?? 'MEDIUM',
                'request_date' => $data['request_date'] ?? now()->toDateString(),
                'needed_by_date' => $data['needed_by_date'] ?? null,
                'status' => $status,
                'justification' => $data['justification'] ?? null,
                'submitted_by' => $status === ReliefRequisition::STATUS_SUBMITTED ? $actor->id : null,
                'submitted_at' => $status === ReliefRequisition::STATUS_SUBMITTED ? now() : null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            if ($supportingDoc) {
                $fileMeta = $this->storePrivateFile($supportingDoc, "relief/requisitions/{$req->id}");
                $req->update([
                    'supporting_doc_path' => $fileMeta['path'],
                    'supporting_doc_name' => $fileMeta['original_name'],
                    'supporting_doc_mime' => $fileMeta['mime'],
                    'supporting_doc_checksum' => $fileMeta['checksum'],
                ]);
            }

            foreach ($data['items'] as $item) {
                ReliefRequisitionItem::create([
                    'relief_requisition_id' => $req->id,
                    'commodity_id' => $item['commodity_id'],
                    'quantity_requested' => $item['quantity_requested'],
                    'quantity_approved' => 0,
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            AuditLog::log(
                'RELIEF_REQUISITION_CREATED',
                ReliefRequisition::class,
                $req->id,
                null,
                ['requisition_no' => $req->requisition_no, 'status' => $req->status],
                $actor,
                $req->administrative_unit_id
            );

            return $req->load(['items.commodity.reliefCategory', 'disasterEvent', 'administrativeUnit', 'creator']);
        });
    }

    /**
     * Update a DRAFT requisition.
     */
    public function updateRequisition(
        ReliefRequisition $requisition,
        array $data,
        User $actor,
        ?UploadedFile $supportingDoc = null
    ): ReliefRequisition {
        if (!in_array($requisition->status, [ReliefRequisition::STATUS_DRAFT, ReliefRequisition::STATUS_SUBMITTED], true)) {
            throw ValidationException::withMessages([
                'status' => ['Only DRAFT or unreviewed SUBMITTED requisitions can be modified.'],
            ]);
        }

        if (!empty($data['administrative_unit_id'])) {
            $this->assertUnitInUserScope($actor, $data['administrative_unit_id'], 'requisition');
        }

        return DB::transaction(function () use ($requisition, $data, $actor, $supportingDoc) {
            $requisition->update([
                'disaster_event_id' => $data['disaster_event_id'] ?? $requisition->disaster_event_id,
                'administrative_unit_id' => $data['administrative_unit_id'] ?? $requisition->administrative_unit_id,
                'requesting_organization' => $data['requesting_organization'] ?? $requisition->requesting_organization,
                'estimated_beneficiaries' => $data['estimated_beneficiaries'] ?? $requisition->estimated_beneficiaries,
                'priority' => $data['priority'] ?? $requisition->priority,
                'request_date' => $data['request_date'] ?? $requisition->request_date,
                'needed_by_date' => $data['needed_by_date'] ?? $requisition->needed_by_date,
                'justification' => $data['justification'] ?? $requisition->justification,
                'updated_by' => $actor->id,
            ]);

            if ($supportingDoc) {
                $fileMeta = $this->storePrivateFile($supportingDoc, "relief/requisitions/{$requisition->id}");
                $requisition->update([
                    'supporting_doc_path' => $fileMeta['path'],
                    'supporting_doc_name' => $fileMeta['original_name'],
                    'supporting_doc_mime' => $fileMeta['mime'],
                    'supporting_doc_checksum' => $fileMeta['checksum'],
                ]);
            }

            if (isset($data['items']) && is_array($data['items'])) {
                $requisition->items()->delete();
                foreach ($data['items'] as $item) {
                    ReliefRequisitionItem::create([
                        'relief_requisition_id' => $requisition->id,
                        'commodity_id' => $item['commodity_id'],
                        'quantity_requested' => $item['quantity_requested'],
                        'quantity_approved' => 0,
                        'notes' => $item['notes'] ?? null,
                    ]);
                }
            }

            AuditLog::log(
                'RELIEF_REQUISITION_UPDATED',
                ReliefRequisition::class,
                $requisition->id,
                null,
                ['requisition_no' => $requisition->requisition_no],
                $actor,
                $requisition->administrative_unit_id
            );

            return $requisition->fresh(['items.commodity.reliefCategory', 'disasterEvent', 'administrativeUnit']);
        });
    }

    /**
     * Transition a DRAFT requisition to SUBMITTED.
     */
    public function submitRequisition(ReliefRequisition $requisition, User $actor): ReliefRequisition
    {
        if ($requisition->status !== ReliefRequisition::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'status' => ['Only DRAFT requisitions can be submitted for review.'],
            ]);
        }

        if ($requisition->items()->count() === 0) {
            throw ValidationException::withMessages([
                'items' => ['Requisition must include at least one relief commodity item before submission.'],
            ]);
        }

        $oldStatus = $requisition->status;
        $requisition->update([
            'status' => ReliefRequisition::STATUS_SUBMITTED,
            'submitted_by' => $actor->id,
            'submitted_at' => now(),
            'updated_by' => $actor->id,
        ]);

        AuditLog::log(
            'RELIEF_REQUISITION_SUBMITTED',
            ReliefRequisition::class,
            $requisition->id,
            ['status' => $oldStatus],
            ['status' => $requisition->status],
            $actor,
            $requisition->administrative_unit_id
        );

        return $requisition->fresh(['items.commodity', 'disasterEvent', 'administrativeUnit']);
    }

    /**
     * Move requisition to UNDER_REVIEW (4-eye SoD enforced in Policy & double-checked here).
     */
    public function reviewRequisition(ReliefRequisition $requisition, User $actor, ?string $notes = null): ReliefRequisition
    {
        if ($requisition->status !== ReliefRequisition::STATUS_SUBMITTED) {
            throw ValidationException::withMessages([
                'status' => ['Only SUBMITTED requisitions can be moved into review.'],
            ]);
        }

        $this->assertCreatorNotApprover($requisition->created_by, $actor, $requisition->submitted_by);

        $oldStatus = $requisition->status;
        $requisition->update([
            'status' => ReliefRequisition::STATUS_UNDER_REVIEW,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'review_notes' => $notes ?? $requisition->review_notes,
            'updated_by' => $actor->id,
        ]);

        AuditLog::log(
            'RELIEF_REQUISITION_UNDER_REVIEW',
            ReliefRequisition::class,
            $requisition->id,
            ['status' => $oldStatus],
            ['status' => $requisition->status, 'review_notes' => $notes],
            $actor,
            $requisition->administrative_unit_id
        );

        return $requisition->fresh(['items.commodity', 'disasterEvent', 'administrativeUnit']);
    }

    /**
     * Approve or partially approve a requisition with approved item quantities (4-eye SoD).
     */
    public function approveRequisition(ReliefRequisition $requisition, User $actor, array $data): ReliefRequisition
    {
        if (!in_array($requisition->status, [ReliefRequisition::STATUS_SUBMITTED, ReliefRequisition::STATUS_UNDER_REVIEW], true)) {
            throw ValidationException::withMessages([
                'status' => ['Only SUBMITTED or UNDER_REVIEW requisitions can be approved.'],
            ]);
        }

        $this->assertCreatorNotApprover($requisition->created_by, $actor, $requisition->submitted_by);

        return DB::transaction(function () use ($requisition, $actor, $data) {
            $requisition->load('items');
            $approvedOverrides = collect($data['items'] ?? [])->keyBy('id');

            $anyApproved = false;
            $anyPartial = false;

            foreach ($requisition->items as $item) {
                $override = $approvedOverrides->get($item->id);
                $approvedQty = $override !== null
                    ? (float) $override['quantity_approved']
                    : (float) $item->quantity_requested;

                if ($approvedQty < 0 || $approvedQty > (float) $item->quantity_requested) {
                    throw ValidationException::withMessages([
                        'items' => ['Approved quantity cannot be negative or exceed requested quantity.'],
                    ]);
                }

                if ($approvedQty > 0) {
                    $anyApproved = true;
                }
                if ($approvedQty < (float) $item->quantity_requested) {
                    $anyPartial = true;
                }

                $item->update(['quantity_approved' => round($approvedQty, 2)]);
            }

            if (!$anyApproved) {
                throw ValidationException::withMessages([
                    'items' => ['At least one line item must have an approved quantity greater than zero. Use Reject otherwise.'],
                ]);
            }

            $oldStatus = $requisition->status;
            $newStatus = $anyPartial
                ? ReliefRequisition::STATUS_PARTIALLY_APPROVED
                : ReliefRequisition::STATUS_APPROVED;

            $requisition->update([
                'status' => $newStatus,
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'review_notes' => $data['review_notes'] ?? $requisition->review_notes,
                'updated_by' => $actor->id,
            ]);

            AuditLog::log(
                'RELIEF_REQUISITION_APPROVED',
                ReliefRequisition::class,
                $requisition->id,
                ['status' => $oldStatus],
                ['status' => $newStatus, 'review_notes' => $requisition->review_notes],
                $actor,
                $requisition->administrative_unit_id
            );

            return $requisition->fresh(['items.commodity', 'disasterEvent', 'administrativeUnit', 'approver']);
        });
    }

    /**
     * Reject a submitted or under-review requisition (4-eye SoD + mandatory reason).
     */
    public function rejectRequisition(ReliefRequisition $requisition, User $actor, string $reviewNotes): ReliefRequisition
    {
        if (!in_array($requisition->status, [ReliefRequisition::STATUS_SUBMITTED, ReliefRequisition::STATUS_UNDER_REVIEW], true)) {
            throw ValidationException::withMessages([
                'status' => ['Only SUBMITTED or UNDER_REVIEW requisitions can be rejected.'],
            ]);
        }

        $this->assertCreatorNotApprover($requisition->created_by, $actor, $requisition->submitted_by);

        $oldStatus = $requisition->status;
        $requisition->update([
            'status' => ReliefRequisition::STATUS_REJECTED,
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'review_notes' => $reviewNotes,
            'updated_by' => $actor->id,
        ]);

        AuditLog::log(
            'RELIEF_REQUISITION_REJECTED',
            ReliefRequisition::class,
            $requisition->id,
            ['status' => $oldStatus],
            ['status' => $requisition->status, 'review_notes' => $reviewNotes],
            $actor,
            $requisition->administrative_unit_id
        );

        return $requisition->fresh(['items.commodity', 'disasterEvent', 'administrativeUnit']);
    }

    /**
     * Create a new relief allocation order (optionally linked to an approved requisition).
     */
    public function createAllocation(array $data, User $actor): ReliefAllocation
    {
        $this->assertUnitInUserScope($actor, $data['administrative_unit_id'], 'allocation');

        if (!empty($data['distribution_point_id'])) {
            $point = DistributionPoint::with('administrativeUnit')->findOrFail($data['distribution_point_id']);
            $this->assertUnitInUserScope($actor, $point->administrative_unit_id, 'distribution point');
        }

        if (!Warehouse::supplyingJurisdiction($actor)->whereKey($data['warehouse_id'])->exists()) {
            throw new AuthorizationException('Selected warehouse is not authorized to supply your jurisdiction.');
        }

        return DB::transaction(function () use ($data, $actor) {
            if (!empty($data['relief_requisition_id'])) {
                $req = ReliefRequisition::with('administrativeUnit')->findOrFail($data['relief_requisition_id']);
                $this->assertUnitInUserScope($actor, $req->administrative_unit_id, 'linked requisition');
                if (!in_array($req->status, [
                    ReliefRequisition::STATUS_APPROVED,
                    ReliefRequisition::STATUS_PARTIALLY_APPROVED,
                ], true)) {
                    throw ValidationException::withMessages([
                        'relief_requisition_id' => ['Allocations can only be linked to APPROVED or PARTIALLY_APPROVED requisitions.'],
                    ]);
                }
            }

            $allocation = ReliefAllocation::create([
                'relief_requisition_id' => $data['relief_requisition_id'] ?? null,
                'disaster_event_id' => $data['disaster_event_id'],
                'administrative_unit_id' => $data['administrative_unit_id'],
                'distribution_point_id' => $data['distribution_point_id'] ?? null,
                'warehouse_id' => $data['warehouse_id'],
                'allocation_order_no' => $this->generateOrderNo('ALLOC'),
                'status' => ReliefAllocation::STATUS_DRAFT,
                'scheduled_start_at' => $data['scheduled_start_at'] ?? null,
                'scheduled_end_at' => $data['scheduled_end_at'] ?? null,
                'duplicate_window_days' => (int) ($data['duplicate_window_days'] ?? 30),
                'remarks' => $data['remarks'] ?? null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            foreach ($data['items'] as $item) {
                ReliefAllocationItem::create([
                    'relief_allocation_id' => $allocation->id,
                    'commodity_id' => $item['commodity_id'],
                    'quantity_allocated' => $item['quantity_allocated'],
                    'quantity_distributed' => 0,
                    'quantity_cancelled' => 0,
                ]);
            }

            if (!empty($data['beneficiary_household_ids']) && is_array($data['beneficiary_household_ids'])) {
                $this->syncBeneficiariesInternal($allocation, $data['beneficiary_household_ids'], $actor);
            }

            AuditLog::log(
                'RELIEF_ALLOCATION_CREATED',
                ReliefAllocation::class,
                $allocation->id,
                null,
                ['allocation_order_no' => $allocation->allocation_order_no, 'status' => $allocation->status],
                $actor,
                $allocation->administrative_unit_id
            );

            return $allocation->load([
                'items.commodity.reliefCategory',
                'beneficiaries.household',
                'warehouse',
                'distributionPoint',
                'disasterEvent',
                'administrativeUnit',
                'requisition',
            ]);
        });
    }

    /**
     * Assign or update eligible beneficiary households on an allocation.
     */
    public function assignBeneficiaries(ReliefAllocation $allocation, array $householdIds, User $actor): ReliefAllocation
    {
        if (in_array($allocation->status, [ReliefAllocation::STATUS_COMPLETED, ReliefAllocation::STATUS_CANCELLED], true)) {
            throw ValidationException::withMessages([
                'status' => ['Cannot modify beneficiary roster on a completed or cancelled allocation.'],
            ]);
        }

        return DB::transaction(function () use ($allocation, $householdIds, $actor) {
            $this->syncBeneficiariesInternal($allocation, $householdIds, $actor);

            AuditLog::log(
                'RELIEF_ALLOCATION_BENEFICIARIES_UPDATED',
                ReliefAllocation::class,
                $allocation->id,
                null,
                ['household_count' => count($householdIds)],
                $actor,
                $allocation->administrative_unit_id
            );

            return $allocation->fresh(['beneficiaries.household']);
        });
    }

    /**
     * Sanction an allocation order and reserve warehouse stock (4-eye SoD enforced).
     */
    public function approveAllocation(ReliefAllocation $allocation, User $actor): ReliefAllocation
    {
        if ($allocation->status !== ReliefAllocation::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'status' => ['Only DRAFT allocation orders can be sanctioned.'],
            ]);
        }

        $this->assertCreatorNotApprover($allocation->created_by, $actor);

        return DB::transaction(function () use ($allocation, $actor) {
            $allocation->load('items', 'requisition');

            if ($allocation->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => ['Allocation must contain at least one commodity line item.'],
                ]);
            }

            foreach ($allocation->items as $item) {
                $this->inventoryLedger->reserveStock(
                    $allocation->warehouse_id,
                    $item->commodity_id,
                    (float) $item->quantity_allocated
                );
            }

            $oldStatus = $allocation->status;
            $allocation->update([
                'status' => ReliefAllocation::STATUS_SANCTIONED,
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'updated_by' => $actor->id,
            ]);

            if ($allocation->requisition && in_array($allocation->requisition->status, [
                ReliefRequisition::STATUS_APPROVED,
                ReliefRequisition::STATUS_PARTIALLY_APPROVED,
            ], true)) {
                $allocation->requisition->update([
                    'status' => ReliefRequisition::STATUS_COMPLETED,
                    'updated_by' => $actor->id,
                ]);
            }

            AuditLog::log(
                'RELIEF_ALLOCATION_SANCTIONED',
                ReliefAllocation::class,
                $allocation->id,
                ['status' => $oldStatus],
                ['status' => $allocation->status],
                $actor,
                $allocation->administrative_unit_id
            );

            return $allocation->fresh(['items.commodity', 'warehouse', 'distributionPoint', 'disasterEvent', 'administrativeUnit']);
        });
    }

    /**
     * Dispatch a sanctioned allocation to its distribution center.
     */
    public function dispatchAllocation(ReliefAllocation $allocation, User $actor): ReliefAllocation
    {
        if ($allocation->status !== ReliefAllocation::STATUS_SANCTIONED) {
            throw ValidationException::withMessages([
                'status' => ['Only SANCTIONED allocations can be dispatched.'],
            ]);
        }

        $oldStatus = $allocation->status;
        $allocation->update([
            'status' => ReliefAllocation::STATUS_DISPATCHED,
            'updated_by' => $actor->id,
        ]);

        AuditLog::log(
            'RELIEF_ALLOCATION_DISPATCHED',
            ReliefAllocation::class,
            $allocation->id,
            ['status' => $oldStatus],
            ['status' => $allocation->status],
            $actor,
            $allocation->administrative_unit_id
        );

        return $allocation->fresh(['items.commodity', 'warehouse', 'distributionPoint']);
    }

    /**
     * Cancel remaining un-distributed allocation quantities and release reserved warehouse stock.
     */
    public function cancelAllocation(ReliefAllocation $allocation, User $actor, string $reason): ReliefAllocation
    {
        if (in_array($allocation->status, [ReliefAllocation::STATUS_COMPLETED, ReliefAllocation::STATUS_CANCELLED], true)) {
            throw ValidationException::withMessages([
                'status' => ['Allocation is already completed or cancelled.'],
            ]);
        }

        return DB::transaction(function () use ($allocation, $actor, $reason) {
            $allocation->load('items');
            $wasReserved = in_array($allocation->status, [
                ReliefAllocation::STATUS_SANCTIONED,
                ReliefAllocation::STATUS_DISPATCHED,
                ReliefAllocation::STATUS_PARTIALLY_DISTRIBUTED,
            ], true);

            $anyDistributed = false;
            foreach ($allocation->items as $item) {
                $remaining = $item->remaining_quantity;
                if ((float) $item->quantity_distributed > 0) {
                    $anyDistributed = true;
                }
                if ($remaining > 0) {
                    if ($wasReserved) {
                        $this->inventoryLedger->releaseReservation(
                            $allocation->warehouse_id,
                            $item->commodity_id,
                            $remaining
                        );
                    }
                    $item->update([
                        'quantity_cancelled' => round((float) $item->quantity_cancelled + $remaining, 2),
                    ]);
                }
            }

            $oldStatus = $allocation->status;
            $newStatus = $anyDistributed
                ? ReliefAllocation::STATUS_COMPLETED
                : ReliefAllocation::STATUS_CANCELLED;

            $allocation->update([
                'status' => $newStatus,
                'remarks' => trim(($allocation->remarks ? $allocation->remarks . "\n" : '') . "Cancelled remainder: {$reason}"),
                'updated_by' => $actor->id,
            ]);

            AuditLog::log(
                'RELIEF_ALLOCATION_CANCELLED',
                ReliefAllocation::class,
                $allocation->id,
                ['status' => $oldStatus],
                ['status' => $newStatus, 'reason' => $reason],
                $actor,
                $allocation->administrative_unit_id
            );

            return $allocation->fresh(['items.commodity', 'warehouse', 'distributionPoint']);
        });
    }

    /**
     * Record physical relief distribution to a beneficiary household with atomic stock deduction,
     * idempotency protection, duplicate-assistance check/override, GPS, acknowledgment & photo evidence.
     */
    public function recordDistribution(
        array $data,
        User $actor,
        ?UploadedFile $photoEvidence = null
    ): array {
        return DB::transaction(function () use ($data, $actor, $photoEvidence) {
            $idempotencyKey = $data['idempotency_key'];

            // 1. Idempotency guard against accidental double submissions
            $existingVoucher = DistributionVoucher::where('idempotency_key', $idempotencyKey)->first();
            if ($existingVoucher) {
                if (
                    $existingVoucher->relief_allocation_item_id !== $data['relief_allocation_item_id']
                    || $existingVoucher->household_id !== $data['household_id']
                ) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => ['Idempotency key has already been used for a different distribution payload.'],
                    ]);
                }

                return [
                    'voucher' => $existingVoucher->load([
                        'allocationItem.commodity',
                        'allocationItem.allocation.disasterEvent',
                        'household',
                        'distributionPoint',
                        'distributor',
                    ]),
                    'idempotent_replay' => true,
                ];
            }

            // 2. Lock allocation line item and validate lifecycle & remaining quota
            /** @var ReliefAllocationItem $allocationItem */
            $allocationItem = ReliefAllocationItem::whereKey($data['relief_allocation_item_id'])
                ->lockForUpdate()
                ->firstOrFail();
            $allocationItem->load(['allocation.administrativeUnit', 'commodity']);
            $allocation = $allocationItem->allocation;

            if (!in_array($allocation->status, [
                ReliefAllocation::STATUS_SANCTIONED,
                ReliefAllocation::STATUS_DISPATCHED,
                ReliefAllocation::STATUS_PARTIALLY_DISTRIBUTED,
            ], true)) {
                throw ValidationException::withMessages([
                    'relief_allocation_item_id' => ['Distribution is only permitted against SANCTIONED or DISPATCHED allocations.'],
                ]);
            }

            $qty = round((float) $data['quantity_disbursed'], 2);
            if ($qty <= 0 || $qty > $allocationItem->remaining_quantity + 0.0001) {
                throw ValidationException::withMessages([
                    'quantity_disbursed' => [
                        sprintf(
                            'Requested quantity (%.2f) exceeds remaining allocation balance (%.2f).',
                            $qty,
                            $allocationItem->remaining_quantity
                        ),
                    ],
                ]);
            }

            // 3. Validate Household & DistributionPoint & Jurisdiction
            $household = Household::with('administrativeUnit')->findOrFail($data['household_id']);
            $point = DistributionPoint::with('administrativeUnit')->findOrFail($data['distribution_point_id']);

            if (!$point->is_active) {
                throw ValidationException::withMessages([
                    'distribution_point_id' => ['Selected distribution center is inactive.'],
                ]);
            }

            $allocAdminPath = $allocation->administrativeUnit?->ancestry_path ?? '/';
            $hhAdminPath = $household->administrativeUnit?->ancestry_path ?? '/';
            $ptAdminPath = $point->administrativeUnit?->ancestry_path ?? '/';

            if (!$this->isPathWithin($hhAdminPath, $allocAdminPath) && !$this->scopeService->canAccessScope($actor, $hhAdminPath)) {
                throw ValidationException::withMessages([
                    'household_id' => ['Selected household falls outside the allocation jurisdiction.'],
                ]);
            }

            if (!$this->scopeService->canAccessScope($actor, $ptAdminPath)) {
                throw ValidationException::withMessages([
                    'distribution_point_id' => ['Distribution center is outside your administrative scope.'],
                ]);
            }

            if (in_array($household->verification_status, ['DRAFT', 'REJECTED'], true)) {
                throw ValidationException::withMessages([
                    'household_id' => ['Only verified/approved beneficiary households are eligible to receive relief.'],
                ]);
            }

            // 4. Configurable duplicate-assistance check + authorized exception override
            $allowOverride = filter_var($data['allow_duplicate_exception'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $dupOutcome = $this->duplicateService->assertAllowedOrThrow(
                $allocationItem,
                $household,
                $actor,
                $allowOverride,
                $data['exception_reason'] ?? null
            );

            // 5. Generate Voucher + optional photo evidence upload
            $voucherNo = $this->generateOrderNo('VCH');
            $photoMeta = $photoEvidence
                ? $this->storePrivateFile($photoEvidence, "relief/vouchers/{$voucherNo}")
                : null;

            $voucher = DistributionVoucher::create([
                'relief_allocation_item_id' => $allocationItem->id,
                'household_id' => $household->id,
                'distribution_point_id' => $point->id,
                'voucher_no' => $voucherNo,
                'idempotency_key' => $idempotencyKey,
                'quantity_disbursed' => $qty,
                'verification_method' => $data['verification_method'],
                'verification_proof' => $data['verification_proof'] ?? null,
                'acknowledgment_signature' => $data['acknowledgment_signature'] ?? null,
                'photo_evidence_path' => $photoMeta['path'] ?? null,
                'photo_evidence_mime' => $photoMeta['mime'] ?? null,
                'photo_evidence_checksum' => $photoMeta['checksum'] ?? null,
                'distribution_lat' => $data['distribution_lat'] ?? null,
                'distribution_lng' => $data['distribution_lng'] ?? null,
                'duplicate_override' => $dupOutcome['overridden'],
                'duplicate_override_reason' => $dupOutcome['reason'] ?? null,
                'distributed_by' => $actor->id,
                'distributed_at' => now(),
                'remarks' => $data['remarks'] ?? null,
                'is_voided' => false,
            ]);

            // 6. Atomically deduct physical warehouse stock in FEFO/FIFO order
            $this->inventoryLedger->deductStock(
                $allocation->warehouse_id,
                $allocationItem->commodity_id,
                $qty,
                $voucherNo,
                $actor,
                $allocationItem->id,
                $voucher->id,
                $data['remarks'] ?? 'Relief handoff distribution'
            );

            // 7. Update allocation item distributed total & parent allocation progress status
            $allocationItem->update([
                'quantity_distributed' => round((float) $allocationItem->quantity_distributed + $qty, 2),
            ]);

            $allocation->load('items');
            $allDone = $allocation->items->every(fn ($i) => $i->remaining_quantity <= 0.0001);
            $allocation->update([
                'status' => $allDone
                    ? ReliefAllocation::STATUS_COMPLETED
                    : ReliefAllocation::STATUS_PARTIALLY_DISTRIBUTED,
                'updated_by' => $actor->id,
            ]);

            // 8. Mark roster row DISTRIBUTED if present
            ReliefAllocationBeneficiary::where('relief_allocation_id', $allocation->id)
                ->where('household_id', $household->id)
                ->update(['selection_status' => ReliefAllocationBeneficiary::STATUS_DISTRIBUTED]);

            // 9. Update DisasterEligibility metrics if linked
            $eligibility = DisasterEligibility::where('household_id', $household->id)
                ->where('disaster_event_id', $allocation->disaster_event_id)
                ->first();
            if ($eligibility) {
                $eligibility->update([
                    'assistance_received_count' => ((int) $eligibility->assistance_received_count) + 1,
                    'last_assistance_date' => now()->toDateString(),
                    'eligibility_status' => 'ASSISTED',
                ]);
            }

            // 10. Audit Log
            AuditLog::log(
                $dupOutcome['overridden'] ? 'RELIEF_DISTRIBUTED_DUPLICATE_OVERRIDE' : 'RELIEF_DISTRIBUTED',
                DistributionVoucher::class,
                $voucher->id,
                null,
                [
                    'voucher_no' => $voucher->voucher_no,
                    'allocation_order_no' => $allocation->allocation_order_no,
                    'household_id' => $household->id,
                    'quantity_disbursed' => $qty,
                    'duplicate_override' => $dupOutcome['overridden'],
                ],
                $actor,
                $point->administrative_unit_id
            );

            return [
                'voucher' => $voucher->load([
                    'allocationItem.commodity',
                    'allocationItem.allocation.disasterEvent',
                    'household',
                    'distributionPoint',
                    'distributor',
                ]),
                'idempotent_replay' => false,
            ];
        });
    }

    /**
     * Witness verification by a Tag Officer (4-eye SoD: verifier !== distributor).
     */
    public function verifyVoucher(DistributionVoucher $voucher, User $actor): DistributionVoucher
    {
        if ($voucher->is_voided) {
            throw ValidationException::withMessages([
                'voucher' => ['Cannot verify a voided distribution voucher.'],
            ]);
        }

        if ($voucher->distributed_by === $actor->id) {
            throw ValidationException::withMessages([
                'voucher' => ['Separation of duties violation: the distributing officer cannot witness-verify their own voucher.'],
            ]);
        }

        $voucher->update([
            'verified_by_tag_officer' => $actor->id,
            'verified_at' => now(),
        ]);

        $voucher->loadMissing('distributionPoint');
        AuditLog::log(
            'RELIEF_VOUCHER_VERIFIED',
            DistributionVoucher::class,
            $voucher->id,
            null,
            ['voucher_no' => $voucher->voucher_no, 'verified_by_tag_officer' => $actor->id],
            $actor,
            $voucher->distributionPoint?->administrative_unit_id
        );

        return $voucher->fresh(['allocationItem.commodity', 'household', 'distributionPoint', 'distributor', 'verifyingTagOfficer']);
    }

    /**
     * Void an erroneous distribution voucher and restore stock & allocation balance atomically.
     */
    public function voidVoucher(DistributionVoucher $voucher, User $actor, string $reason): DistributionVoucher
    {
        if ($voucher->is_voided) {
            throw ValidationException::withMessages([
                'voucher' => ['This distribution voucher is already voided.'],
            ]);
        }

        return DB::transaction(function () use ($voucher, $actor, $reason) {
            $voucher->loadMissing(['allocationItem.allocation', 'distributionPoint']);
            $item = ReliefAllocationItem::whereKey($voucher->relief_allocation_item_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->inventoryLedger->reverseDistributionDeduction($voucher, $actor, $reason);

            $voucher->update([
                'is_voided' => true,
                'void_reason' => $reason,
                'voided_by' => $actor->id,
                'voided_at' => now(),
            ]);

            $newDistributed = max(0.0, round((float) $item->quantity_distributed - (float) $voucher->quantity_disbursed, 2));
            $item->update(['quantity_distributed' => $newDistributed]);

            $allocation = $item->allocation;
            if ($allocation && $allocation->status === ReliefAllocation::STATUS_COMPLETED) {
                $allocation->update([
                    'status' => $newDistributed > 0
                        ? ReliefAllocation::STATUS_PARTIALLY_DISTRIBUTED
                        : ReliefAllocation::STATUS_DISPATCHED,
                    'updated_by' => $actor->id,
                ]);
            }

            AuditLog::log(
                'RELIEF_VOUCHER_VOIDED',
                DistributionVoucher::class,
                $voucher->id,
                ['is_voided' => false],
                ['is_voided' => true, 'void_reason' => $reason],
                $actor,
                $voucher->distributionPoint?->administrative_unit_id
            );

            return $voucher->fresh(['allocationItem.commodity', 'household', 'distributionPoint', 'voider']);
        });
    }

    /**
     * Compute jurisdiction-scoped dashboard analytics & area-wise relief statistics.
     */
    public function getDashboardAnalytics(User $user, array $filters = []): array
    {
        $disasterId = $filters['disaster_event_id'] ?? null;

        $reqQuery = ReliefRequisition::inJurisdiction($user)
            ->when($disasterId, fn ($q) => $q->where('disaster_event_id', $disasterId));

        $allocQuery = ReliefAllocation::inJurisdiction($user)
            ->when($disasterId, fn ($q) => $q->where('disaster_event_id', $disasterId));

        $voucherQuery = DistributionVoucher::inJurisdiction($user)
            ->where('is_voided', false)
            ->when($disasterId, function ($q) use ($disasterId) {
                $q->whereHas('allocationItem.allocation', fn ($aq) => $aq->where('disaster_event_id', $disasterId));
            });

        $totalRequests = (clone $reqQuery)->count();
        $pendingApprovals = (clone $reqQuery)
            ->whereIn('status', [ReliefRequisition::STATUS_SUBMITTED, ReliefRequisition::STATUS_UNDER_REVIEW])
            ->count();

        $approvedAllocations = (clone $allocQuery)
            ->whereIn('status', [
                ReliefAllocation::STATUS_SANCTIONED,
                ReliefAllocation::STATUS_DISPATCHED,
                ReliefAllocation::STATUS_PARTIALLY_DISTRIBUTED,
                ReliefAllocation::STATUS_COMPLETED,
            ])
            ->count();

        $allocIds = (clone $allocQuery)->pluck('id');
        $totals = ReliefAllocationItem::whereIn('relief_allocation_id', $allocIds)
            ->selectRaw('COALESCE(SUM(quantity_allocated), 0) as allocated, COALESCE(SUM(quantity_distributed), 0) as distributed')
            ->first();

        $totalAllocatedQty = (float) ($totals->allocated ?? 0);
        $totalDistributedQty = (float) ($totals->distributed ?? 0);
        $progressPct = $totalAllocatedQty > 0
            ? round(($totalDistributedQty / $totalAllocatedQty) * 100, 1)
            : 0.0;

        $vouchersIssued = (clone $voucherQuery)->count();
        $beneficiariesServed = (clone $voucherQuery)->distinct('household_id')->count('household_id');

        $requisitionStatusBreakdown = (clone $reqQuery)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $allocTable = (new ReliefAllocation())->getTable();
        $allocItemTable = (new ReliefAllocationItem())->getTable();
        $adminTable = (new AdministrativeUnit())->getTable();

        $areaWiseStats = (clone $allocQuery)
            ->join($adminTable, "{$adminTable}.id", '=', "{$allocTable}.administrative_unit_id")
            ->leftJoin($allocItemTable, "{$allocItemTable}.relief_allocation_id", '=', "{$allocTable}.id")
            ->selectRaw("
                {$allocTable}.administrative_unit_id,
                {$adminTable}.name_en as unit_name_en,
                {$adminTable}.name_bn as unit_name_bn,
                {$adminTable}.tier_level,
                COUNT(DISTINCT {$allocTable}.id) as allocations_count,
                COALESCE(SUM({$allocItemTable}.quantity_allocated), 0) as quantity_allocated,
                COALESCE(SUM({$allocItemTable}.quantity_distributed), 0) as quantity_distributed
            ")
            ->groupBy(
                "{$allocTable}.administrative_unit_id",
                "{$adminTable}.name_en",
                "{$adminTable}.name_bn",
                "{$adminTable}.tier_level"
            )
            ->orderByDesc('quantity_allocated')
            ->limit(12)
            ->get()
            ->map(fn ($row) => [
                'administrative_unit_id' => $row->administrative_unit_id,
                'unit_name_en' => $row->unit_name_en ?? 'Unknown',
                'unit_name_bn' => $row->unit_name_bn ?? $row->unit_name_en ?? 'অজানা',
                'tier_level' => $row->tier_level,
                'allocations_count' => (int) $row->allocations_count,
                'quantity_allocated' => round((float) $row->quantity_allocated, 2),
                'quantity_distributed' => round((float) $row->quantity_distributed, 2),
            ])
            ->all();

        $warehouseStockSummary = Warehouse::supplyingJurisdiction($user)
            ->where('is_active', true)
            ->withCount('inventories')
            ->withSum('inventories', 'current_quantity')
            ->get()
            ->map(function (Warehouse $wh) {
                return [
                    'id' => $wh->id,
                    'warehouse_code' => $wh->warehouse_code,
                    'warehouse_name' => $wh->warehouse_name,
                    'warehouse_name_bn' => $wh->warehouse_name_bn,
                    'tier' => $wh->tier,
                    'total_batches' => (int) ($wh->inventories_count ?? 0),
                    'total_units_on_hand' => round((float) ($wh->inventories_sum_current_quantity ?? 0), 2),
                ];
            })
            ->values()
            ->all();

        return [
            'summary' => [
                'total_requests' => $totalRequests,
                'pending_approvals' => $pendingApprovals,
                'approved_allocations' => $approvedAllocations,
                'total_allocated_qty' => round($totalAllocatedQty, 2),
                'total_distributed_qty' => round($totalDistributedQty, 2),
                'distribution_progress_pct' => $progressPct,
                'beneficiaries_served' => $beneficiariesServed,
                'vouchers_issued' => $vouchersIssued,
            ],
            'requisition_status_breakdown' => $requisitionStatusBreakdown,
            'area_wise_stats' => $areaWiseStats,
            'warehouse_stock_summary' => $warehouseStockSummary,
        ];
    }

    /**
     * Store uploaded requisition document or distribution photo privately on local disk.
     */
    private function storePrivateFile(UploadedFile $file, string $directory): array
    {
        $extension = $file->getClientOriginalExtension() ?: $file->extension() ?: 'bin';
        $filename = Str::uuid()->toString() . '.' . strtolower($extension);
        $path = $file->storeAs($directory, $filename, 'local');

        return [
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType() ?: $file->getClientMimeType() ?: 'application/octet-stream',
            'checksum' => hash_file('sha256', Storage::disk('local')->path($path)),
        ];
    }

    /**
     * Enforce 4-eye Separation of Duties.
     */
    private function assertCreatorNotApprover(string $createdById, User $actor, ?string $submittedById = null): void
    {
        if ($createdById === $actor->id || ($submittedById !== null && $submittedById === $actor->id)) {
            throw ValidationException::withMessages([
                'authorization' => ['Four-eyes separation of duties violation: you cannot review, approve, or reject your own submission.'],
            ]);
        }
    }

    /**
     * Sync selected eligible households on an allocation within jurisdiction.
     */
    private function syncBeneficiariesInternal(ReliefAllocation $allocation, array $householdIds, User $actor): void
    {
        $allocation->loadMissing('administrativeUnit');
        $allocPath = $allocation->administrativeUnit?->ancestry_path ?? '/';

        $uniqueIds = array_values(array_unique(array_filter($householdIds)));
        $households = Household::with('administrativeUnit')->whereIn('id', $uniqueIds)->get()->keyBy('id');

        foreach ($uniqueIds as $hhId) {
            $hh = $households->get($hhId);
            if (!$hh) {
                throw ValidationException::withMessages([
                    'beneficiary_household_ids' => ["Household {$hhId} was not found."],
                ]);
            }
            if (in_array($hh->verification_status, ['DRAFT', 'REJECTED'], true)) {
                throw ValidationException::withMessages([
                    'beneficiary_household_ids' => ["Household {$hh->household_code} is not verified/approved for relief distribution."],
                ]);
            }
            $hhPath = $hh->administrativeUnit?->ancestry_path ?? '/';
            if (!$this->isPathWithin($hhPath, $allocPath)) {
                throw ValidationException::withMessages([
                    'beneficiary_household_ids' => ["Household {$hh->household_code} belongs outside the allocation's target administrative unit."],
                ]);
            }

            ReliefAllocationBeneficiary::firstOrCreate(
                [
                    'relief_allocation_id' => $allocation->id,
                    'household_id' => $hh->id,
                ],
                [
                    'selection_status' => ReliefAllocationBeneficiary::STATUS_SELECTED,
                    'assigned_by' => $actor->id,
                ]
            );
        }
    }

    private function assertUnitInUserScope(User $actor, string $administrativeUnitId, string $context): void
    {
        $unit = AdministrativeUnit::findOrFail($administrativeUnitId);
        if (!$this->scopeService->canAccessScope($actor, $unit->ancestry_path)) {
            throw new AuthorizationException(
                "Jurisdiction access denied: target {$context} unit is outside your administrative hierarchy."
            );
        }
    }

    private function isPathWithin(string $childPath, string $parentPath): bool
    {
        if ($parentPath === '/' || $parentPath === '') {
            return true;
        }
        $normParent = rtrim($parentPath, '/') . '/';
        $normChild = rtrim($childPath, '/') . '/';

        return str_starts_with($normChild, $normParent);
    }

    private function generateOrderNo(string $prefix): string
    {
        return sprintf('%s-%s-%s', $prefix, now()->format('Ymd'), strtoupper(Str::random(6)));
    }
}
