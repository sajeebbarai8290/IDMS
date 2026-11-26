<?php

namespace App\Modules\DamageAssessment\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DamageSectorItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'damage_assessment_id' => $this->damage_assessment_id,
            'category_id' => $this->category_id,
            'sector_code' => $this->sector_code,
            'category' => $this->whenLoaded('category', fn () => new DamageCategoryResource($this->category)),
            'item_title' => $this->item_title,
            'unit_of_measure' => $this->unit_of_measure,
            'fully_damaged_count' => (int) $this->fully_damaged_count,
            'partially_damaged_count' => (int) $this->partially_damaged_count,
            'minor_damaged_count' => (int) $this->minor_damaged_count,
            'damaged_quantity' => (float) $this->damaged_quantity,
            'affected_households' => (int) $this->affected_households,
            'affected_population' => (int) $this->affected_population,
            'estimated_loss_bdt' => (float) $this->estimated_loss_bdt,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'remarks' => $this->remarks,
        ];
    }
}
