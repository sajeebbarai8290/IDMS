<?php

namespace App\Modules\UserManagement\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'full_name_en' => $this->full_name_en,
            'full_name_bn' => $this->full_name_bn,
            'email' => $this->email,
            'mobile_number' => $this->mobile_number,
            'designation' => $this->designation,
            'designation_bn' => $this->designation_bn,
            'department_en' => $this->department_en,
            'department_bn' => $this->department_bn,
            'role_code' => $this->role_code,
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->map(fn ($r) => [
                'id' => $r->id,
                'code' => $r->code,
                'name_en' => $r->name_en,
                'name_bn' => $r->name_bn,
                'tier_level' => $r->tier_level,
                'hierarchy_rank' => (int) $r->hierarchy_rank,
                'is_primary' => (bool) ($r->pivot->is_primary ?? ($r->code === $this->role_code)),
            ])),
            'organization_id' => $this->organization_id,
            'organization' => $this->whenLoaded('organization', fn () => $this->organization ? [
                'id' => $this->organization->id,
                'org_code' => $this->organization->org_code,
                'name_en' => $this->organization->name_en,
                'name_bn' => $this->organization->name_bn,
                'org_type' => $this->organization->org_type,
                'tier_level' => $this->organization->tier_level,
            ] : null),
            'administrative_unit_id' => $this->administrative_unit_id,
            'administrative_unit' => $this->whenLoaded('administrativeUnit', fn () => $this->administrativeUnit ? [
                'id' => $this->administrativeUnit->id,
                'code' => $this->administrativeUnit->code,
                'name_en' => $this->administrativeUnit->name_en,
                'name_bn' => $this->administrativeUnit->name_bn,
                'tier_level' => $this->administrativeUnit->tier_level,
                'ancestry_path' => $this->administrativeUnit->ancestry_path,
            ] : null),
            'administrative_scopes' => UserAdministrativeScopeResource::collection($this->whenLoaded('administrativeScopes')),
            'is_active' => (bool) $this->is_active,
            'account_status' => $this->account_status ?? ($this->is_active ? 'ACTIVE' : 'DEACTIVATED'),
            'status_reason' => $this->status_reason,
            'status_changed_at' => $this->status_changed_at?->toIso8601String(),
            'status_changed_by_user' => $this->whenLoaded('statusChangedByUser', fn () => $this->statusChangedByUser ? [
                'id' => $this->statusChangedByUser->id,
                'full_name_en' => $this->statusChangedByUser->full_name_en,
                'full_name_bn' => $this->statusChangedByUser->full_name_bn,
            ] : null),
            'has_photo' => !empty($this->photo_path),
            'photo_url' => !empty($this->photo_path) ? "/api/v1/user-management/users/{$this->id}/photo" : null,
            'keycloak_sub' => $this->keycloak_sub,
            'keycloak_username' => $this->keycloak_username,
            'idp_sync_status' => $this->idp_sync_status ?? 'LINKED',
            'idp_synced_at' => $this->idp_synced_at?->toIso8601String(),
            'idp_sync_error' => $this->idp_sync_error,
            'mfa_enforced' => (bool) $this->mfa_enforced,
            'mfa_verified' => (bool) $this->mfa_verified,
            'mfa_enrolled_at' => $this->mfa_enrolled_at?->toIso8601String(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
