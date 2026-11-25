<?php

namespace App\Modules\NidVerification\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NidVerificationAttemptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'attempt_number' => $this->attempt_number,
            'provider_code' => $this->provider_code,
            'is_mock' => $this->is_mock,
            'status' => $this->status,
            'http_status' => $this->http_status,
            'response_time_ms' => $this->response_time_ms,
            'error_code' => $this->error_code,
            'error_message' => $this->error_message,
            'attempted_by' => $this->attemptedBy?->full_name_en,
            'attempted_at' => $this->attempted_at?->toIso8601String(),
        ];
    }
}
