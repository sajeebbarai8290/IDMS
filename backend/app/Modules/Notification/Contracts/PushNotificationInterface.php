<?php

namespace App\Modules\Notification\Contracts;

interface PushNotificationInterface
{
    /**
     * Dispatch a push notification to a registered mobile/web device token.
     *
     * @param string $deviceToken
     * @param string $platform
     * @param string $title
     * @param string $body
     * @param array<string, mixed> $data
     * @return array{status: string, provider: string, reference: string}
     */
    public function sendPush(
        string $deviceToken,
        string $platform,
        string $title,
        string $body,
        array $data = []
    ): array;

    /**
     * Return push adapter configuration readiness (without exposing server keys).
     *
     * @return array<string, mixed>
     */
    public function healthCheck(): array;
}
