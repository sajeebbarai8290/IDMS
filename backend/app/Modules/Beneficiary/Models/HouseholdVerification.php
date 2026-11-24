<?php

namespace App\Modules\Beneficiary\Models;

use App\Core\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class HouseholdVerification
 *
 * Captures historical stages of verification and sign-off.
 *
 * @package App\Modules\Beneficiary\Models
 */
class HouseholdVerification extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'beneficiary.household_verifications';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql' ? 'beneficiary.household_verifications' : 'household_verifications';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'household_id',
        'stage',
        'status',
        'remarks',
        'evidence_document_path',
        'verified_by',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class, 'household_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
