<?php

namespace App\Modules\Document\Adapters;

use App\Modules\Document\Contracts\DocumentStorageInterface;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Shared private filesystem streaming and lifecycle logic for storage adapters.
 */
abstract class AbstractFilesystemStorageAdapter implements DocumentStorageInterface
{
    public function exists(string $disk, string $relativePath): bool
    {
        return Storage::disk($disk)->exists($relativePath);
    }

    public function get(string $disk, string $relativePath): string
    {
        $bytes = Storage::disk($disk)->get($relativePath);
        if ($bytes === null) {
            throw new RuntimeException('Document file is missing from private storage.');
        }

        return $bytes;
    }

    public function streamResponse(
        string $disk,
        string $relativePath,
        string $downloadFilename,
        string $mimeType,
        bool $inline = false
    ): StreamedResponse {
        if (!$this->exists($disk, $relativePath)) {
            throw new NotFoundHttpException('Document binary was not found in private storage.');
        }

        $safeName = str_replace(['"', "\r", "\n", '\\', '/'], '_', $downloadFilename);
        $headers = [
            'Content-Type' => $mimeType,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ];

        if ($inline) {
            $headers['Content-Disposition'] = "inline; filename=\"{$safeName}\"";
            return Storage::disk($disk)->response($relativePath, $safeName, $headers);
        }

        return Storage::disk($disk)->download($relativePath, $safeName, $headers);
    }

    public function delete(string $disk, string $relativePath): bool
    {
        if (!$this->exists($disk, $relativePath)) {
            return true;
        }

        return Storage::disk($disk)->delete($relativePath);
    }
}
