<?php

namespace Tests\Unit\NidVerification;

use App\Modules\NidVerification\Adapters\AuthorizedGovernmentNidAdapter;
use App\Modules\NidVerification\Adapters\MockNidAdapter;
use App\Modules\NidVerification\DTOs\NidVerificationRequestDto;
use App\Modules\NidVerification\Exceptions\NidProviderUnavailableException;
use App\Modules\NidVerification\Models\NidVerification;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NidAdapterTest extends TestCase
{
    public function test_request_dto_masks_nid_in_debug_info(): void
    {
        $dto = new NidVerificationRequestDto(
            nidNumber: '1990471234567',
            dateOfBirth: '1990-05-12',
            nameEn: 'Test Citizen',
            verificationReference: 'NID-TEST-001'
        );

        $debug = $dto->__debugInfo();
        $this->assertStringNotContainsString('1990471234567', json_encode($debug));
        $this->assertSame('19*********67', $debug['nidNumber']);
    }

    public function test_mock_adapter_handles_valid_and_fixture_cases(): void
    {
        $adapter = new MockNidAdapter();
        $this->assertTrue($adapter->isMockProvider());
        $this->assertTrue($adapter->isProviderAvailable());

        $ok = $adapter->verifyNid(new NidVerificationRequestDto('1990471234567', '1990-05-12'));
        $this->assertSame(NidVerification::STATUS_VERIFIED, $ok->status);
        $this->assertTrue($ok->isValid);
        $this->assertTrue($ok->isMock);

        $notFound = $adapter->verifyNid(new NidVerificationRequestDto('0000000000', '1990-01-01'));
        $this->assertSame(NidVerification::STATUS_FAILED, $notFound->status);
        $this->assertSame('NID_NOT_FOUND', $notFound->errorCode);

        $deceased = $adapter->verifyNid(new NidVerificationRequestDto('9999999999', '1970-01-01'));
        $this->assertSame(NidVerification::STATUS_FAILED, $deceased->status);
        $this->assertSame('CITIZEN_DECEASED', $deceased->errorCode);

        $manual = $adapter->verifyNid(new NidVerificationRequestDto('8888888888', '1988-02-02'));
        $this->assertSame(NidVerification::STATUS_MANUAL_REVIEW_REQUIRED, $manual->status);
        $this->assertSame('DEMOGRAPHIC_MISMATCH', $manual->errorCode);
    }

    public function test_mock_adapter_throws_retryable_exception_on_timeout_fixture(): void
    {
        $adapter = new MockNidAdapter();

        $this->expectException(NidProviderUnavailableException::class);
        $adapter->verifyNid(new NidVerificationRequestDto('7777777777', '1985-03-03'));
    }

    public function test_government_adapter_fails_closed_when_unconfigured(): void
    {
        config()->set('services.nid.base_url', null);
        config()->set('services.nid.verify_path', null);
        config()->set('services.nid.api_key', null);

        $adapter = new AuthorizedGovernmentNidAdapter();
        $this->assertFalse($adapter->isMockProvider());
        $this->assertFalse($adapter->isProviderAvailable());

        $this->expectException(NidProviderUnavailableException::class);
        $adapter->verifyNid(new NidVerificationRequestDto('1990471234567', '1990-05-12'));
    }

    public function test_government_adapter_calls_configured_http_contract_safely(): void
    {
        config()->set('services.nid.base_url', 'https://nid-gateway.example.test');
        config()->set('services.nid.verify_path', '/v1/verify');
        config()->set('services.nid.api_key', 'test-secret-key');
        config()->set('services.nid.timeout_seconds', 3);

        Http::fake([
            'https://nid-gateway.example.test/v1/verify' => Http::response([
                'verified' => true,
                'nid_found' => true,
                'dob_matched' => true,
                'name_matched' => true,
                'verification_reference' => 'EC-REF-9988',
            ], 200),
        ]);

        $adapter = new AuthorizedGovernmentNidAdapter();
        $this->assertTrue($adapter->isProviderAvailable());

        $result = $adapter->verifyNid(new NidVerificationRequestDto('1990471234567', '1990-05-12', 'Test'));
        $this->assertSame(NidVerification::STATUS_VERIFIED, $result->status);
        $this->assertFalse($result->isMock);
        $this->assertSame('EC-REF-9988', $result->verificationToken);
    }
}
