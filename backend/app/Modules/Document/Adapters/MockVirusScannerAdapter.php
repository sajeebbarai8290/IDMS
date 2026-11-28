<?php

namespace App\Modules\Document\Adapters;

use App\Modules\Document\Contracts\VirusScannerInterface;

/**
 * Development & test antivirus scanner adapter.
 * Detects the standard EICAR test string so security rejection paths can be tested cleanly.
 */
class MockVirusScannerAdapter implements VirusScannerInterface
{
    public const EICAR_SIGNATURE = 'X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*';

    public function scanFile(string $realPath, string $originalFilename, string $mimeType): array
    {
        $contents = @file_get_contents($realPath);
        if ($contents === false) {
            return [
                'status' => self::STATUS_FAILED,
                'engine' => 'mock-av-v1',
                'details' => 'Unable to read temporary upload stream for virus scan.',
            ];
        }

        if (str_contains($contents, self::EICAR_SIGNATURE)) {
            return [
                'status' => self::STATUS_INFECTED,
                'engine' => 'mock-av-v1',
                'details' => 'EICAR-Test-Signature detected; upload quarantined and rejected.',
            ];
        }

        return [
            'status' => self::STATUS_CLEAN,
            'engine' => 'mock-av-v1',
            'details' => 'No malware signature detected.',
        ];
    }

    public function health(): array
    {
        return [
            'driver' => 'mock',
            'engine' => 'mock-av-v1',
            'ready' => true,
        ];
    }
}
