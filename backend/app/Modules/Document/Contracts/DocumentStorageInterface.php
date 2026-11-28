<?php

namespace App\Modules\Document\Contracts;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Abstraction for private document storage backends.
 * Decouples business logic from local private disk vs. approved government object/SAN storage.
 */
interface DocumentStorageInterface
{
    /**
     * Store file bytes securely on the private backend.
     *
     * @return array{disk: string, path: string, driver: string}
     */
    public function put(string $relativePath, string $contents, array $metadata = []): array;

    /**
     * Check whether a stored object exists.
     */
    public function exists(string $disk, string $relativePath): bool;

    /**
     * Retrieve raw bytes from storage (used for SHA-256 integrity verification).
     */
    public function get(string $disk, string $relativePath): string;

    /**
     * Stream an authorized download or inline preview response without exposing public URLs.
     */
    public function streamResponse(
        string $disk,
        string $relativePath,
        string $downloadFilename,
        string $mimeType,
        bool $inline = false
    ): StreamedResponse;

    /**
     * Permanently remove an unlinked/orphaned object if needed.
     */
    public function delete(string $disk, string $relativePath): bool;

    /**
     * Report adapter configuration health without leaking credentials.
     *
     * @return array{driver: string, disk: string, ready: bool, public_urls_disabled: bool}
     */
    public function health(): array;
}
