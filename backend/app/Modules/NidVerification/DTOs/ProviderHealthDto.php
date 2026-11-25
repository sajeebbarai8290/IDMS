<?php

namespace App\Modules\NidVerification\DTOs;

/**
 * Class ProviderHealthDto
 *
 * Represents live availability and mode metadata for the configured NID verification adapter.
 */
final class ProviderHealthDto
{
    public function __construct(
        public readonly string $providerCode,
        public readonly bool $isMock,
        public readonly bool $available,
        public readonly string $status,
        public readonly string $message,
        public readonly string $checkedAt,
    ) {}

    public function toArray(): array
    {
        return [
            'provider_code' => $this->providerCode,
            'is_mock' => $this->isMock,
            'available' => $this->available,
            'status' => $this->status,
            'message' => $this->message,
            'checked_at' => $this->checkedAt,
        ];
    }
}
