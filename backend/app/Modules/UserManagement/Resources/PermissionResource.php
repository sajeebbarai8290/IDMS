<?php

namespace App\Modules\UserManagement\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PermissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'module_code' => $this->module_code,
            'name_en' => $this->name_en,
            'name_bn' => $this->name_bn,
            'action_type' => $this->action_type,
            'is_sensitive' => (bool) $this->is_sensitive,
            'description' => $this->description,
        ];
    }
}
