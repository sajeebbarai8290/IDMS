<?php

namespace App\Modules\UserManagement\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'org_code' => $this->org_code,
            'name_en' => $this->name_en,
            'name_bn' => $this->name_bn,
            'org_type' => $this->org_type,
            'tier_level' => $this->tier_level,
            'administrative_unit_id' => $this->administrative_unit_id,
            'administrative_unit' => $this->whenLoaded('administrativeUnit', fn () => $this->administrativeUnit ? [
                'id' => $this->administrativeUnit->id,
                'code' => $this->administrativeUnit->code,
                'name_en' => $this->administrativeUnit->name_en,
                'name_bn' => $this->administrativeUnit->name_bn,
                'tier_level' => $this->administrativeUnit->tier_level,
                'ancestry_path' => $this->administrativeUnit->ancestry_path,
            ] : null),
            'parent_organization_id' => $this->parent_organization_id,
            'parent_organization' => $this->whenLoaded('parentOrganization', fn () => $this->parentOrganization ? [
                'id' => $this->parentOrganization->id,
                'org_code' => $this->parentOrganization->org_code,
                'name_en' => $this->parentOrganization->name_en,
                'name_bn' => $this->parentOrganization->name_bn,
            ] : null),
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'users_count' => $this->whenCounted('users'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
