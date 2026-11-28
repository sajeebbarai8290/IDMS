<?php

namespace App\Modules\Notification\Contracts;

interface SmsGatewayInterface
{
    /**
     * Dispatch an SMS message (supports Unicode Bangla UCS-2).
     *
     * @param string $phoneNumber
     * @param string $message
     * @param array<string, mixed> $context
     * @return array{status: string, provider: string, reference: string}
     */
    public function sendSms(string $phoneNumber, string $message, array $context = []): array;

    /**
     * Return adapter connectivity and configuration readiness (without exposing secrets).
     *
     * @return array<string, mixed>
     */
    public function healthCheck(): array;
}
