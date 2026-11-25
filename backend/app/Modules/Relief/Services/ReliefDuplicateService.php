<?php

namespace App\Modules\Relief\Services;

use App\Core\Models\User;
use App\Modules\Beneficiary\Models\Household;
use App\Modules\Relief\Models\DistributionVoucher;
use App\Modules\Relief\Models\ReliefAllocationItem;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class ReliefDuplicateService
{
    /**
     * Inspect whether a household has already received relief from the same allocation item
     * or within the configured disaster + relief category cooldown window.
     */
    public function checkDuplicateAssistance(
        ReliefAllocationItem $allocationItem,
        Household $household
    ): array {
        $allocationItem->loadMissing(['allocation', 'commodity']);
        $allocation = $allocationItem->allocation;
        $commodity = $allocationItem->commodity;

        $windowDays = max(1, (int) ($allocation?->duplicate_window_days ?? 30));
        $cutoff = now()->subDays($windowDays);

        // 1. Same allocation item check
        $sameAllocationVouchers = DistributionVoucher::where('relief_allocation_item_id', $allocationItem->id)
            ->where('household_id', $household->id)
            ->where('is_voided', false)
            ->orderByDesc('distributed_at')
            ->get(['id', 'voucher_no', 'quantity_disbursed', 'distributed_at']);

        // 2. Same disaster + same relief category (or commodity) within cooldown window
        $categoryId = $commodity?->relief_category_id;
        $disasterId = $allocation?->disaster_event_id;

        $windowVouchersQuery = DistributionVoucher::query()
            ->where('household_id', $household->id)
            ->where('is_voided', false)
            ->where('distributed_at', '>=', $cutoff)
            ->where('relief_allocation_item_id', '!=', $allocationItem->id)
            ->whereHas('allocationItem', function ($q) use ($disasterId, $categoryId, $commodity) {
                $q->whereHas('allocation', function ($aq) use ($disasterId) {
                    $aq->where('disaster_event_id', $disasterId);
                });
                if ($categoryId) {
                    $q->whereHas('commodity', function ($cq) use ($categoryId) {
                        $cq->where('relief_category_id', $categoryId);
                    });
                } elseif ($commodity) {
                    $q->where('commodity_id', $commodity->id);
                }
            });

        $windowVouchers = $windowVouchersQuery
            ->orderByDesc('distributed_at')
            ->get(['id', 'voucher_no', 'quantity_disbursed', 'distributed_at']);

        $reasons = [];
        if ($sameAllocationVouchers->isNotEmpty()) {
            $first = $sameAllocationVouchers->first();
            $reasons[] = sprintf(
                'Household already received assistance under this allocation item (Voucher %s on %s).',
                $first->voucher_no,
                $first->distributed_at?->toDateTimeString()
            );
        }

        if ($windowVouchers->isNotEmpty()) {
            $first = $windowVouchers->first();
            $reasons[] = sprintf(
                'Household received relief from the same disaster and category within the %d-day cooldown window (Voucher %s on %s).',
                $windowDays,
                $first->voucher_no,
                $first->distributed_at?->toDateTimeString()
            );
        }

        return [
            'has_duplicate' => $sameAllocationVouchers->isNotEmpty() || $windowVouchers->isNotEmpty(),
            'same_allocation_duplicate' => $sameAllocationVouchers->isNotEmpty(),
            'window_duplicate' => $windowVouchers->isNotEmpty(),
            'window_days' => $windowDays,
            'reasons' => $reasons,
            'recent_vouchers' => $sameAllocationVouchers->merge($windowVouchers)->values()->all(),
        ];
    }

    /**
     * Block duplicate assistance unless an authorized officer explicitly invokes
     * an exception override with a mandatory documented justification.
     */
    public function assertAllowedOrThrow(
        ReliefAllocationItem $allocationItem,
        Household $household,
        User $actor,
        bool $allowDuplicateException,
        ?string $exceptionReason
    ): array {
        $check = $this->checkDuplicateAssistance($allocationItem, $household);

        if (!$check['has_duplicate']) {
            return ['overridden' => false, 'check' => $check];
        }

        if (!$allowDuplicateException) {
            throw ValidationException::withMessages([
                'household_id' => $check['reasons'],
            ]);
        }

        $hasOverrideRole = $actor->hasRole('ROLE_DEPUTY_COMMISSIONER')
            || $actor->hasRole('ROLE_DRRO')
            || $actor->hasRole('ROLE_UNO')
            || $actor->hasRole('ROLE_PIO');

        if (
            $actor->hasRole('ROLE_SUPER_ADMIN')
            || (!$actor->hasPermission('relief.distribution.override_duplicate') && !$hasOverrideRole)
        ) {
            throw new AuthorizationException(
                'You are not authorized to override duplicate-assistance restrictions.'
            );
        }

        $cleanReason = trim((string) $exceptionReason);
        if (mb_strlen($cleanReason) < 10) {
            throw ValidationException::withMessages([
                'exception_reason' => [
                    'A detailed exception justification (at least 10 characters) is required to override duplicate relief distribution.',
                ],
            ]);
        }

        return ['overridden' => true, 'check' => $check, 'reason' => $cleanReason];
    }
}
