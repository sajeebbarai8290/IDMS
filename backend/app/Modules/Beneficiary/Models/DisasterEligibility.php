<?php

namespace App\Modules\Beneficiary\Models;

use App\Core\Models\User;
use App\Modules\Disaster\Models\DisasterEvent;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class DisasterEligibility
 *
 * Links households to specific disaster declarations and tracks assistance history.
 *
 * @package App\Modules\Beneficiary\Models
 */
class DisasterEligibility extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'beneficiary.disaster_eligibilities';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql' ? 'beneficiary.disaster_eligibilities' : 'disaster_eligibilities';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'household_id',
        'disaster_event_id',
        'eligibility_status',
        'eligibility_criteria',
        'assistance_count',
        'last_assistance_at',
        'is_blacklisted',
        'blacklist_reason',
        'enrolled_by',
    ];

    protected function casts(): array
    {
        return [
            'assistance_count' => 'integer',
            'last_assistance_at' => 'datetime',
            'eligibility_criteria' => 'array',
            'is_blacklisted' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class, 'household_id');
    }

    public function disasterEvent(): BelongsTo
    {
        return $this->belongsTo(DisasterEvent::class, 'disaster_event_id');
    }

    public function enroller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enrolled_by');
    }
}
