<?php

namespace App\Modules\Beneficiary\Models;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

/**
 * Class Household
 *
 * Master registry model for vulnerable and disaster-affected households.
 *
 * @package App\Modules\Beneficiary\Models
 */
class Household extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'beneficiary.households';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql' ? 'beneficiary.households' : 'households';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'household_code',
        'administrative_unit_id',
        'village_ward_name',
        'present_address',
        'permanent_address',
        'hoh_name_en',
        'hoh_name_bn',
        'hoh_nid_hash',
        'hoh_nid_encrypted',
        'hoh_nid_masked',
        'hoh_dob',
        'hoh_gender',
        'hoh_mobile_hash',
        'hoh_mobile_encrypted',
        'hoh_mobile_masked',
        'hoh_occupation',
        'monthly_income_bdt',
        'income_category',
        'family_member_count',
        'vulnerability_score',
        'is_female_headed',
        'has_elderly',
        'has_disabled',
        'has_children_infants',
        'has_pregnant_lactating',
        'is_displaced',
        'other_vulnerabilities',
        'latitude',
        'longitude',
        'verification_status',
        'nid_verified',
        'address_verified',
        'field_verified',
        'photo_url',
        'nid_document_url',
        'created_by',
        'updated_by',
    ];

    protected $hidden = [
        'hoh_nid_encrypted',
        'hoh_mobile_encrypted',
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'hoh_dob' => 'date',
            'monthly_income_bdt' => 'decimal:2',
            'family_member_count' => 'integer',
            'vulnerability_score' => 'decimal:2',
            'is_female_headed' => 'boolean',
            'has_elderly' => 'boolean',
            'has_disabled' => 'boolean',
            'has_children_infants' => 'boolean',
            'has_pregnant_lactating' => 'boolean',
            'is_displaced' => 'boolean',
            'other_vulnerabilities' => 'array',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'nid_verified' => 'boolean',
            'address_verified' => 'boolean',
            'field_verified' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Generate deterministic HMAC-SHA256 hash of NID.
     */
    public static function hashNid(string $nid): string
    {
        $pepper = config('app.key');
        return hash_hmac('sha256', trim($nid), $pepper);
    }

    /**
     * Generate deterministic HMAC-SHA256 hash of Mobile number.
     */
    public static function hashMobile(?string $mobile): ?string
    {
        if (!$mobile) {
            return null;
        }
        $pepper = config('app.key');
        return hash_hmac('sha256', preg_replace('/[^0-9]/', '', $mobile), $pepper);
    }

    /**
     * Mask NID number: e.g. 1990123456789 -> 1990*******789
     */
    public static function maskNid(string $nid): string
    {
        $clean = trim($nid);
        $len = strlen($clean);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }
        $prefixLen = min(4, (int) floor($len / 3));
        $suffixLen = min(3, (int) floor($len / 4));
        $maskLen = $len - $prefixLen - $suffixLen;
        return substr($clean, 0, $prefixLen) . str_repeat('*', $maskLen) . substr($clean, -$suffixLen);
    }

    /**
     * Mask mobile number: e.g. 01712345678 -> 017*****678
     */
    public static function maskMobile(?string $mobile): ?string
    {
        if (!$mobile) {
            return null;
        }
        $clean = trim($mobile);
        $len = strlen($clean);
        if ($len <= 5) {
            return str_repeat('*', $len);
        }
        return substr($clean, 0, 3) . str_repeat('*', $len - 6) . substr($clean, -3);
    }

    /**
     * Decrypt raw NID (Restricted to authorized officers with beneficiary.pii.decrypt).
     */
    public function getDecryptedNid(): ?string
    {
        if (!$this->hoh_nid_encrypted) {
            return null;
        }
        try {
            return Crypt::decryptString($this->hoh_nid_encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Decrypt raw Mobile (Restricted to authorized officers with beneficiary.pii.decrypt).
     */
    public function getDecryptedMobile(): ?string
    {
        if (!$this->hoh_mobile_encrypted) {
            return null;
        }
        try {
            return Crypt::decryptString($this->hoh_mobile_encrypted);
        } catch (\Throwable) {
            return null;
        }
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
        $householdTable = $this->getTable();

        return $query->whereExists(function ($subQuery) use ($adminTable, $householdTable, $normPath) {
            $subQuery->selectRaw('1')
                ->from($adminTable)
                ->whereColumn("{$adminTable}.id", "{$householdTable}.administrative_unit_id")
                ->where("{$adminTable}.ancestry_path", 'like', $normPath . '%');
        });
    }

    // Relationships
    public function administrativeUnit(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class, 'administrative_unit_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(HouseholdMember::class, 'household_id');
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(HouseholdVerification::class, 'household_id');
    }

    public function nidVerifications(): HasMany
    {
        return $this->hasMany(NidVerification::class, 'household_id');
    }

    public function disasterEligibilities(): HasMany
    {
        return $this->hasMany(DisasterEligibility::class, 'household_id');
    }

    public function duplicateSuspects(): HasMany
    {
        return $this->hasMany(DuplicateSuspect::class, 'household_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
