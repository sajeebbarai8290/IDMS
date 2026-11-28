<?php

namespace Tests\Unit\Document;

use App\Modules\Document\Adapters\GovObjectStoreAdapter;
use App\Modules\Document\Adapters\LaravelPrivateStorageAdapter;
use App\Modules\Document\Adapters\MockVirusScannerAdapter;
use App\Modules\Document\Services\FileSecurityValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class DocumentSecurityAndAdapterTest extends TestCase
{
    public function test_validator_accepts_valid_pdf_and_computes_sha256_and_uuid_path(): void
    {
        $validator = new FileSecurityValidator(new MockVirusScannerAdapter());
        $pdfBytes = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n";
        $file = UploadedFile::fake()->createWithContent('Circular_GO_2026.pdf', $pdfBytes);

        $result = $validator->validateAndScan($file);

        $this->assertSame('application/pdf', $result['mime_type']);
        $this->assertSame('PDF', $result['extension']);
        $this->assertSame(hash('sha256', $pdfBytes), $result['file_hash_sha256']);
        $this->assertSame('CLEAN', $result['scan_status']);
        $this->assertMatchesRegularExpression('#^docs/\d{4}/\d{2}/[0-9a-f-]{36}\.pdf$#', $result['storage_path']);
    }

    public function test_validator_rejects_spoofed_extension_and_executable_payloads(): void
    {
        $validator = new FileSecurityValidator(new MockVirusScannerAdapter());

        $spoofedPdf = UploadedFile::fake()->createWithContent('malicious.pdf', "<?php system(\$_GET['cmd']); ?>");
        $this->expectException(ValidationException::class);
        $validator->validateAndScan($spoofedPdf);
    }

    public function test_validator_rejects_infected_file_via_virus_scanner_hook(): void
    {
        $validator = new FileSecurityValidator(new MockVirusScannerAdapter());
        $infectedContent = "%PDF-1.4\n" . MockVirusScannerAdapter::EICAR_SIGNATURE . "\n%%EOF";
        $file = UploadedFile::fake()->createWithContent('infected.pdf', $infectedContent);

        try {
            $validator->validateAndScan($file);
            $this->fail('Expected ValidationException for infected file.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('file', $e->errors());
            $this->assertStringContainsString('Malware detected', $e->errors()['file'][0]);
        }
    }

    public function test_gov_object_store_adapter_fails_closed_when_not_configured(): void
    {
        config([
            'filesystems.disks.gov_object_store.bucket' => null,
            'filesystems.disks.gov_object_store.key' => null,
            'filesystems.disks.gov_object_store.secret' => null,
        ]);

        $adapter = new GovObjectStoreAdapter();
        $health = $adapter->health();

        $this->assertFalse($health['ready']);
        $this->assertTrue($health['public_urls_disabled']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('GOV_STORAGE_NOT_CONFIGURED');
        $adapter->put('docs/test.pdf', '%PDF-1.4');
    }

    public function test_private_storage_adapter_reports_private_non_public_health(): void
    {
        $adapter = new LaravelPrivateStorageAdapter();
        $health = $adapter->health();

        $this->assertSame('laravel_private', $health['driver']);
        $this->assertSame('documents_private', $health['disk']);
        $this->assertTrue($health['ready']);
        $this->assertTrue($health['public_urls_disabled']);
    }
}
