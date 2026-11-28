<?php

namespace App\Modules\UserManagement\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserAdministrativeScopeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'administrative_unit_id' => $this->administrative_unit_id,
            'administrative_unit' => $this->whenLoaded('administrativeUnit', fn () => $this->administrativeUnit ? [
                'id' => $this->administrativeUnit->id,
                'code' => $this->administrativeUnit->code,
                'name_en' => $this->administrativeUnit->name_en,
                'name_bn' => $this->administrativeUnit->name_bn,
                'tier_level' => $this->administrativeUnit->tier_level,
                'ancestry_path' => $this->administrativeUnit->ancestry_path,
            ] : null),
            'assignment_type' => $this->assignment_type,
            'is_active' => (bool) $this->is_active,
            'is_currently_valid' => $this->isCurrentlyValid(),
            'valid_from' => $this->valid_from?->toIso8601String(),
            'valid_until' => $this->valid_until?->toIso8601String(),
            'official_order_ref' => $this->official_order_ref,
            'notes' => $this->notes,
            'delegated_by' => $this->delegated_by,
            'delegated_by_user' => $this->whenLoaded('delegatedByUser', fn () => $this->delegatedByUser ? [
                'id' => $this->delegatedByUser->id,
                'full_name_en' => $this->delegatedByUser->full_name_en,
                'full_name_bn' => $this->delegatedByUser->full_name_bn,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
