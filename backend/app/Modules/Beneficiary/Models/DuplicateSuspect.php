<?php

namespace App\Modules\Beneficiary\Models;

use App\Core\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class DuplicateSuspect
 *
 * Tracks suspected duplicate household registrations for human review.
 *
 * @package App\Modules\Beneficiary\Models
 */
class DuplicateSuspect extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'beneficiary.duplicate_suspects';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql' ? 'beneficiary.duplicate_suspects' : 'duplicate_suspects';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'household_id',
        'matched_household_id',
        'match_type',
        'confidence_score',
        'status',
        'reviewed_by',
        'reviewed_at',
        'resolution_notes',
    ];

    protected function casts(): array
    {
        return [
            'confidence_score' => 'decimal:2',
            'reviewed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class, 'household_id');
    }

    public function matchedHousehold(): BelongsTo
    {
        return $this->belongsTo(Household::class, 'matched_household_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
