<?php

namespace App\Modules\NidVerification\Models;

use App\Core\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class NidVerificationAttempt
 *
 * Records individual provider request attempts, latency, HTTP status, and sanitized error details.
 *
 * @package App\Modules\NidVerification\Models
 */
class NidVerificationAttempt extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'beneficiary.nid_verification_attempts';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'beneficiary.nid_verification_attempts'
            : 'nid_verification_attempts';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'nid_verification_id',
        'attempt_number',
        'provider_code',
        'is_mock',
        'status',
        'http_status',
        'response_time_ms',
        'error_code',
        'error_message',
        'attempted_by_user_id',
        'attempted_at',
    ];

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'is_mock' => 'boolean',
            'http_status' => 'integer',
            'response_time_ms' => 'integer',
            'attempted_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function verification(): BelongsTo
    {
        return $this->belongsTo(NidVerification::class, 'nid_verification_id');
    }

    public function attemptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attempted_by_user_id');
    }
}
