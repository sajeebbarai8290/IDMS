<?php

namespace App\Modules\Document\Adapters;

use App\Modules\Document\Contracts\VirusScannerInterface;
use Throwable;

/**
 * ClamAV clamd INSTREAM scanner adapter for production Linux/container deployments.
 */
class ClamAvScannerAdapter implements VirusScannerInterface
{
    public function scanFile(string $realPath, string $originalFilename, string $mimeType): array
    {
        $socketPath = config('services.document.clamav_socket');
        $host = (string) config('services.document.clamav_host', '127.0.0.1');
        $port = (int) config('services.document.clamav_port', 3310);
        $timeout = max(1, (int) config('services.document.clamav_timeout_seconds', 5));

        try {
            $address = $socketPath ? "unix://{$socketPath}" : "tcp://{$host}:{$port}";
            $errno = 0;
            $errstr = '';
            $stream = @stream_socket_client($address, $errno, $errstr, $timeout);

            if (!$stream) {
                return [
                    'status' => self::STATUS_FAILED,
                    'engine' => 'clamav',
                    'details' => "ClamAV daemon unreachable ({$errstr}).",
                ];
            }

            stream_set_timeout($stream, $timeout);
            fwrite($stream, "zINSTREAM\0");

            $handle = fopen($realPath, 'rb');
            if (!$handle) {
                fclose($stream);
                return [
                    'status' => self::STATUS_FAILED,
                    'engine' => 'clamav',
                    'details' => 'Unable to open uploaded file for ClamAV scan.',
                ];
            }

            while (!feof($handle)) {
                $chunk = fread($handle, 8192);
                if ($chunk === false || $chunk === '') {
                    break;
                }
                fwrite($stream, pack('N', strlen($chunk)) . $chunk);
            }
            fclose($handle);
            fwrite($stream, pack('N', 0));

            $response = trim((string) stream_get_contents($stream));
            fclose($stream);

            if (str_contains($response, 'FOUND')) {
                return [
                    'status' => self::STATUS_INFECTED,
                    'engine' => 'clamav',
                    'details' => $response,
                ];
            }

            if (str_contains($response, 'OK')) {
                return [
                    'status' => self::STATUS_CLEAN,
                    'engine' => 'clamav',
                    'details' => $response,
                ];
            }

            return [
                'status' => self::STATUS_FAILED,
                'engine' => 'clamav',
                'details' => $response ?: 'Unexpected ClamAV response.',
            ];
        } catch (Throwable $e) {
            return [
                'status' => self::STATUS_FAILED,
                'engine' => 'clamav',
                'details' => $e->getMessage(),
            ];
        }
    }

    public function health(): array
    {
        $socketPath = config('services.document.clamav_socket');
        $host = (string) config('services.document.clamav_host', '127.0.0.1');
        $port = (int) config('services.document.clamav_port', 3310);

        return [
            'driver' => 'clamav',
            'engine' => 'clamd',
            'ready' => !empty($socketPath) || (!empty($host) && $port > 0),
        ];
    }
}
