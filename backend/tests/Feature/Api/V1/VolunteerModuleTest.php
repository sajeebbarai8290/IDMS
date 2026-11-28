<?php

namespace Tests\Feature\Api\V1;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\AuditLog;
use App\Modules\Disaster\Models\DisasterEvent;
use App\Modules\Response\Models\ResponseOperation;
use App\Modules\Volunteer\Models\SkillCatalog;
use App\Modules\Volunteer\Models\Volunteer;
use App\Modules\Volunteer\Models\VolunteerCategory;
use App\Modules\Volunteer\Models\VolunteerOrganization;
use App\Modules\Volunteer\Models\VolunteerTrainingProgram;
use Database\Seeders\AdministrativeUnitSeeder;
use Database\Seeders\DisasterEventSeeder;
use Database\Seeders\ResponseCatalogSeeder;
use Database\Seeders\VolunteerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class VolunteerModuleTest extends TestCase
{
    use RefreshDatabase;

    protected string $privateKey;
    protected string $cleanX5cCert;
    protected string $kid = 'idms-volunteer-test-key-2026';
    protected string $realm = 'idms-gov-realm';
    protected string $issuer = 'http://localhost:8080/realms/idms-gov-realm';
    protected string $audience = 'idms-backend-api';

    protected AdministrativeUnit $chalnaUnion;
    protected AdministrativeUnit $dacopeUpazila;
    protected AdministrativeUnit $khulnaDistrict;
    protected AdministrativeUnit $barishalDistrict;
    protected DisasterEvent $disaster;
    protected VolunteerCategory $sarCategory;
    protected SkillCatalog $firstAidSkill;
    protected VolunteerOrganization $cppOrg;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->seed(AdministrativeUnitSeeder::class);
        $this->seed(DisasterEventSeeder::class);
        $this->seed(ResponseCatalogSeeder::class);
        $this->seed(VolunteerSeeder::class);

        $this->chalnaUnion = AdministrativeUnit::where('code', '35')->firstOrFail();
        $this->dacopeUpazila = AdministrativeUnit::where('code', '12')->firstOrFail();
        $this->khulnaDistrict = AdministrativeUnit::where('code', '47')->firstOrFail();
        $this->barishalDistrict = AdministrativeUnit::where('code', '10')->firstOrFail();
        $this->disaster = DisasterEvent::where('disaster_code', 'CYC-REMAL-2024')->firstOrFail();
        $this->sarCategory = VolunteerCategory::where('code', 'SEARCH_AND_RESCUE')->firstOrFail();
        $this->firstAidSkill = SkillCatalog::where('skill_code', 'FIRST_AID_CPR')->firstOrFail();
        $this->cppOrg = VolunteerOrganization::where('org_code', 'ORG-CPP-KHL-01')->firstOrFail();

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

        $dn = [
            'countryName' => 'BD',
            'organizationName' => 'Ministry of Disaster Management and Relief',
            'commonName' => 'idms-keycloak.modmr.gov.bd',
        ];
        $csr = openssl_csr_new($dn, $res, $sslOpts);
        $x509 = openssl_csr_sign($csr, null, $res, 365, $sslOpts);
        $certOut = '';
        openssl_x509_export($x509, $certOut);

        $this->cleanX5cCert = str_replace(
            ["-----BEGIN CERTIFICATE-----", "-----END CERTIFICATE-----", "\r", "\n"],
            '',
            $certOut
        );

        Cache::put("keycloak:jwks:{$this->realm}", [
            [
                'kid' => $this->kid,
                'kty' => 'RSA',
                'alg' => 'RS256',
                'use' => 'sig',
                'x5c' => [$this->cleanX5cCert],
            ],
        ], 3600);
    }

    protected function makeJwt(string $role, AdministrativeUnit $unit, ?string $sub = null): string
    {
        $now = time();
        $payload = [
            'iss' => $this->issuer,
            'sub' => $sub ?? (string) Str::uuid(),
            'aud' => $this->audience,
            'exp' => $now + 3600,
            'nbf' => $now - 10,
            'iat' => $now,
            'preferred_username' => strtolower($role),
            'email' => strtolower($role) . '.' . $unit->code . '@modmr.gov.bd',
            'name' => 'Officer ' . $role,
            'roles' => [$role],
            'administrative_profile' => [
                'tier_level' => $unit->tier_level,
                'scope_id' => $unit->id,
                'ancestry_path' => $unit->ancestry_path,
            ],
        ];

        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT', 'kid' => $this->kid]));
        $body = $this->base64UrlEncode(json_encode($payload));
        $signingInput = "{$header}.{$body}";

        $signature = '';
        openssl_sign($signingInput, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);

        return "{$signingInput}." . $this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public function test_unauthenticated_volunteer_routes_return_401(): void
    {
        $this->getJson('/api/v1/volunteer/dashboard')->assertStatus(401);
        $this->getJson('/api/v1/volunteer/volunteers')->assertStatus(401);
        $this->postJson('/api/v1/volunteer/volunteers', [])->assertStatus(401);
        $this->getJson('/api/v1/volunteer/deployments')->assertStatus(401);
    }

    public function test_super_admin_blackout_allows_categories_and_view_but_blocks_operational_actions(): void
    {
        $nationalUnit = AdministrativeUnit::where('code', '00')->firstOrFail();
        $superToken = $this->makeJwt('ROLE_SUPER_ADMIN', $nationalUnit);
        $existingVol = Volunteer::firstOrFail();

        // Allowed: view dashboard, configure categories/skills/programs, export
        $this->withToken($superToken)->getJson('/api/v1/volunteer/dashboard')->assertStatus(200);
        $this->withToken($superToken)->postJson('/api/v1/volunteer/categories', [
            'code' => 'PSYCHOSOCIAL_SUPPORT',
            'name_en' => 'Psychosocial Support',
            'name_bn' => 'মনোসামাজিক সহায়তা',
        ])->assertStatus(201);
        $this->withToken($superToken)->postJson('/api/v1/volunteer/skills', [
            'skill_code' => 'DRONE_RECON',
            'name_en' => 'Drone Reconnaissance',
            'name_bn' => 'ড্রোন পর্যবেক্ষণ',
        ])->assertStatus(201);
        $this->withToken($superToken)->get('/api/v1/volunteer/export?report_type=register')->assertStatus(200);

        // Blocked under Super Admin Operational Blackout
        $this->withToken($superToken)->postJson('/api/v1/volunteer/volunteers', [
            'administrative_unit_id' => $this->dacopeUpazila->id,
            'full_name_en' => 'Blocked Test',
            'full_name_bn' => 'ব্লকড টেস্ট',
            'mobile_number' => '01799999999',
            'emergency_contact_name' => 'Contact',
            'emergency_contact_phone' => '01899999999',
        ])->assertStatus(403);

        $this->withToken($superToken)->postJson("/api/v1/volunteer/volunteers/{$existingVol->id}/approve", [])->assertStatus(403);
        $this->withToken($superToken)->postJson('/api/v1/volunteer/deployments', [
            'volunteer_id' => $existingVol->id,
            'administrative_unit_id' => $this->dacopeUpazila->id,
            'deployment_location' => 'Test Shelter',
            'assigned_role' => 'FIRST_AID',
            'assigned_duties' => 'Assist evacuees',
            'start_date' => now()->toDateString(),
            'supervisor_name' => 'Test Sup',
        ])->assertStatus(403);
    }

    public function test_volunteer_registration_pii_protection_photo_duplicate_nid_and_4eye_verification(): void
    {
        $pioSub = (string) Str::uuid();
        $drroSub = (string) Str::uuid();
        $pioToken = $this->makeJwt('ROLE_PIO', $this->dacopeUpazila, $pioSub);
        $drroToken = $this->makeJwt('ROLE_DRRO', $this->khulnaDistrict, $drroSub);
        $barishalToken = $this->makeJwt('ROLE_DRRO', $this->barishalDistrict);
        $externalToken = $this->makeJwt('ROLE_EXTERNAL_PARTNER', $this->khulnaDistrict);

        $payload = [
            'administrative_unit_id' => $this->chalnaUnion->id,
            'organization_id' => $this->cppOrg->id,
            'primary_category_id' => $this->sarCategory->id,
            'full_name_en' => 'Md. Tariqul Islam',
            'full_name_bn' => 'মো. তারিকুল ইসলাম',
            'gender' => 'MALE',
            'date_of_birth' => '1990-05-10',
            'nid_number' => '1990123456789',
            'mobile_number' => '01755667788',
            'email' => 'tariqul.vol@example.org',
            'present_address' => 'Chalna Ward 04, Dacope',
            'emergency_contact_name' => 'Salma Begum',
            'emergency_contact_relation' => 'Spouse',
            'emergency_contact_phone' => '01855667788',
            'location_consent_given' => true,
            'latitude' => 22.5965,
            'longitude' => 89.5128,
            'submit_for_verification' => true,
            'initiate_nid_check' => true,
            'skills' => [
                ['skill_id' => $this->firstAidSkill->id, 'proficiency_level' => 'INTERMEDIATE'],
            ],
        ];

        $createRes = $this->withToken($pioToken)
            ->postJson('/api/v1/volunteer/volunteers', $payload)
            ->assertStatus(201);

        $volId = $createRes->json('data.id');
        // PII must be masked by default
        $this->assertStringNotContainsString('123456', (string) $createRes->json('data.nid_masked'));
        $this->assertSame('017*****788', $createRes->json('data.mobile_masked'));
        $this->assertNull($createRes->json('data.nid_encrypted'));
        // Hybrid NID check via MockNidAdapter succeeded
        $this->assertSame('VERIFIED', $createRes->json('data.identity_verification_status'));

        // Duplicate NID must be rejected
        $this->withToken($pioToken)
            ->postJson('/api/v1/volunteer/volunteers', $payload)
            ->assertStatus(422);

        // Private photo upload & download
        $photo = UploadedFile::fake()->image('volunteer.jpg', 300, 300);
        $this->withToken($pioToken)
            ->post("/api/v1/volunteer/volunteers/{$volId}/photo", ['photo' => $photo])
            ->assertStatus(200)
            ->assertJsonPath('data.has_photo', true);
        $this->withToken($pioToken)
            ->get("/api/v1/volunteer/volunteers/{$volId}/photo")
            ->assertStatus(200);

        // Gated PII reveal + immutable audit log
        $this->withToken($pioToken)
            ->postJson("/api/v1/volunteer/volunteers/{$volId}/reveal-pii")
            ->assertStatus(200)
            ->assertJsonPath('data.nid_number', '1990123456789')
            ->assertJsonPath('data.mobile_number', '01755667788');

        $this->assertTrue(
            AuditLog::where('action_name', 'VOLUNTEER_PII_DECRYPTED')
                ->where('auditable_id', $volId)
                ->exists()
        );

        // Lateral isolation: Barishal DRRO cannot view Khulna volunteer
        $this->withToken($barishalToken)->getJson("/api/v1/volunteer/volunteers/{$volId}")->assertStatus(403);

        // 4-eye SoD: submitting PIO cannot verify/approve own submission
        $this->withToken($pioToken)->postJson("/api/v1/volunteer/volunteers/{$volId}/approve", [])->assertStatus(403);

        // DRRO Khulna verifies pillars and approves volunteer
        $this->withToken($drroToken)->postJson("/api/v1/volunteer/volunteers/{$volId}/verify-pillar", [
            'pillar' => 'ORGANIZATION',
            'status' => 'VERIFIED',
            'remarks' => 'Verified with CPP unit register.',
        ])->assertStatus(200)->assertJsonPath('data.organization_verification_status', 'VERIFIED');

        $this->withToken($drroToken)->postJson("/api/v1/volunteer/volunteers/{$volId}/verify-pillar", [
            'pillar' => 'TRAINING',
            'status' => 'VERIFIED',
        ])->assertStatus(200);

        $this->withToken($drroToken)->postJson("/api/v1/volunteer/volunteers/{$volId}/approve", [
            'remarks' => 'Cleared for deployment.',
        ])->assertStatus(200)->assertJsonPath('data.overall_verification_status', 'VERIFIED');

        // External partner gets anonymized data & zero PII/photos
        $extDetail = $this->withToken($externalToken)->getJson("/api/v1/volunteer/volunteers/{$volId}")->assertStatus(200);
        $this->assertTrue($extDetail->json('data.pii_redacted'));
        $this->assertSame('Verified Volunteer', $extDetail->json('data.full_name_en'));
        $this->assertNull($extDetail->json('data.mobile_masked'));
        $this->assertTrue($extDetail->json('data.location_masked'));
        $this->withToken($externalToken)->postJson("/api/v1/volunteer/volunteers/{$volId}/reveal-pii")->assertStatus(403);
        $this->withToken($externalToken)->get("/api/v1/volunteer/volunteers/{$volId}/photo")->assertStatus(403);
    }

    public function test_deployment_availability_locking_attendance_gps_consent_and_supervisor_confirmation(): void
    {
        $pioSub = (string) Str::uuid();
        $volSub = (string) Str::uuid();
        $pioToken = $this->makeJwt('ROLE_PIO', $this->dacopeUpazila, $pioSub);
        $volToken = $this->makeJwt('ROLE_VOLUNTEER_RESPONDER', $this->chalnaUnion, $volSub);

        $userModel = \App\Core\Models\User::create([
            'id' => $volSub,
            'keycloak_sub' => $volSub,
            'email' => 'vol.responder@modmr.gov.bd',
            'full_name_en' => 'Volunteer Self',
            'role_code' => 'ROLE_VOLUNTEER_RESPONDER',
            'administrative_unit_id' => $this->chalnaUnion->id,
            'is_active' => true,
        ]);

        // Verified volunteer linked to user_id
        $verifiedVol = Volunteer::where('volunteer_code', 'VOL-CPP-404712-001')->firstOrFail();
        $verifiedVol->update(['user_id' => $userModel->id]);

        $operation = ResponseOperation::first();

        // Deploy volunteer
        $depRes = $this->withToken($pioToken)->postJson('/api/v1/volunteer/deployments', [
            'volunteer_id' => $verifiedVol->id,
            'disaster_event_id' => $this->disaster->id,
            'response_operation_id' => $operation?->id,
            'administrative_unit_id' => $this->chalnaUnion->id,
            'deployment_location' => 'Chalna Cyclone Shelter #1',
            'latitude' => 22.5960,
            'longitude' => 89.5120,
            'assigned_role' => 'EVACUATION_SUPPORT',
            'assigned_duties' => 'Coordinate coastal shelter intake and megaphone alert.',
            'start_date' => now()->toDateString(),
            'supervisor_name' => 'PIO Dacope',
            'activate_immediately' => true,
        ])->assertStatus(201);

        $depId = $depRes->json('data.id');
        $this->assertSame('DEPLOYED', $verifiedVol->fresh()->availability_status);

        // Overlapping deployment must fail (422)
        $this->withToken($pioToken)->postJson('/api/v1/volunteer/deployments', [
            'volunteer_id' => $verifiedVol->id,
            'disaster_event_id' => $this->disaster->id,
            'administrative_unit_id' => $this->chalnaUnion->id,
            'deployment_location' => 'Another Location',
            'assigned_role' => 'FIRST_AID',
            'assigned_duties' => 'Double deploy attempt',
            'start_date' => now()->toDateString(),
            'supervisor_name' => 'PIO Dacope',
        ])->assertStatus(422);

        // Volunteer self check-in WITH explicit GPS consent
        $checkInRes = $this->withToken($volToken)->postJson("/api/v1/volunteer/deployments/{$depId}/check-in", [
            'check_in_at' => now()->subHours(4)->toIso8601String(),
            'gps_consent_given' => true,
            'latitude' => 22.5961,
            'longitude' => 89.5121,
            'activity_summary' => 'Started morning shift at shelter gate.',
        ])->assertStatus(201);

        $attId = $checkInRes->json('data.id');

        // Volunteer self check-out & activity report
        $checkOutRes = $this->withToken($volToken)->postJson("/api/v1/volunteer/attendances/{$attId}/check-out", [
            'check_out_at' => now()->toIso8601String(),
            'activity_summary' => 'Assisted 45 elderly and children into cyclone shelter.',
            'tasks_completed' => 'Evacuation intake & ORS distribution.',
            'beneficiaries_assisted' => 45,
        ])->assertStatus(200);

        $this->assertGreaterThanOrEqual(239, $checkOutRes->json('data.duration_minutes'));

        // Volunteer cannot confirm own attendance (403)
        $this->withToken($volToken)->postJson("/api/v1/volunteer/attendances/{$attId}/confirm", [
            'confirmation_status' => 'CONFIRMED',
        ])->assertStatus(403);

        // PIO supervisor confirms attendance
        $this->withToken($pioToken)->postJson("/api/v1/volunteer/attendances/{$attId}/confirm", [
            'confirmation_status' => 'CONFIRMED',
            'remarks' => 'Shift verified on-site.',
        ])->assertStatus(200)->assertJsonPath('data.confirmation_status', 'CONFIRMED');

        // Complete deployment -> volunteer returns to AVAILABLE
        $this->withToken($pioToken)->postJson("/api/v1/volunteer/deployments/{$depId}/complete", [
            'notes' => 'Duty completed.',
        ])->assertStatus(200)->assertJsonPath('data.deployment_status', 'COMPLETED');

        $this->assertSame('AVAILABLE', $verifiedVol->fresh()->availability_status);
    }

    public function test_training_program_session_enrollment_certification_dashboard_and_reports(): void
    {
        $pioToken = $this->makeJwt('ROLE_PIO', $this->dacopeUpazila);
        $vol = Volunteer::where('volunteer_code', 'VOL-CPP-404712-001')->firstOrFail();

        $progRes = $this->withToken($pioToken)->postJson('/api/v1/volunteer/training-programs', [
            'title_en' => 'Swift Water Boat Rescue',
            'title_bn' => 'স্রোতে নৌকা উদ্ধার প্রশিক্ষণ',
            'category_id' => $this->sarCategory->id,
            'skill_id' => $this->firstAidSkill->id,
            'provider_name' => 'Fire Service & Civil Defence',
            'duration_hours' => 24,
            'validity_months' => 36,
        ])->assertStatus(201);

        $progId = $progRes->json('data.id');

        $sesRes = $this->withToken($pioToken)->postJson('/api/v1/volunteer/training-sessions', [
            'program_id' => $progId,
            'administrative_unit_id' => $this->dacopeUpazila->id,
            'venue' => 'Dacope River Jetty Training Ground',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'trainer_name' => 'Station Officer FSCD',
            'max_participants' => 20,
        ])->assertStatus(201);

        $sesId = $sesRes->json('data.id');

        $enrRes = $this->withToken($pioToken)->postJson("/api/v1/volunteer/training-sessions/{$sesId}/enroll", [
            'volunteer_id' => $vol->id,
        ])->assertStatus(201);

        $enrId = $enrRes->json('data.id');

        $certRes = $this->withToken($pioToken)->putJson("/api/v1/volunteer/training-enrollments/{$enrId}", [
            'enrollment_status' => 'ATTENDED',
            'attendance_hours' => 24,
            'assessment_score' => 95,
            'passed' => true,
        ])->assertStatus(200);

        $this->assertNotEmpty($certRes->json('data.certificate_no'));

        // Lookups, Dashboard, GeoJSON, List Endpoints, Reports & CSV Export
        $this->withToken($pioToken)->getJson('/api/v1/volunteer/lookups')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'categories',
                    'skills',
                    'organizations',
                    'disasters',
                    'response_operations',
                    'administrative_units',
                ],
            ]);

        $this->withToken($pioToken)->getJson('/api/v1/volunteer/volunteers')->assertStatus(200);
        $this->withToken($pioToken)->getJson('/api/v1/volunteer/organizations')->assertStatus(200);
        $this->withToken($pioToken)->getJson('/api/v1/volunteer/deployments')->assertStatus(200);
        $this->withToken($pioToken)->getJson('/api/v1/volunteer/attendances')->assertStatus(200);
        $this->withToken($pioToken)->getJson('/api/v1/volunteer/training-programs')->assertStatus(200);
        $this->withToken($pioToken)->getJson('/api/v1/volunteer/training-sessions')->assertStatus(200);

        $this->withToken($pioToken)->getJson('/api/v1/volunteer/dashboard')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'kpis' => ['total_registered_volunteers', 'verified_volunteers', 'available_volunteers', 'active_deployments'],
                    'by_area',
                    'by_category',
                    'skills_distribution',
                ],
            ]);

        $this->withToken($pioToken)->getJson('/api/v1/volunteer/gis/geojson')
            ->assertStatus(200)
            ->assertJsonPath('data.type', 'FeatureCollection');

        $this->withToken($pioToken)->getJson('/api/v1/volunteer/reports')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['volunteer_register', 'deployment_history', 'attendance_history', 'training_certifications'],
            ]);

        $this->withToken($pioToken)->get('/api/v1/volunteer/export?report_type=training')
            ->assertStatus(200);
    }
}
