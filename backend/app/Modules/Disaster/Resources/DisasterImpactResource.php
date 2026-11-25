<?php

namespace App\Modules\Disaster\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DisasterImpactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'affected_population' => (int) $this->affected_population,
            'affected_households' => (int) $this->affected_households,
            'deaths_count' => (int) $this->deaths_count,
            'injured_count' => (int) $this->injured_count,
            'missing_count' => (int) $this->missing_count,
            'damaged_houses_fully' => (int) $this->damaged_houses_fully,
            'damaged_houses_partially' => (int) $this->damaged_houses_partially,
            'damaged_infrastructure_count' => (int) $this->damaged_infrastructure_count,
            'agricultural_loss_acres' => (float) $this->agricultural_loss_acres,
            'agricultural_loss_bdt' => (float) $this->agricultural_loss_bdt,
            'livestock_loss_count' => (int) $this->livestock_loss_count,
            'livestock_loss_bdt' => (float) $this->livestock_loss_bdt,
            'total_economic_loss_bdt' => (float) $this->total_economic_loss_bdt,
            'remarks' => $this->remarks,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
