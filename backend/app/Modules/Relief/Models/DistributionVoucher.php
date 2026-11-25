<?php

namespace App\Modules\Relief\Models;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Modules\Beneficiary\Models\Household;
use App\Modules\Warehouse\Models\StockTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DistributionVoucher extends Model
{
    use HasUuids;

    public const VERIFICATION_METHODS = [
        'QR_SCAN',
        'SMS_OTP',
        'SIGNATURE_MANUAL',
        'NID_BIOMETRIC',
    ];

    protected $table = 'distribution.distribution_vouchers';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'distribution.distribution_vouchers'
            : 'distribution_vouchers';
    }

    protected $fillable = [
        'relief_allocation_item_id',
        'household_id',
        'distribution_point_id',
        'voucher_no',
        'idempotency_key',
        'quantity_disbursed',
        'verification_method',
        'verification_proof',
        'acknowledgment_signature',
        'photo_evidence_path',
        'photo_evidence_mime',
        'photo_evidence_checksum',
        'distribution_lat',
        'distribution_lng',
        'duplicate_override',
        'duplicate_override_reason',
        'distributed_by',
        'distributed_at',
        'verified_by_tag_officer',
        'verified_at',
        'remarks',
        'is_voided',
        'void_reason',
        'voided_by',
        'voided_at',
    ];

    protected $casts = [
        'quantity_disbursed' => 'decimal:2',
        'distribution_lat' => 'float',
        'distribution_lng' => 'float',
        'duplicate_override' => 'boolean',
        'distributed_at' => 'datetime',
        'verified_at' => 'datetime',
        'is_voided' => 'boolean',
        'voided_at' => 'datetime',
    ];

    public function scopeInJurisdiction(Builder $query, User $user): Builder
    {
        if ($user->isNationalScope()) {
            return $query;
        }

        $scope = $user->getAdministrativeScope();
        $officerPath = $scope['ancestry_path'] ?? '/';
        if ($officerPath === '/' || $officerPath === '') {
            return $query;
        }

        $normPath = rtrim($officerPath, '/') . '/';
        $adminTable = (new AdministrativeUnit())->getTable();
        $pointTable = (new DistributionPoint())->getTable();
        $voucherTable = $this->getTable();

        return $query->whereExists(function ($subQuery) use ($adminTable, $pointTable, $voucherTable, $normPath) {
            $subQuery->selectRaw('1')
                ->from($pointTable)
                ->join($adminTable, "{$adminTable}.id", '=', "{$pointTable}.administrative_unit_id")
                ->whereColumn("{$pointTable}.id", "{$voucherTable}.distribution_point_id")
                ->where("{$adminTable}.ancestry_path", 'like', $normPath . '%');
        });
    }

    public function allocationItem(): BelongsTo
    {
        return $this->belongsTo(ReliefAllocationItem::class, 'relief_allocation_item_id');
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class, 'household_id');
    }

    public function distributionPoint(): BelongsTo
    {
        return $this->belongsTo(DistributionPoint::class, 'distribution_point_id');
    }

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'distributed_by');
    }

    public function verifyingTagOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_tag_officer');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function stockTransactions(): HasMany
    {
        return $this->hasMany(StockTransaction::class, 'distribution_voucher_id');
    }
}
