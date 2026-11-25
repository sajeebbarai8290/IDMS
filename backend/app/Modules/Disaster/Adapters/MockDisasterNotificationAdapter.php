<?php

namespace App\Modules\Disaster\Adapters;

use App\Modules\Disaster\Contracts\DisasterNotificationInterface;
use App\Modules\Disaster\Models\DisasterWarning;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Development/staging mock dispatcher for disaster early warnings until Module 13 SMS/Push gateway is connected.
 */
class MockDisasterNotificationAdapter implements DisasterNotificationInterface
{
    public function dispatchWarning(DisasterWarning $warning): array
    {
        $ref = 'MOCK-WARN-' . strtoupper(Str::random(10));

        Log::info('Disaster early warning dispatched via MockDisasterNotificationAdapter.', [
            'warning_id' => $warning->id,
            'warning_code' => $warning->warning_code,
            'warning_level' => $warning->warning_level,
            'disaster_event_id' => $warning->disaster_event_id,
            'reference' => $ref,
        ]);

        return [
            'status' => 'DISPATCHED',
            'reference' => $ref,
        ];
    }
}
