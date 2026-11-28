<?php

namespace App\Modules\Notification\Adapters;

use App\Modules\Notification\Contracts\SmsGatewayInterface;
use App\Modules\Notification\Models\NotificationDispatch;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Safe development/testing SMS gateway adapter. Never bound in production environments.
 */
class MockSmsGatewayAdapter implements SmsGatewayInterface
{
    public function sendSms(string $phoneNumber, string $message, array $context = []): array
    {
        $reference = 'MOCK-SMS-' . strtoupper(Str::random(10));

        Log::info('SMS dispatched via MockSmsGatewayAdapter.', [
            'masked_phone' => NotificationDispatch::maskDestination($phoneNumber, 'SMS'),
            'message_length' => mb_strlen($message),
            'is_unicode' => mb_strlen($message) !== strlen($message),
            'dispatch_id' => $context['dispatch_id'] ?? null,
            'reference' => $reference,
        ]);

        return [
            'status' => NotificationDispatch::STATUS_DELIVERED,
            'provider' => 'mock_sms',
            'reference' => $reference,
        ];
    }

    public function healthCheck(): array
    {
        return [
            'driver' => 'mock_sms',
            'configured' => true,
            'is_mock' => true,
            'status' => 'READY',
        ];
    }
}
