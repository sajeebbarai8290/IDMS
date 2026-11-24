<?php

namespace App\Modules\Beneficiary\Models;

use App\Core\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class NidVerification
 *
 * Audit record of National ID verification queries.
 *
 * @package App\Modules\Beneficiary\Models
 */
class NidVerification extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'beneficiary.nid_verifications';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql' ? 'beneficiary.nid_verifications' : 'nid_verifications';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'household_id',
        'household_member_id',
        'nid_hash',
        'nid_masked',
        'date_of_birth',
        'verification_status',
        'verification_token',
        'provider_code',
        'response_payload_hash',
        'verified_by_user_id',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'verified_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class, 'household_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(HouseholdMember::class, 'household_member_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }
}
