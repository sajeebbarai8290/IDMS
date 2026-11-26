<?php

namespace App\Modules\Project\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectRiskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'risk_code' => $this->risk_code,
            'record_kind' => $this->record_kind,
            'title' => $this->title,
            'description' => $this->description,
            'severity' => $this->severity,
            'likelihood' => $this->likelihood,
            'mitigation_plan' => $this->mitigation_plan,
            'owner_name' => $this->owner_name,
            'status' => $this->status,
            'identified_date' => $this->identified_date?->toDateString(),
            'resolved_date' => $this->resolved_date?->toDateString(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
