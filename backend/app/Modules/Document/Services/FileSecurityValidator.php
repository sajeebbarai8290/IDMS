<?php

namespace App\Modules\Document\Services;

use App\Modules\Document\Contracts\VirusScannerInterface;
use App\Modules\Document\Models\DocumentCategory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Enforces strict extension allowlist, in-memory magic-byte MIME verification,
 * size limits, virus scanning hook, SHA-256 checksum computation, and safe UUID filenames.
 */
class FileSecurityValidator
{
    /**
     * Approved formats map: extension => allowed MIME types.
     */
    public const APPROVED_FORMAT_MIMES = [
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/CDFV2', 'application/octet-stream'],
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
        ],
        'xls' => ['application/vnd.ms-excel', 'application/CDFV2', 'application/octet-stream'],
        'xlsx' => [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
        ],
        'csv' => ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'txt' => ['text/plain'],
    ];

    public const CANONICAL_MIME_BY_EXTENSION = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'csv' => 'text/csv',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'txt' => 'text/plain',
    ];

    public function __construct(
        protected VirusScannerInterface $scanner
    ) {}

    /**
     * Validate, magic-byte verify, virus scan, and hash an uploaded file.
     *
     * @return array{
     *   original_filename: string,
     *   extension: string,
     *   mime_type: string,
     *   file_size_bytes: int,
     *   file_hash_sha256: string,
     *   storage_path: string,
     *   contents: string,
     *   scan_status: string,
     *   scan_engine: string,
     *   scan_details: ?string
     * }
     */
    public function validateAndScan(UploadedFile $file, ?DocumentCategory $category = null): array
    {
        if (!$file->isValid()) {
            throw ValidationException::withMessages([
                'file' => ['The uploaded file is incomplete or corrupted.'],
            ]);
        }

        $maxKb = (int) config('services.document.max_file_size_kb', 20480);
        $sizeBytes = (int) ($file->getSize() ?: 0);
        if ($sizeBytes <= 0) {
            throw ValidationException::withMessages([
                'file' => ['Uploaded file cannot be empty.'],
            ]);
        }
        if ($sizeBytes > ($maxKb * 1024)) {
            throw ValidationException::withMessages([
                'file' => ["File exceeds maximum allowed size of {$maxKb} KB."],
            ]);
        }

        $originalName = $this->sanitizeOriginalFilename($file->getClientOriginalName());
        $ext = strtolower($file->getClientOriginalExtension() ?: '');

        if (!array_key_exists($ext, self::APPROVED_FORMAT_MIMES)) {
            throw ValidationException::withMessages([
                'file' => ["File format '.{$ext}' is not an approved IDMS document format."],
            ]);
        }

        if ($category && !empty($category->allowed_formats)) {
            $catAllowed = array_map(fn ($f) => strtolower((string) $f), $category->allowed_formats);
            $normalizedExt = $ext === 'jpg' ? 'jpeg' : $ext;
            if (!in_array($ext, $catAllowed, true) && !in_array($normalizedExt, $catAllowed, true)) {
                throw ValidationException::withMessages([
                    'file' => ["Format '.{$ext}' is not permitted for category {$category->name_en}."],
                ]);
            }
        }

        $realPath = $file->getRealPath();
        $contents = $realPath ? @file_get_contents($realPath) : false;
        if ($contents === false || $contents === '') {
            throw ValidationException::withMessages([
                'file' => ['Unable to read uploaded file stream.'],
            ]);
        }

        $detectedMime = $this->detectRealMime($realPath, $file);
        $this->assertMagicBytesAndMime($ext, $detectedMime, $contents);

        $canonicalMime = self::CANONICAL_MIME_BY_EXTENSION[$ext] ?? $detectedMime;
        $scanResult = $this->scanner->scanFile($realPath, $originalName, $canonicalMime);

        if ($scanResult['status'] === VirusScannerInterface::STATUS_INFECTED) {
            throw ValidationException::withMessages([
                'file' => ['Security alert: Malware detected during antivirus scan (' . ($scanResult['details'] ?? 'INFECTED') . ').'],
            ]);
        }

        if ($scanResult['status'] !== VirusScannerInterface::STATUS_CLEAN) {
            throw ValidationException::withMessages([
                'file' => ['Antivirus scan could not verify file safety (' . ($scanResult['details'] ?? 'SCAN_FAILED') . ').'],
            ]);
        }

        $sha256 = hash('sha256', $contents);
        $safeStorageKey = sprintf(
            'docs/%s/%s.%s',
            now()->format('Y/m'),
            (string) Str::uuid(),
            $ext
        );

        return [
            'original_filename' => $originalName,
            'extension' => strtoupper($ext),
            'mime_type' => $canonicalMime,
            'file_size_bytes' => $sizeBytes,
            'file_hash_sha256' => $sha256,
            'storage_path' => $safeStorageKey,
            'contents' => $contents,
            'scan_status' => $scanResult['status'],
            'scan_engine' => $scanResult['engine'],
            'scan_details' => $scanResult['details'],
        ];
    }

    protected function sanitizeOriginalFilename(?string $name): string
    {
        $base = basename((string) ($name ?: 'document.bin'));
        $clean = preg_replace('/[^\p{L}\p{N}\s._-]/u', '_', $base);
        $clean = trim((string) $clean);

        return $clean !== '' ? mb_substr($clean, 0, 250) : 'document.bin';
    }

    protected function detectRealMime(string $realPath, UploadedFile $file): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_file($finfo, $realPath);
                finfo_close($finfo);
                if (is_string($mime) && $mime !== '') {
                    return $mime;
                }
            }
        }

        return $file->getMimeType() ?: 'application/octet-stream';
    }

    protected function assertMagicBytesAndMime(string $ext, string $detectedMime, string $contents): void
    {
        // Block common executable / script payloads regardless of extension
        $prefix4 = substr($contents, 0, 4);
        if (str_starts_with($contents, 'MZ') || str_starts_with($prefix4, "\x7FELF") || preg_match('/^\s*<\?php/i', $contents)) {
            throw ValidationException::withMessages([
                'file' => ['Executable or script payload detected; upload rejected.'],
            ]);
        }

        $allowedMimes = self::APPROVED_FORMAT_MIMES[$ext] ?? [];
        if (!in_array($detectedMime, $allowedMimes, true)) {
            // Allow plain text detection for csv/txt even if finfo reports text/x-Algol68 or similar ASCII
            if (!in_array($ext, ['csv', 'txt'], true) || !str_starts_with($detectedMime, 'text/')) {
                throw ValidationException::withMessages([
                    'file' => ["File MIME type '{$detectedMime}' does not match extension '.{$ext}'."],
                ]);
            }
        }

        // Strict magic-byte signature validation for binary formats
        $validMagic = match ($ext) {
            'pdf' => str_starts_with($contents, '%PDF-'),
            'png' => str_starts_with($contents, "\x89PNG\r\n\x1a\n"),
            'jpg', 'jpeg' => str_starts_with($contents, "\xFF\xD8\xFF"),
            'webp' => str_starts_with($contents, 'RIFF') && substr($contents, 8, 4) === 'WEBP',
            'docx', 'xlsx' => str_starts_with($contents, "PK\x03\x04"),
            'doc', 'xls' => str_starts_with($contents, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1"),
            'csv', 'txt' => !str_contains($contents, "\x00"),
            default => false,
        };

        if (!$validMagic) {
            throw ValidationException::withMessages([
                'file' => ["File content signature failed magic-byte verification for '.{$ext}'."],
            ]);
        }
    }
}
