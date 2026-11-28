<?php

namespace App\Modules\Workflow\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowDefinitionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name_en' => $this->name_en,
            'name_bn' => $this->name_bn,
            'module_code' => $this->module_code,
            'entity_type' => $this->entity_type,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'is_default' => (bool) $this->is_default,
            'is_system' => (bool) $this->is_system,
            'allow_self_approval' => (bool) $this->allow_self_approval,
            'allow_delegation' => (bool) $this->allow_delegation,
            'sync_entity_status' => (bool) $this->sync_entity_status,
            'sla_hours' => (int) $this->sla_hours,
            'escalation_enabled' => (bool) $this->escalation_enabled,
            'version' => (int) $this->version,
            'config_metadata' => $this->config_metadata,
            'instances_count' => $this->whenCounted('instances'),
            'stages' => WorkflowStageResource::collection($this->whenLoaded('stages')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
