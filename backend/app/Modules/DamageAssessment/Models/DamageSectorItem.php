<?php

namespace App\Modules\DamageAssessment\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DamageSectorItem extends Model
{
    use HasUuids;

    protected $table = 'damage.damage_sector_items';

    public function getTable(): string
    {
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'damage.damage_sector_items'
            : 'damage_sector_items';
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'damage_assessment_id',
        'category_id',
        'sector_code',
        'item_title',
        'unit_of_measure',
        'fully_damaged_count',
        'partially_damaged_count',
        'minor_damaged_count',
        'damaged_quantity',
        'affected_households',
        'affected_population',
        'estimated_loss_bdt',
        'latitude',
        'longitude',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'fully_damaged_count' => 'integer',
            'partially_damaged_count' => 'integer',
            'minor_damaged_count' => 'integer',
            'damaged_quantity' => 'decimal:2',
            'affected_households' => 'integer',
            'affected_population' => 'integer',
            'estimated_loss_bdt' => 'decimal:2',
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(DamageCategory::class, 'category_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(DamageAttachment::class, 'damage_sector_item_id');
    }
}
