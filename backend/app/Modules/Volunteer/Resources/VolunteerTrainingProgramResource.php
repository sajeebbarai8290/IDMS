<?php

namespace App\Modules\Volunteer\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VolunteerTrainingProgramResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'program_code' => $this->program_code,
            'title_en' => $this->title_en,
            'title_bn' => $this->title_bn,
            'category_id' => $this->category_id,
            'skill_id' => $this->skill_id,
            'provider_organization_id' => $this->provider_organization_id,
            'provider_name' => $this->provider_name,
            'duration_hours' => $this->duration_hours,
            'validity_months' => $this->validity_months,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'name_en' => $this->category->name_en,
                'name_bn' => $this->category->name_bn,
            ] : null),
            'skill' => $this->whenLoaded('skill', fn () => $this->skill ? [
                'id' => $this->skill->id,
                'skill_code' => $this->skill->skill_code,
                'name_en' => $this->skill->name_en,
                'name_bn' => $this->skill->name_bn,
            ] : null),
            'sessions_count' => $this->whenCounted('sessions'),
        ];
    }
}
