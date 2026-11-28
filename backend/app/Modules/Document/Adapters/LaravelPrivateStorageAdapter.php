<?php

namespace App\Modules\Document\Adapters;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

class LaravelPrivateStorageAdapter extends AbstractFilesystemStorageAdapter
{
    public function defaultDisk(): string
    {
        return (string) config('services.document.storage_disk', 'documents_private');
    }

    public function put(string $relativePath, string $contents, array $metadata = []): array
    {
        $disk = $this->defaultDisk();
        $ok = Storage::disk($disk)->put($relativePath, $contents, ['visibility' => 'private']);

        if (!$ok) {
            throw new RuntimeException('Failed to write document bytes to private storage.');
        }

        return [
            'disk' => $disk,
            'path' => $relativePath,
            'driver' => 'laravel_private',
        ];
    }

    public function health(): array
    {
        $disk = $this->defaultDisk();
        $diskConfig = config("filesystems.disks.{$disk}", []);

        return [
            'driver' => 'laravel_private',
            'disk' => $disk,
            'ready' => !empty($diskConfig),
            'public_urls_disabled' => true,
        ];
    }
}
