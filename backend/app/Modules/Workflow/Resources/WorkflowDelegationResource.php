<?php

namespace App\Modules\Workflow\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowDelegationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $now = now();
        $isCurrentlyValid = $this->is_active
            && $this->revoked_at === null
            && $this->valid_from !== null && $this->valid_from->lessThanOrEqualTo($now)
            && $this->valid_until !== null && $this->valid_until->greaterThanOrEqualTo($now);

        return [
            'id' => $this->id,
            'workflow_definition_id' => $this->workflow_definition_id,
            'definition' => $this->whenLoaded('definition', fn () => [
                'id' => $this->definition->id,
                'code' => $this->definition->code,
                'name_en' => $this->definition->name_en,
                'name_bn' => $this->definition->name_bn,
            ]),
            'delegator_user_id' => $this->delegator_user_id,
            'delegator' => $this->whenLoaded('delegator', fn () => [
                'id' => $this->delegator->id,
                'name_en' => $this->delegator->full_name_en,
                'role_code' => $this->delegator->role_code,
            ]),
            'delegate_user_id' => $this->delegate_user_id,
            'delegate' => $this->whenLoaded('delegate', fn () => [
                'id' => $this->delegate->id,
                'name_en' => $this->delegate->full_name_en,
                'role_code' => $this->delegate->role_code,
            ]),
            'administrative_unit_id' => $this->administrative_unit_id,
            'administrative_unit' => $this->whenLoaded('administrativeUnit', fn () => [
                'id' => $this->administrativeUnit->id,
                'name_en' => $this->administrativeUnit->name_en,
                'name_bn' => $this->administrativeUnit->name_bn,
                'tier_level' => $this->administrativeUnit->tier_level,
            ]),
            'delegated_role_code' => $this->delegated_role_code,
            'valid_from' => $this->valid_from?->toIso8601String(),
            'valid_until' => $this->valid_until?->toIso8601String(),
            'reason' => $this->reason,
            'is_active' => (bool) $this->is_active,
            'is_currently_valid' => $isCurrentlyValid,
            'revoked_at' => $this->revoked_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
