<?php

namespace App\Modules\Document\Contracts;

/**
 * Pluggable antivirus / malware scanning contract for uploaded files.
 */
interface VirusScannerInterface
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_CLEAN = 'CLEAN';
    public const STATUS_INFECTED = 'INFECTED';
    public const STATUS_FAILED = 'FAILED';

    /**
     * Scan a temporary uploaded file or byte stream before persisting.
     *
     * @return array{status: string, engine: string, details: ?string}
     */
    public function scanFile(string $realPath, string $originalFilename, string $mimeType): array;

    /**
     * Report scanner health & driver readiness.
     *
     * @return array{driver: string, engine: string, ready: bool}
     */
    public function health(): array;
}
