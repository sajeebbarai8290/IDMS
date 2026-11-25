<?php

namespace App\Modules\Disaster\Models;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class DisasterEvent
 *
 * Represents a disaster event with lifecycle phases, GIS geometry, multi-location mapping,
 * impact metrics, early warnings, and chronological timeline.
 */
class DisasterEvent extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public const PHASE_REPORTED = 'REPORTED';
    public const PHASE_UNDER_ASSESSMENT = 'UNDER_ASSESSMENT';
    public const PHASE_VERIFIED = 'VERIFIED';
    public const PHASE_ACTIVE = 'ACTIVE';
    public const PHASE_RESPONSE_ONGOING = 'RESPONSE_ONGOING';
    public const PHASE_RECOVERY = 'RECOVERY';
    public const PHASE_CLOSED = 'CLOSED';

    public const LIFECYCLE_PHASES = [
        self::PHASE_REPORTED,
        self::PHASE_UNDER_ASSESSMENT,
        self::PHASE_VERIFIED,
        self::PHASE_ACTIVE,
        self::PHASE_RESPONSE_ONGOING,
        self::PHASE_RECOVERY,
        self::PHASE_CLOSED,
    ];

    public const SEVERITY_LEVELS = [
        'ADVISORY',
        'WARNING',
        'DANGER',
        'GREAT_DANGER',
        'CATASTROPHIC',
    ];

    protected $table = 'disaster.disaster_events';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'disaster.disaster_events'
            : 'disaster_events';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'category_id',
        'disaster_code',
        'name_en',
        'name_bn',
        'disaster_type',
        'subtype',
        'description_en',
        'description_bn',
        'severity_level',
        'lifecycle_phase',
        'declaration_date',
        'start_date',
        'end_date',
        'epicenter_lat',
        'epicenter_lng',
        'affected_areas_summary',
        'affected_zone_geojson',
        'is_national',
        'is_emergency_declared',
        'created_by',
        'updated_by',
        'verified_by',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'declaration_date' => 'datetime',
            'start_date' => 'date',
            'end_date' => 'date',
            'epicenter_lat' => 'decimal:7',
            'epicenter_lng' => 'decimal:7',
            'affected_zone_geojson' => 'array',
            'is_national' => 'boolean',
            'is_emergency_declared' => 'boolean',
            'verified_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Map legacy lifecycle aliases to the standardized 7-stage lifecycle.
     */
    public static function normalizeLifecyclePhase(?string $phase): string
    {
        $upper = strtoupper(trim((string) $phase));

        return match ($upper) {
            'IMPENDING' => self::PHASE_REPORTED,
            'RESPONSE' => self::PHASE_RESPONSE_ONGOING,
            default => in_array($upper, self::LIFECYCLE_PHASES, true) ? $upper : self::PHASE_REPORTED,
        };
    }

    /**
     * Hierarchical 7-tier jurisdictional visibility scope.
     * National users see all events; regional users see national events and events
     * intersecting their jurisdiction subtree (downward children or parent region coverage).
     */
    public function scopeInJurisdiction(Builder $query, User $user): Builder
    {
        if ($user->isNationalScope()) {
            return $query;
        }

        $officerPath = $user->getAdministrativeScope()['ancestry_path'] ?? '/';
        if ($officerPath === '/' || $officerPath === '') {
            return $query;
        }

        $normPath = rtrim($officerPath, '/') . '/';
        $table = $this->getTable();
        $locationsTable = (new DisasterLocation())->getTable();
        $adminTable = (new AdministrativeUnit())->getTable();

        return $query->where(function (Builder $outer) use ($table, $locationsTable, $adminTable, $normPath, $user) {
            $outer->where("{$table}.is_national", true)
                ->orWhere("{$table}.created_by", $user->id)
                ->orWhereExists(function ($sub) use ($table, $locationsTable, $adminTable, $normPath) {
                    $sub->selectRaw('1')
                        ->from($locationsTable)
                        ->join($adminTable, "{$adminTable}.id", '=', "{$locationsTable}.administrative_unit_id")
                        ->whereColumn("{$locationsTable}.disaster_event_id", "{$table}.id")
                        ->where(function ($pathMatch) use ($adminTable, $normPath) {
                            $pathMatch->where("{$adminTable}.ancestry_path", 'like', $normPath . '%')
                                ->orWhereRaw("? LIKE {$adminTable}.ancestry_path || '%'", [$normPath]);
                        });
                });
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DisasterCategory::class, 'category_id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(DisasterLocation::class, 'disaster_event_id');
    }

    public function impact(): HasOne
    {
        return $this->hasOne(DisasterImpact::class, 'disaster_event_id');
    }

    public function warnings(): HasMany
    {
        return $this->hasMany(DisasterWarning::class, 'disaster_event_id')->orderByDesc('issued_at');
    }

    public function timelines(): HasMany
    {
        return $this->hasMany(DisasterTimeline::class, 'disaster_event_id')->orderByDesc('occurred_at');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
