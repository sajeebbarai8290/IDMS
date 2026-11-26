<?php

namespace App\Modules\DamageAssessment\Models;

use App\Core\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DamageVerificationLog extends Model
{
    use HasUuids;

    protected $table = 'damage.damage_verification_logs';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'damage.damage_verification_logs'
            : 'damage_verification_logs';
    }

    public $timestamps = false;
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'damage_assessment_id',
        'action',
        'from_status',
        'to_status',
        'actor_id',
        'actor_name',
        'actor_role',
        'notes',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(DamageAssessment::class, 'damage_assessment_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
