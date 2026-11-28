<?php

namespace App\Modules\Document\Adapters;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * S3 / MinIO / Sovereign Government Object Store adapter.
 * Fails closed if government object store credentials/bucket are not configured.
 */
class GovObjectStoreAdapter extends AbstractFilesystemStorageAdapter
{
    protected string $disk = 'gov_object_store';

    protected function assertConfigured(): void
    {
        $cfg = config("filesystems.disks.{$this->disk}", []);
        if (empty($cfg['bucket']) || empty($cfg['key']) || empty($cfg['secret'])) {
            throw new RuntimeException('GOV_STORAGE_NOT_CONFIGURED: Approved government object storage credentials are missing.');
        }
    }

    public function put(string $relativePath, string $contents, array $metadata = []): array
    {
        $this->assertConfigured();
        $ok = Storage::disk($this->disk)->put($relativePath, $contents, ['visibility' => 'private']);

        if (!$ok) {
            throw new RuntimeException('Failed to upload document to government object storage.');
        }

        return [
            'disk' => $this->disk,
            'path' => $relativePath,
            'driver' => 'gov_object_store',
        ];
    }

    public function health(): array
    {
        $cfg = config("filesystems.disks.{$this->disk}", []);
        $ready = !empty($cfg['bucket']) && !empty($cfg['key']) && !empty($cfg['secret']);

        return [
            'driver' => 'gov_object_store',
            'disk' => $this->disk,
            'ready' => $ready,
            'public_urls_disabled' => true,
        ];
    }
}
