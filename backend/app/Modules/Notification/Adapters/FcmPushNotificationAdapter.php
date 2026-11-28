<?php

namespace App\Modules\Notification\Adapters;

use App\Modules\Notification\Contracts\PushNotificationInterface;
use App\Modules\Notification\Models\NotificationDispatch;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * FCM Push Notification adapter for Flutter Android/iOS devices.
 * Credentials are read strictly from config/services.php (.env) and fails closed if unconfigured.
 */
class FcmPushNotificationAdapter implements PushNotificationInterface
{
    public function sendPush(
        string $deviceToken,
        string $platform,
        string $title,
        string $body,
        array $data = []
    ): array {
        $endpoint = config('services.push.fcm_endpoint');
        $serverKey = config('services.push.fcm_server_key');
        $timeout = (int) config('services.push.timeout_seconds', 5);

        if (empty($endpoint) || empty($serverKey)) {
            throw new RuntimeException('FCM push notification credentials are not configured.');
        }

        $response = Http::timeout($timeout)
            ->withHeaders([
                'Authorization' => 'key=' . $serverKey,
                'Content-Type' => 'application/json',
            ])
            ->post($endpoint, [
                'to' => $deviceToken,
                'priority' => ($data['priority'] ?? 'NORMAL') === 'URGENT' ? 'high' : 'normal',
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                    'sound' => 'default',
                ],
                'data' => array_merge($data, ['platform' => $platform]),
            ]);

        if ($response->failed()) {
            throw new RuntimeException('FCM push provider failed with HTTP ' . $response->status());
        }

        $payload = $response->json() ?? [];

        return [
            'status' => NotificationDispatch::STATUS_SENT,
            'provider' => 'fcm_push',
            'reference' => (string) ($payload['message_id'] ?? ('FCM-' . Str::random(10))),
        ];
    }

    public function healthCheck(): array
    {
        $configured = !empty(config('services.push.fcm_endpoint')) && !empty(config('services.push.fcm_server_key'));

        return [
            'driver' => 'fcm_push',
            'configured' => $configured,
            'is_mock' => false,
            'status' => $configured ? 'READY' : 'UNCONFIGURED',
        ];
    }
}
