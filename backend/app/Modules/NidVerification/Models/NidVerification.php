<?php

namespace App\Modules\NidVerification\Models;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Modules\Beneficiary\Models\Household;
use App\Modules\Beneficiary\Models\HouseholdMember;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

/**
 * Class NidVerification
 *
 * Workflow & audit record of National ID verification requests and manual reviews.
 *
 * @package App\Modules\NidVerification\Models
 */
class NidVerification extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_PROCESSING = 'PROCESSING';
    public const STATUS_VERIFIED = 'VERIFIED';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_MANUAL_REVIEW_REQUIRED = 'MANUAL_REVIEW_REQUIRED';

    public const WORKFLOW_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
        self::STATUS_VERIFIED,
        self::STATUS_FAILED,
        self::STATUS_MANUAL_REVIEW_REQUIRED,
    ];

    protected $table = 'beneficiary.nid_verifications';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'beneficiary.nid_verifications'
            : 'nid_verifications';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'household_id',
        'household_member_id',
        'administrative_unit_id',
        'nid_hash',
        'nid_masked',
        'nid_encrypted_payload',
        'date_of_birth',
        'submitted_name_en',
        'verification_status',
        'verification_reference',
        'verification_token',
        'provider_code',
        'is_mock',
        'response_payload_hash',
        'matched_fields',
        'attempt_count',
        'last_error_code',
        'last_error_message',
        'manual_override',
        'override_reason',
        'previous_status',
        'requested_by_user_id',
        'verified_by_user_id',
        'reviewed_by_user_id',
        'verified_at',
        'reviewed_at',
    ];

    protected $hidden = [
        'nid_encrypted_payload',
        'nid_hash',
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'is_mock' => 'boolean',
            'matched_fields' => 'array',
            'attempt_count' => 'integer',
            'manual_override' => 'boolean',
            'verified_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Encrypt raw NID temporarily for queued background processing.
     */
    public static function encryptTemporaryPayload(string $rawNid): string
    {
        return Crypt::encryptString(trim($rawNid));
    }

    /**
     * Decrypt temporary NID payload for queued verification job.
     */
    public function decryptTemporaryPayload(): ?string
    {
        if (!$this->nid_encrypted_payload) {
            return null;
        }

        try {
            return Crypt::decryptString($this->nid_encrypted_payload);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Generate a unique human-readable verification reference.
     */
    public static function generateReference(): string
    {
        return 'NID-' . now()->format('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    }

    /**
     * 7-Tier Administrative Scope Query Filter.
     */
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
        $table = $this->getTable();
        $householdTable = (new Household())->getTable();

        return $query->where(function (Builder $outer) use ($adminTable, $table, $householdTable, $normPath) {
            $outer->whereExists(function ($subQuery) use ($adminTable, $table, $normPath) {
                $subQuery->selectRaw('1')
                    ->from($adminTable)
                    ->whereColumn("{$adminTable}.id", "{$table}.administrative_unit_id")
                    ->where("{$adminTable}.ancestry_path", 'like', $normPath . '%');
            })->orWhere(function (Builder $fallback) use ($adminTable, $table, $householdTable, $normPath) {
                $fallback->whereNull("{$table}.administrative_unit_id")
                    ->whereExists(function ($subQuery) use ($adminTable, $table, $householdTable, $normPath) {
                        $subQuery->selectRaw('1')
                            ->from($householdTable)
                            ->join($adminTable, "{$adminTable}.id", '=', "{$householdTable}.administrative_unit_id")
                            ->whereColumn("{$householdTable}.id", "{$table}.household_id")
                            ->where("{$adminTable}.ancestry_path", 'like', $normPath . '%');
                    });
            });
        });
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class, 'household_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(HouseholdMember::class, 'household_member_id');
    }

    public function administrativeUnit(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class, 'administrative_unit_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(NidVerificationAttempt::class, 'nid_verification_id')
            ->orderBy('attempt_number', 'asc');
    }
}
