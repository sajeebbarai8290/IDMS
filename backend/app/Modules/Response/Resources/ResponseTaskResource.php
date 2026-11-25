<?php

namespace App\Modules\Response\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResponseTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isExternal = $request->user()?->hasRole('ROLE_EXTERNAL_PARTNER') ?? false;

        return [
            'id' => $this->id,
            'response_operation_id' => $this->response_operation_id,
            'task_code' => $this->task_code,
            'title_en' => $this->title_en,
            'title_bn' => $this->title_bn,
            'description' => $this->description,
            'activity_type_id' => $this->activity_type_id,
            'activity_type' => $this->whenLoaded('activityType', fn () => $this->activityType ? [
                'id' => $this->activityType->id,
                'code' => $this->activityType->code,
                'name_en' => $this->activityType->name_en,
                'name_bn' => $this->activityType->name_bn,
                'color_hex' => $this->activityType->color_hex,
            ] : null),
            'response_team_id' => $this->response_team_id,
            'team' => $this->whenLoaded('team', fn () => $this->team ? [
                'id' => $this->team->id,
                'team_code' => $this->team->team_code,
                'team_name' => $this->team->team_name,
                'team_type' => $this->team->team_type,
            ] : null),
            'assigned_officer_name' => $isExternal ? null : $this->assigned_officer_name,
            'priority' => $this->priority,
            'status' => $this->status,
            'progress_percentage' => (int) $this->progress_percentage,
            'deadline_at' => $this->deadline_at?->toIso8601String(),
            'completion_notes' => $this->completion_notes,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn ($att) => [
                'id' => $att->id,
                'title' => $att->title,
                'original_name' => $att->original_name,
                'mime_type' => $att->mime_type,
                'file_size_bytes' => (int) $att->file_size_bytes,
                'created_at' => $att->created_at?->toIso8601String(),
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
