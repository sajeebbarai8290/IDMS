<?php

namespace App\Modules\Beneficiary\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

/**
 * Class HouseholdMember
 *
 * Dependent individual family member of a household.
 *
 * @package App\Modules\Beneficiary\Models
 */
class HouseholdMember extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'beneficiary.household_members';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql' ? 'beneficiary.household_members' : 'household_members';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'household_id',
        'full_name_en',
        'full_name_bn',
        'relationship_to_hoh',
        'gender',
        'date_of_birth',
        'age',
        'nid_hash',
        'nid_encrypted',
        'nid_masked',
        'has_disability',
        'disability_type',
        'is_pregnant_lactating',
        'occupation',
    ];

    protected $hidden = [
        'nid_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'age' => 'integer',
            'has_disability' => 'boolean',
            'is_pregnant_lactating' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class, 'household_id');
    }

    public function getDecryptedNid(): ?string
    {
        if (!$this->nid_encrypted) {
            return null;
        }
        try {
            return Crypt::decryptString($this->nid_encrypted);
        } catch (\Throwable) {
            return null;
        }
    }
}
