<?php

namespace App\Modules\Notification\Adapters;

use App\Modules\Notification\Contracts\SmsGatewayInterface;
use App\Modules\Notification\Models\NotificationDispatch;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Production Teletalk / Government SMS Gateway adapter reading credentials strictly from config/services.php (.env).
 * Fails closed if required gateway credentials are missing.
 */
class TeletalkSmsGatewayAdapter implements SmsGatewayInterface
{
    public function sendSms(string $phoneNumber, string $message, array $context = []): array
    {
        $baseUrl = config('services.sms.base_url');
        $apiKey = config('services.sms.api_key');
        $senderId = config('services.sms.sender_id', 'MoDMR-IDMS');
        $timeout = (int) config('services.sms.timeout_seconds', 5);

        if (empty($baseUrl) || empty($apiKey)) {
            throw new RuntimeException('Government SMS gateway credentials are not configured.');
        }

        $isUnicode = mb_strlen($message) !== strlen($message);

        $response = Http::timeout($timeout)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Accept' => 'application/json',
            ])
            ->post(rtrim($baseUrl, '/') . '/send', [
                'sender_id' => $senderId,
                'msisdn' => $phoneNumber,
                'message' => $message,
                'encoding' => $isUnicode ? 'UCS2' : 'GSM7',
                'reference_id' => $context['dispatch_id'] ?? (string) Str::uuid(),
            ]);

        if ($response->failed()) {
            throw new RuntimeException('SMS gateway rejected request with HTTP ' . $response->status());
        }

        $data = $response->json() ?? [];

        return [
            'status' => NotificationDispatch::STATUS_SENT,
            'provider' => 'teletalk_sms',
            'reference' => (string) ($data['message_id'] ?? $data['reference'] ?? ('TLK-' . Str::random(10))),
        ];
    }

    public function healthCheck(): array
    {
        $configured = !empty(config('services.sms.base_url')) && !empty(config('services.sms.api_key'));

        return [
            'driver' => 'teletalk_sms',
            'configured' => $configured,
            'is_mock' => false,
            'status' => $configured ? 'READY' : 'UNCONFIGURED',
        ];
    }
}
