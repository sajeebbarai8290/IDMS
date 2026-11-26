<?php

namespace App\Modules\Resource\Models;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Core\Traits\HasJurisdictionScope;
use App\Modules\Response\Models\ResponseResourceAllocation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResourceAsset extends Model
{
    use HasUuids, SoftDeletes, HasJurisdictionScope;

    public const STATUS_AVAILABLE = 'AVAILABLE';
    public const STATUS_ASSIGNED = 'ASSIGNED';
    public const STATUS_IN_USE = 'IN_USE';
    public const STATUS_DEPLOYED = 'DEPLOYED'; // Legacy Module 05 compatibility
    public const STATUS_UNDER_MAINTENANCE = 'UNDER_MAINTENANCE';
    public const STATUS_MAINTENANCE = 'MAINTENANCE'; // Legacy Module 05 compatibility
    public const STATUS_DAMAGED = 'DAMAGED';
    public const STATUS_RETIRED = 'RETIRED';

    public const STATUSES = [
        self::STATUS_AVAILABLE,
        self::STATUS_ASSIGNED,
        self::STATUS_IN_USE,
        self::STATUS_UNDER_MAINTENANCE,
        self::STATUS_DAMAGED,
        self::STATUS_RETIRED,
        self::STATUS_DEPLOYED,
        self::STATUS_MAINTENANCE,
    ];

    public const PRIMARY_STATUSES = [
        self::STATUS_AVAILABLE,
        self::STATUS_ASSIGNED,
        self::STATUS_IN_USE,
        self::STATUS_UNDER_MAINTENANCE,
        self::STATUS_DAMAGED,
        self::STATUS_RETIRED,
    ];

    protected $table = 'resource.resource_assets';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'resource.resource_assets'
            : 'resource_assets';
    }

    protected $fillable = [
        'category_id',
        'administrative_unit_id',
        'asset_code',
        'asset_type',
        'resource_type',
        'name_en',
        'name_bn',
        'description',
        'registration_no',
        'capacity_info',
        'specifications',
        'owning_organization',
        'current_location',
        'responsible_officer_name',
        'responsible_officer_contact',
        'responsible_user_id',
        'acquisition_date',
        'acquisition_value_bdt',
        'next_maintenance_due_at',
        'status',
        'latitude',
        'longitude',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'specifications' => 'array',
        'acquisition_date' => 'date',
        'acquisition_value_bdt' => 'decimal:2',
        'next_maintenance_due_at' => 'date',
        'latitude' => 'float',
        'longitude' => 'float',
        'is_active' => 'boolean',
    ];

    public function isAvailableForAllocation(): bool
    {
        return $this->is_active && $this->status === self::STATUS_AVAILABLE;
    }

    /**
     * Normalize status filter values to include legacy Module 05 status aliases.
     *
     * @return list<string>
     */
    public static function statusesForFilter(string $status): array
    {
        $norm = strtoupper(trim($status));

        return match ($norm) {
            self::STATUS_IN_USE => [self::STATUS_IN_USE, self::STATUS_DEPLOYED],
            self::STATUS_UNDER_MAINTENANCE => [self::STATUS_UNDER_MAINTENANCE, self::STATUS_MAINTENANCE],
            default => [$norm],
        };
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ResourceCategory::class, 'category_id');
    }

    public function administrativeUnit(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class, 'administrative_unit_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ResourceAttachment::class, 'resource_asset_id')->orderByDesc('created_at');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ResourceAllocation::class, 'resource_asset_id')->orderByDesc('assignment_date')->orderByDesc('created_at');
    }

    public function responseAllocations(): HasMany
    {
        return $this->hasMany(ResponseResourceAllocation::class, 'resource_asset_id');
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(ResourceMaintenance::class, 'resource_asset_id')->orderByDesc('scheduled_date')->orderByDesc('created_at');
    }

    public function humanDeployments(): HasMany
    {
        return $this->hasMany(HumanResourceDeployment::class, 'resource_asset_id')->orderByDesc('deployment_start_date');
    }
}
