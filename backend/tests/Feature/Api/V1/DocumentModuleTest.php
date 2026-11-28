<?php

namespace Tests\Feature\Api\V1;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\AuditLog;
use App\Core\Models\User;
use App\Modules\Beneficiary\Models\Household;
use App\Modules\DamageAssessment\Models\DamageAssessment;
use App\Modules\DamageAssessment\Models\DamageCategory;
use App\Modules\Disaster\Models\DisasterCategory;
use App\Modules\Disaster\Models\DisasterEvent;
use App\Modules\Document\Adapters\MockVirusScannerAdapter;
use App\Modules\Document\Models\Document;
use App\Modules\Document\Models\DocumentAccessLog;
use App\Modules\Document\Models\DocumentCategory;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectCategory;
use App\Modules\Relief\Models\ReliefRequisition;
use Database\Seeders\AdministrativeUnitSeeder;
use Database\Seeders\DocumentSeeder;
use Database\Seeders\UserManagementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentModuleTest extends TestCase
{
    use RefreshDatabase;

    protected string $privateKey;
    protected string $cleanX5cCert;
    protected string $kid = 'idms-doc-test-key-2026';
    protected string $realm = 'idms-gov-realm';
    protected string $issuer = 'http://localhost:8080/realms/idms-gov-realm';
    protected string $audience = 'idms-backend-api';

    protected AdministrativeUnit $nationalUnit;
    protected AdministrativeUnit $khulnaDistrict;
    protected AdministrativeUnit $barishalDistrict;
    protected AdministrativeUnit $dacopeUpazila;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('documents_private');
        Storage::fake('public');

        $this->seed(AdministrativeUnitSeeder::class);
        $this->seed(UserManagementSeeder::class);
        $this->seed(DocumentSeeder::class);

        $this->nationalUnit = AdministrativeUnit::where('tier_level', 'NATIONAL')->firstOrFail();
        $this->khulnaDistrict = AdministrativeUnit::where('code', '47')->firstOrFail();
        $this->barishalDistrict = AdministrativeUnit::where('code', '10')->firstOrFail();
        $this->dacopeUpazila = AdministrativeUnit::where('code', '12')->firstOrFail();

        $configPath = null;
        foreach ([
            'C:/wamp64/bin/php/php8.4.15/extras/ssl/openssl.cnf',
            'C:/wamp64/bin/php/php8.3.28/extras/ssl/openssl.cnf',
            'C:/wamp64/bin/apache/apache2.4.63/conf/openssl.cnf',
        ] as $candidate) {
            if (file_exists($candidate)) {
                $configPath = $candidate;
                putenv("OPENSSL_CONF={$candidate}");
                break;
            }
        }

        $sslOpts = $configPath ? ['config' => $configPath] : [];
        $res = openssl_pkey_new(array_merge($sslOpts, [
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]));
        $privKey = '';
        openssl_pkey_export($res, $privKey, null, $sslOpts);
        $this->privateKey = $privKey;

        $csr = openssl_csr_new([
            'countryName' => 'BD',
            'organizationName' => 'MoDMR',
            'commonName' => 'idms-test.modmr.gov.bd',
        ], $res, $sslOpts);
        $x509 = openssl_csr_sign($csr, null, $res, 365, $sslOpts);
        $certOut = '';
        openssl_x509_export($x509, $certOut);
        $this->cleanX5cCert = str_replace(
            ["-----BEGIN CERTIFICATE-----", "-----END CERTIFICATE-----", "\r", "\n"],
            '',
            $certOut
        );

        Cache::put("keycloak:jwks:{$this->realm}", [[
            'kid' => $this->kid,
            'kty' => 'RSA',
            'alg' => 'RS256',
            'use' => 'sig',
            'x5c' => [$this->cleanX5cCert],
        ]], 3600);
    }

    protected function createToken(array $payloadOverrides = []): string
    {
        $header = ['alg' => 'RS256', 'typ' => 'JWT', 'kid' => $this->kid];
        $now = time();
        $payload = array_merge([
            'iss' => $this->issuer,
            'sub' => (string) Str::uuid(),
            'aud' => $this->audience,
            'exp' => $now + 3600,
            'iat' => $now,
            'preferred_username' => 'doc_officer',
            'email' => 'doc.officer@modmr.gov.bd',
            'name' => 'Document Officer',
            'roles' => ['ROLE_DRRO'],
            'administrative_profile' => [
                'tier_level' => 'DISTRICT',
                'scope_id' => $this->khulnaDistrict->id,
                'ancestry_path' => $this->khulnaDistrict->ancestry_path,
            ],
        ], $payloadOverrides);

        $b64 = fn (array $d) => rtrim(strtr(base64_encode(json_encode($d)), '+/', '-_'), '=');
        $unsigned = $b64($header) . '.' . $b64($payload);
        $sig = '';
        openssl_sign($unsigned, $sig, $this->privateKey, OPENSSL_ALGO_SHA256);

        return $unsigned . '.' . rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');
    }

    protected function createUserWithToken(string $role, AdministrativeUnit $unit, string $username): array
    {
        $sub = (string) Str::uuid();
        $user = User::create([
            'id' => (string) Str::uuid(),
            'keycloak_sub' => $sub,
            'keycloak_username' => $username,
            'email' => "{$username}@modmr.gov.bd",
            'full_name_en' => ucwords(str_replace('_', ' ', $username)),
            'full_name_bn' => 'কর্মকর্তা',
            'role_code' => $role,
            'administrative_unit_id' => $unit->id,
            'is_active' => true,
            'account_status' => User::STATUS_ACTIVE,
        ]);

        $token = $this->createToken([
            'sub' => $sub,
            'preferred_username' => $username,
            'email' => $user->email,
            'roles' => [$role],
            'administrative_profile' => [
                'tier_level' => $unit->tier_level,
                'scope_id' => $unit->id,
                'ancestry_path' => $unit->ancestry_path,
            ],
        ]);

        return [$user, $token];
    }

    protected function makeFakePdf(string $name = 'go_order.pdf', string $body = 'MoDMR Official Order 2026'): UploadedFile
    {
        $content = "%PDF-1.4\n% {$body}\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n";
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    protected function makeFakePng(string $name = 'field_photo.png'): UploadedFile
    {
        $pngHeader = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x02\x00\x00\x00\x90wS\xDE";
        return UploadedFile::fake()->createWithContent($name, $pngHeader);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/documents')->assertStatus(401);
        $this->getJson('/api/v1/documents/dashboard')->assertStatus(401);
        $this->postJson('/api/v1/documents', [])->assertStatus(401);
    }

    public function test_upload_private_storage_download_preview_and_integrity_verification(): void
    {
        [, $pioToken] = $this->createUserWithToken('ROLE_PIO', $this->dacopeUpazila, 'pio_dacope');
        $goCategory = DocumentCategory::where('code', DocumentCategory::CODE_GOVERNMENT_ORDER)->firstOrFail();

        $pdf = $this->makeFakePdf('emergency_go.pdf', 'Cyclone Relief GO 2026');

        $res = $this->withHeaders(['Authorization' => "Bearer {$pioToken}"])
            ->post('/api/v1/documents', [
                'file' => $pdf,
                'title_bn' => 'জরুরি ত্রাণ বরাদ্দ সরকারি আদেশ',
                'title_en' => 'Emergency Relief Allocation Government Order',
                'reference_no' => '51.00.0000.123.26-405',
                'category_id' => $goCategory->id,
                'document_type' => Document::TYPE_GOVERNMENT_ORDER,
                'issuing_organization' => 'MoDMR',
                'classification' => Document::CLASS_CONFIDENTIAL,
                'tags' => ['cyclone', 'dacope'],
                'expires_at' => now()->addDays(15)->toDateString(),
                'submit_for_approval' => true,
            ]);

        $res->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title_en', 'Emergency Relief Allocation Government Order')
            ->assertJsonPath('data.classification', 'CONFIDENTIAL')
            ->assertJsonPath('data.is_confidential', true)
            ->assertJsonPath('data.approval_status', 'PENDING_APPROVAL')
            ->assertJsonPath('data.scan_status', 'CLEAN')
            ->assertJsonPath('data.current_version_number', 1)
            ->assertJsonPath('data.is_previewable', true);

        $docId = $res->json('data.id');
        $doc = Document::findOrFail($docId);

        // Verify raw storage_path is hidden from API responses and stored privately (never on public disk)
        $this->assertArrayNotHasKey('storage_path', $res->json('data'));
        Storage::disk('documents_private')->assertExists($doc->storage_path);
        $this->assertSame([], Storage::disk('public')->allFiles());

        // Authorized download & inline preview
        $downloadRes = $this->withHeaders(['Authorization' => "Bearer {$pioToken}"])
            ->get("/api/v1/documents/{$docId}/download");
        $downloadRes->assertStatus(200);
        $this->assertStringContainsString('attachment', (string) $downloadRes->headers->get('Content-Disposition'));

        $previewRes = $this->withHeaders(['Authorization' => "Bearer {$pioToken}"])
            ->get("/api/v1/documents/{$docId}/preview");
        $previewRes->assertStatus(200);
        $this->assertStringContainsString('inline', (string) $previewRes->headers->get('Content-Disposition'));

        // Signed temporary streaming URL + tamper protection
        $signedRes = $this->withHeaders(['Authorization' => "Bearer {$pioToken}"])
            ->postJson("/api/v1/documents/{$docId}/signed-url", ['ttl_minutes' => 10]);
        $signedRes->assertStatus(200);
        $signedUrl = $signedRes->json('data.url');
        $this->assertNotEmpty($signedUrl);

        $this->withHeaders(['Authorization' => "Bearer {$pioToken}"])
            ->get($signedUrl)
            ->assertStatus(200);

        $this->withHeaders(['Authorization' => "Bearer {$pioToken}"])
            ->get($signedUrl . 'tampered')
            ->assertStatus(403);

        // Integrity verification
        $this->withHeaders(['Authorization' => "Bearer {$pioToken}"])
            ->postJson("/api/v1/documents/{$docId}/verify-integrity")
            ->assertStatus(200)
            ->assertJsonPath('data.checksum_verified', true)
            ->assertJsonPath('data.storage_exists', true);

        // Access & modification history recorded
        $this->withHeaders(['Authorization' => "Bearer {$pioToken}"])
            ->getJson("/api/v1/documents/{$docId}/history")
            ->assertStatus(200);

        $this->assertTrue(
            DocumentAccessLog::where('document_id', $docId)
                ->where('action', DocumentAccessLog::ACTION_DOWNLOADED)
                ->exists()
        );
        $this->assertTrue(
            AuditLog::where('auditable_type', Document::class)
                ->where('auditable_id', $docId)
                ->where('action_name', 'DOCUMENT_INTEGRITY_VERIFIED')
                ->exists()
        );
    }

    public function test_security_rejects_infected_uploads_and_duplicate_references(): void
    {
        [, $pioToken] = $this->createUserWithToken('ROLE_PIO', $this->dacopeUpazila, 'pio_sec');
        $cat = DocumentCategory::where('code', DocumentCategory::CODE_DISASTER_REPORT)->firstOrFail();

        // Infected EICAR payload inside PDF header
        $infectedFile = UploadedFile::fake()->createWithContent(
            'eicar.pdf',
            "%PDF-1.4\n" . MockVirusScannerAdapter::EICAR_SIGNATURE . "\n%%EOF"
        );

        $this->withHeaders(['Authorization' => "Bearer {$pioToken}"])
            ->post('/api/v1/documents', [
                'file' => $infectedFile,
                'title_bn' => 'সংক্রমিত ফাইল',
                'title_en' => 'Infected File Test',
                'category_id' => $cat->id,
            ])
            ->assertStatus(422);

        // Duplicate reference_no in same category & jurisdiction prevented
        $this->withHeaders(['Authorization' => "Bearer {$pioToken}"])
            ->post('/api/v1/documents', [
                'file' => $this->makeFakePdf('sitrep1.pdf', 'Sitrep One'),
                'title_bn' => 'দুর্যোগ প্রতিবেদন ১',
                'title_en' => 'Sitrep One',
                'reference_no' => 'DUP-REF-2026-01',
                'category_id' => $cat->id,
            ])
            ->assertStatus(201);

        $this->withHeaders(['Authorization' => "Bearer {$pioToken}"])
            ->post('/api/v1/documents', [
                'file' => $this->makeFakePdf('sitrep2.pdf', 'Sitrep Two'),
                'title_bn' => 'দুর্যোগ প্রতিবেদন ২',
                'title_en' => 'Sitrep Two',
                'reference_no' => 'DUP-REF-2026-01',
                'category_id' => $cat->id,
            ])
            ->assertStatus(422);
    }

    public function test_version_management_and_four_eye_approval_workflow(): void
    {
        [, $pioToken] = $this->createUserWithToken('ROLE_PIO', $this->dacopeUpazila, 'pio_uploader');
        [, $drroToken] = $this->createUserWithToken('ROLE_DRRO', $this->khulnaDistrict, 'drro_approver');
        [, $superToken] = $this->createUserWithToken('ROLE_SUPER_ADMIN', $this->nationalUnit, 'super_admin_doc');

        $cat = DocumentCategory::where('code', DocumentCategory::CODE_APPROVAL_DOCUMENT)->firstOrFail();

        $createRes = $this->withHeaders(['Authorization' => "Bearer {$pioToken}"])
            ->post('/api/v1/documents', [
                'file' => $this->makeFakePdf('sanction_v1.pdf', 'Version 1 Body'),
                'title_bn' => 'বরাদ্দ মঞ্জুরি নথি',
                'title_en' => 'Sanction Order Draft',
                'category_id' => $cat->id,
                'submit_for_approval' => true,
            ])
            ->assertStatus(201);

        $docId = $createRes->json('data.id');
        $v1Id = $createRes->json('data.current_version_id');

        // 4-eye SoD: uploader cannot self-approve
        $this->withHeaders(['Authorization' => "Bearer {$pioToken}"])
            ->postJson("/api/v1/documents/{$docId}/approve", ['notes' => 'Self approve'])
            ->assertStatus(403);

        // Super Admin operational blackout on document approval
        $this->withHeaders(['Authorization' => "Bearer {$superToken}"])
            ->postJson("/api/v1/documents/{$docId}/approve", ['notes' => 'Super admin override'])
            ->assertStatus(403);

        // District DRRO approves (4-eye SoD satisfied)
        $this->withHeaders(['Authorization' => "Bearer {$drroToken}"])
            ->postJson("/api/v1/documents/{$docId}/approve", ['notes' => 'Verified and approved by DRRO'])
            ->assertStatus(200)
            ->assertJsonPath('data.approval_status', 'APPROVED');

        // Upload version 2 -> category requires approval, so approval_status resets to PENDING_APPROVAL
        $v2Res = $this->withHeaders(['Authorization' => "Bearer {$pioToken}"])
            ->post("/api/v1/documents/{$docId}/versions", [
                'file' => $this->makeFakePdf('sanction_v2.pdf', 'Version 2 Updated Body'),
                'version_label' => 'v2.0',
                'change_notes' => 'Updated budget annex',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.current_version_number', 2)
            ->assertJsonPath('data.approval_status', 'PENDING_APPROVAL');

        $this->assertCount(2, $v2Res->json('data.versions'));

        // Download version 1 specifically, then restore v1
        $this->withHeaders(['Authorization' => "Bearer {$drroToken}"])
            ->get("/api/v1/documents/{$docId}/versions/{$v1Id}/download")
            ->assertStatus(200);

        $this->withHeaders(['Authorization' => "Bearer {$pioToken}"])
            ->postJson("/api/v1/documents/{$docId}/versions/{$v1Id}/restore")
            ->assertStatus(200)
            ->assertJsonPath('data.current_version_number', 1);

        // Rejection requires mandatory reason, then succeeds and allows archival
        $this->withHeaders(['Authorization' => "Bearer {$drroToken}"])
            ->postJson("/api/v1/documents/{$docId}/reject", [])
            ->assertStatus(422);

        $this->withHeaders(['Authorization' => "Bearer {$drroToken}"])
            ->postJson("/api/v1/documents/{$docId}/reject", ['reason' => 'Missing annex signature'])
            ->assertStatus(200)
            ->assertJsonPath('data.approval_status', 'REJECTED')
            ->assertJsonPath('data.rejection_reason', 'Missing annex signature');

        // Metadata update & archive
        $this->withHeaders(['Authorization' => "Bearer {$pioToken}"])
            ->putJson("/api/v1/documents/{$docId}", [
                'title_en' => 'Sanction Order Revised Title',
                'tags' => ['sanction', 'revised'],
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.title_en', 'Sanction Order Revised Title');

        $this->withHeaders(['Authorization' => "Bearer {$pioToken}"])
            ->postJson("/api/v1/documents/{$docId}/archive", ['notes' => 'Superseded'])
            ->assertStatus(200)
            ->assertJsonPath('data.approval_status', 'ARCHIVED');
    }

    public function test_jurisdiction_isolation_and_external_partner_restrictions(): void
    {
        [$khulnaDrro, $khulnaDrroToken] = $this->createUserWithToken('ROLE_DRRO', $this->khulnaDistrict, 'drro_khulna');
        [, $barishalDrroToken] = $this->createUserWithToken('ROLE_DRRO', $this->barishalDistrict, 'drro_barishal');
        [, $pioDacopeToken] = $this->createUserWithToken('ROLE_PIO', $this->dacopeUpazila, 'pio_dacope_iso');
        [, $extToken] = $this->createUserWithToken('ROLE_EXTERNAL_PARTNER', $this->nationalUnit, 'ext_wfp');

        $cat = DocumentCategory::where('code', DocumentCategory::CODE_GOVERNMENT_ORDER)->firstOrFail();

        // Confidential document in Khulna District
        $khulnaDocRes = $this->withHeaders(['Authorization' => "Bearer {$khulnaDrroToken}"])
            ->post('/api/v1/documents', [
                'file' => $this->makeFakePdf('khulna_conf.pdf', 'Khulna Confidential'),
                'title_bn' => 'খুলনা গোপনীয় নথি',
                'title_en' => 'Khulna Confidential Order',
                'category_id' => $cat->id,
                'classification' => Document::CLASS_CONFIDENTIAL,
            ])
            ->assertStatus(201);
        $khulnaDocId = $khulnaDocRes->json('data.id');

        // Public approved document in Dacope Upazila
        $dacopePublicRes = $this->withHeaders(['Authorization' => "Bearer {$pioDacopeToken}"])
            ->post('/api/v1/documents', [
                'file' => $this->makeFakePdf('dacope_pub.pdf', 'Dacope Public Notice'),
                'title_bn' => 'দাকোপ গণবিজ্ঞপ্তি',
                'title_en' => 'Dacope Public Bulletin',
                'category_id' => $cat->id,
                'classification' => Document::CLASS_PUBLIC,
                'submit_for_approval' => true,
            ])
            ->assertStatus(201);
        $dacopePublicId = $dacopePublicRes->json('data.id');

        $this->withHeaders(['Authorization' => "Bearer {$khulnaDrroToken}"])
            ->postJson("/api/v1/documents/{$dacopePublicId}/approve", ['notes' => 'Approved'])
            ->assertStatus(200);

        // Barishal DRRO is laterally isolated from Khulna & Dacope
        $this->withHeaders(['Authorization' => "Bearer {$barishalDrroToken}"])
            ->getJson("/api/v1/documents/{$khulnaDocId}")
            ->assertStatus(403);
        $this->withHeaders(['Authorization' => "Bearer {$barishalDrroToken}"])
            ->getJson("/api/v1/documents/{$dacopePublicId}")
            ->assertStatus(403);

        // Dacope PIO cannot view Khulna District parent document
        $this->withHeaders(['Authorization' => "Bearer {$pioDacopeToken}"])
            ->getJson("/api/v1/documents/{$khulnaDocId}")
            ->assertStatus(403);

        // Khulna DRRO CAN view descendant Dacope document
        $this->withHeaders(['Authorization' => "Bearer {$khulnaDrroToken}"])
            ->getJson("/api/v1/documents/{$dacopePublicId}")
            ->assertStatus(200);

        // External partner: blocked from uploading, blocked from confidential doc, allowed on approved PUBLIC doc
        $this->withHeaders(['Authorization' => "Bearer {$extToken}"])
            ->post('/api/v1/documents', [
                'file' => $this->makeFakePdf('ext.pdf'),
                'title_bn' => 'বাহ্যিক',
                'title_en' => 'External Attempt',
                'category_id' => $cat->id,
            ])
            ->assertStatus(403);

        $this->withHeaders(['Authorization' => "Bearer {$extToken}"])
            ->getJson("/api/v1/documents/{$khulnaDocId}")
            ->assertStatus(403);

        $this->withHeaders(['Authorization' => "Bearer {$extToken}"])
            ->getJson("/api/v1/documents/{$dacopePublicId}")
            ->assertStatus(200);
    }

    public function test_cross_module_entity_attachment_dashboard_filtering_and_categories(): void
    {
        [$pioUser, $pioToken] = $this->createUserWithToken('ROLE_PIO', $this->dacopeUpazila, 'pio_int');
        [, $superToken] = $this->createUserWithToken('ROLE_SUPER_ADMIN', $this->nationalUnit, 'super_cat');

        $disasterCat = DisasterCategory::create([
            'id' => (string) Str::uuid(),
            'code' => 'CYCLONE',
            'name_en' => 'Cyclone',
            'name_bn' => 'ঘূর্ণিঝড়',
            'is_system' => true,
            'is_active' => true,
        ]);

        $disaster = DisasterEvent::create([
            'id' => (string) Str::uuid(),
            'category_id' => $disasterCat->id,
            'disaster_code' => 'DIS-2026-DOC1',
            'name_en' => 'Cyclone Shakti',
            'name_bn' => 'ঘূর্ণিঝড় শক্তি',
            'disaster_type' => 'CYCLONE',
            'severity_level' => 'DANGER',
            'lifecycle_phase' => 'ACTIVE',
            'declaration_date' => now(),
            'start_date' => now()->toDateString(),
            'is_national' => true,
            'created_by' => $pioUser->id,
        ]);

        $household = Household::create([
            'id' => (string) Str::uuid(),
            'household_code' => 'HH-DAC-001',
            'administrative_unit_id' => $this->dacopeUpazila->id,
            'hoh_name_en' => 'Abdul Karim',
            'hoh_name_bn' => 'আব্দুল করিম',
            'hoh_nid_hash' => hash('sha256', '1990471234567'),
            'hoh_nid_encrypted' => encrypt('1990471234567'),
            'hoh_nid_masked' => '1990******567',
            'hoh_gender' => 'MALE',
            'family_member_count' => 4,
            'vulnerability_score' => 75,
            'verification_status' => 'VERIFIED',
            'created_by' => $pioUser->id,
        ]);

        $photoCat = DocumentCategory::where('code', DocumentCategory::CODE_PHOTOGRAPH)->firstOrFail();

        // Attach photograph to Beneficiary Household
        $attachRes = $this->withHeaders(['Authorization' => "Bearer {$pioToken}"])
            ->post('/api/v1/documents', [
                'file' => $this->makeFakePng('household_damage.png'),
                'title_bn' => 'পরিবারের বসতঘরের ছবি',
                'title_en' => 'Household Shelter Photo',
                'category_id' => $photoCat->id,
                'document_type' => Document::TYPE_PHOTOGRAPH,
                'entity_type' => 'HOUSEHOLD',
                'entity_id' => $household->id,
                'expires_at' => now()->addDays(10)->toDateString(),
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.module_code', 'BENEFICIARY')
            ->assertJsonPath('data.entity_type', 'HOUSEHOLD')
            ->assertJsonPath('data.entity_reference', 'HH-DAC-001');

        $docId = $attachRes->json('data.id');

        // Entity lookup & entity documents listing
        $this->withHeaders(['Authorization' => "Bearer {$pioToken}"])
            ->getJson('/api/v1/documents/target-entities?entity_type=DISASTER_EVENT')
            ->assertStatus(200)
            ->assertJsonFragment(['entity_reference' => 'DIS-2026-DOC1']);

        $this->withHeaders(['Authorization' => "Bearer {$pioToken}"])
            ->getJson("/api/v1/documents/entity/HOUSEHOLD/{$household->id}")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        // Dashboard metrics
        $this->withHeaders(['Authorization' => "Bearer {$pioToken}"])
            ->getJson('/api/v1/documents/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('data.kpis.total_documents', 1)
            ->assertJsonPath('data.kpis.expiring_soon_count', 1);

        // Search & filter
        $this->withHeaders(['Authorization' => "Bearer {$pioToken}"])
            ->getJson('/api/v1/documents?search=HH-DAC-001&expiring_within_days=30')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1);

        // Configurable category CRUD & system category delete protection
        $customCatRes = $this->withHeaders(['Authorization' => "Bearer {$superToken}"])
            ->postJson('/api/v1/documents/categories', [
                'code' => 'ENGINEERING_BLUEPRINT',
                'name_en' => 'Engineering Blueprints',
                'name_bn' => 'প্রকৌশল নকশা',
                'default_classification' => 'INTERNAL',
                'allowed_formats' => ['PDF', 'JPEG'],
                'requires_approval' => true,
            ])
            ->assertStatus(201);

        $customCatId = $customCatRes->json('data.id');

        $this->withHeaders(['Authorization' => "Bearer {$superToken}"])
            ->deleteJson("/api/v1/documents/categories/{$photoCat->id}")
            ->assertStatus(422);

        $this->withHeaders(['Authorization' => "Bearer {$superToken}"])
            ->deleteJson("/api/v1/documents/categories/{$customCatId}")
            ->assertStatus(200);

        // Soft delete document
        $this->withHeaders(['Authorization' => "Bearer {$pioToken}"])
            ->deleteJson("/api/v1/documents/{$docId}")
            ->assertStatus(200);
    }
}
