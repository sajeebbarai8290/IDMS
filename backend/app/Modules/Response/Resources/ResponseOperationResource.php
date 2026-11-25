<?php

namespace App\Modules\Response\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResponseOperationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isExternal = $request->user()?->hasRole('ROLE_EXTERNAL_PARTNER') ?? false;

        return [
            'id' => $this->id,
            'operation_no' => $this->operation_no,
            'disaster_event_id' => $this->disaster_event_id,
            'disaster_event' => $this->whenLoaded('disasterEvent', fn () => [
                'id' => $this->disasterEvent->id,
                'disaster_code' => $this->disasterEvent->disaster_code,
                'name_en' => $this->disasterEvent->name_en,
                'name_bn' => $this->disasterEvent->name_bn,
                'severity_level' => $this->disasterEvent->severity_level,
            ]),
            'administrative_unit_id' => $this->administrative_unit_id,
            'administrative_unit' => $this->whenLoaded('administrativeUnit', fn () => [
                'id' => $this->administrativeUnit->id,
                'name_en' => $this->administrativeUnit->name_en,
                'name_bn' => $this->administrativeUnit->name_bn,
                'tier_level' => $this->administrativeUnit->tier_level,
            ]),
            'response_center_id' => $this->response_center_id,
            'response_center' => $this->whenLoaded('responseCenter', fn () => $this->responseCenter ? [
                'id' => $this->responseCenter->id,
                'center_code' => $this->responseCenter->center_code,
                'center_type' => $this->responseCenter->center_type,
                'name_en' => $this->responseCenter->name_en,
                'name_bn' => $this->responseCenter->name_bn,
                'contact_phone' => $isExternal ? null : $this->responseCenter->contact_phone,
                'latitude' => $this->responseCenter->latitude,
                'longitude' => $this->responseCenter->longitude,
            ] : null),
            'title_en' => $this->title_en,
            'title_bn' => $this->title_bn,
            'objectives' => $this->objectives,
            'description' => $this->description,
            'priority' => $this->priority,
            'severity_level' => $this->severity_level,
            'status' => $this->status,
            'start_date' => $this->start_date?->toDateString(),
            'expected_completion_date' => $this->expected_completion_date?->toDateString(),
            'actual_completion_date' => $this->actual_completion_date?->toDateString(),
            'lead_organization' => $this->lead_organization,
            'commander_name' => $isExternal ? null : $this->commander_name,
            'status_notes' => $this->status_notes,
            'tasks_count' => $this->whenCounted('tasks'),
            'teams_count' => $this->whenCounted('teams'),
            'situation_reports_count' => $this->whenCounted('situationReports'),
            'agencies' => $this->whenLoaded('agencies', fn () => $this->agencies->map(fn ($ag) => [
                'id' => $ag->id,
                'agency_name' => $ag->agency_name,
                'agency_type' => $ag->agency_type,
                'role_summary' => $ag->role_summary,
                'focal_person_name' => $isExternal ? null : $ag->focal_person_name,
                'focal_contact' => $isExternal ? null : $ag->focal_contact,
                'coordination_status' => $ag->coordination_status,
            ])),
            'teams' => $this->whenLoaded('teams', fn () => ResponseTeamResource::collection($this->teams)->resolve($request)),
            'tasks' => $this->whenLoaded('tasks', fn () => ResponseTaskResource::collection($this->tasks)->resolve($request)),
            'resource_allocations' => $this->whenLoaded('resourceAllocations', fn () => $this->resourceAllocations->map(fn ($r) => [
                'id' => $r->id,
                'requirement_title' => $r->requirement_title,
                'category' => $r->category,
                'quantity_needed' => (float) $r->quantity_needed,
                'quantity_assigned' => (float) $r->quantity_assigned,
                'unit' => $r->unit,
                'priority' => $r->priority,
                'status' => $r->status,
                'notes' => $r->notes,
                'resource_asset_id' => $r->resource_asset_id,
                'asset' => $r->relationLoaded('asset') && $r->asset ? [
                    'id' => $r->asset->id,
                    'asset_code' => $r->asset->asset_code,
                    'asset_type' => $r->asset->asset_type,
                    'name_en' => $r->asset->name_en,
                    'name_bn' => $r->asset->name_bn,
                    'registration_no' => $isExternal ? null : $r->asset->registration_no,
                    'status' => $r->asset->status,
                ] : null,
            ])),
            'situation_reports' => $this->whenLoaded('situationReports', fn () => ResponseSituationReportResource::collection($this->situationReports)->resolve($request)),
            'activated_at' => $this->activated_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
