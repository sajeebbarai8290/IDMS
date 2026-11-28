<?php

namespace App\Modules\UserManagement\Models;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAdministrativeScope extends Model
{
    use HasFactory, HasUuids;

    public const ASSIGNMENT_TYPES = [
        'PRIMARY_POSTING',
        'ADDITIONAL_CHARGE',
        'EMERGENCY_DEPUTATION',
        'INSPECTION_TAG',
    ];

    protected $table = 'core.user_administrative_scopes';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'core.user_administrative_scopes'
            : 'user_administrative_scopes';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'administrative_unit_id',
        'assignment_type',
        'is_active',
        'valid_from',
        'valid_until',
        'delegated_by',
        'official_order_ref',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function scopeCurrentlyValid(Builder $query): Builder
    {
        $now = now();

        return $query->where('is_active', true)
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', $now);
            })
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>=', $now);
            });
    }

    public function isCurrentlyValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now();
        if ($this->valid_from && $this->valid_from->gt($now)) {
            return false;
        }
        if ($this->valid_until && $this->valid_until->lt($now)) {
            return false;
        }

        return true;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function administrativeUnit(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class, 'administrative_unit_id');
    }

    public function delegatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegated_by');
    }
}
