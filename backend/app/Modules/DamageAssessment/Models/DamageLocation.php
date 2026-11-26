<?php

namespace App\Modules\DamageAssessment\Models;

use App\Core\Models\AdministrativeUnit;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DamageLocation extends Model
{
    use HasUuids;

    protected $table = 'damage.damage_locations';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'damage.damage_locations'
            : 'damage_locations';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'damage_assessment_id',
        'administrative_unit_id',
        'ward_no',
        'locality_name_en',
        'locality_name_bn',
        'affected_households',
        'affected_population',
        'severity_level',
        'latitude',
        'longitude',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'affected_households' => 'integer',
            'affected_population' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(DamageAssessment::class, 'damage_assessment_id');
    }

    public function administrativeUnit(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class, 'administrative_unit_id');
    }
}
