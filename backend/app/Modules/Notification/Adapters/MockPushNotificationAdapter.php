<?php

namespace App\Modules\Notification\Adapters;

use App\Modules\Notification\Contracts\PushNotificationInterface;
use App\Modules\Notification\Models\NotificationDispatch;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Safe development/testing push notification adapter for Flutter/Web push integration.
 */
class MockPushNotificationAdapter implements PushNotificationInterface
{
    public function sendPush(
        string $deviceToken,
        string $platform,
        string $title,
        string $body,
        array $data = []
    ): array {
        $reference = 'MOCK-PUSH-' . strtoupper(Str::random(10));

        Log::info('Push notification dispatched via MockPushNotificationAdapter.', [
            'platform' => $platform,
            'masked_token' => NotificationDispatch::maskDestination($deviceToken, 'PUSH'),
            'title' => $title,
            'reference' => $reference,
        ]);

        return [
            'status' => NotificationDispatch::STATUS_DELIVERED,
            'provider' => 'mock_push',
            'reference' => $reference,
        ];
    }

    public function healthCheck(): array
    {
        return [
            'driver' => 'mock_push',
            'configured' => true,
            'is_mock' => true,
            'status' => 'READY',
        ];
    }
}
