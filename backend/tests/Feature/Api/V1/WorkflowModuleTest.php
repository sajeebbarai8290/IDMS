<?php

namespace Tests\Feature\Api\V1;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\AuditLog;
use App\Modules\Beneficiary\Models\Household;
use App\Modules\DamageAssessment\Models\DamageAssessment;
use App\Modules\Project\Models\Project;
use App\Modules\Relief\Models\ReliefRequisition;
use App\Modules\Workflow\Models\WorkflowDefinition;
use App\Modules\Workflow\Models\WorkflowInstance;
use Database\Seeders\AdministrativeUnitSeeder;
use Database\Seeders\BeneficiarySeeder;
use Database\Seeders\DamageAssessmentSeeder;
use Database\Seeders\DisasterEventSeeder;
use Database\Seeders\ProjectSeeder;
use Database\Seeders\ReliefCatalogAndWarehouseSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkflowModuleTest extends TestCase
{
    use RefreshDatabase;

    protected string $privateKey;
    protected string $cleanX5cCert;
    protected string $kid = 'idms-workflow-test-key-2026';
    protected string $realm = 'idms-gov-realm';
    protected string $issuer = 'http://localhost:8080/realms/idms-gov-realm';
    protected string $audience = 'idms-backend-api';

    protected AdministrativeUnit $nationalUnit;
    protected AdministrativeUnit $khulnaDistrict;
    protected AdministrativeUnit $barishalDistrict;
    protected AdministrativeUnit $dacopeUpazila;
    protected AdministrativeUnit $chalnaUnion;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->seed(AdministrativeUnitSeeder::class);
        $this->seed(DisasterEventSeeder::class);
        $this->seed(BeneficiarySeeder::class);
        $this->seed(ReliefCatalogAndWarehouseSeeder::class);
        $this->seed(ProjectSeeder::class);
        $this->seed(DamageAssessmentSeeder::class);
        $this->seed(WorkflowSeeder::class);

        $this->nationalUnit = AdministrativeUnit::where('tier_level', 'NATIONAL')->firstOrFail();
        $this->khulnaDistrict = AdministrativeUnit::where('code', '47')->firstOrFail();
        $this->barishalDistrict = AdministrativeUnit::where('code', '10')->firstOrFail();
        $this->dacopeUpazila = AdministrativeUnit::where('code', '12')->firstOrFail();
        $this->chalnaUnion = AdministrativeUnit::where('code', '35')->firstOrFail();

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

    protected function createToken(array $overrides = []): string
    {
        $header = ['alg' => 'RS256', 'typ' => 'JWT', 'kid' => $this->kid];
        $now = time();

        $payload = array_merge([
            'sub' => 'a1111111-1111-4111-8111-111111111111',
            'preferred_username' => 'up_chairman_chalna',
            'name' => 'Md. Habibur Rahman',
            'email' => 'chairman.chalna@modmr.gov.bd',
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'iat' => $now,
            'nbf' => $now - 5,
            'exp' => $now + 3600,
            'roles' => ['ROLE_UP_CHAIRMAN'],
            'tier_level' => 'UNION',
            'scope_id' => $this->chalnaUnion->id,
            'ancestry_path' => $this->chalnaUnion->ancestry_path,
            'administrative_profile' => [
                'tier_level' => 'UNION',
                'scope_id' => $this->chalnaUnion->id,
                'ancestry_path' => $this->chalnaUnion->ancestry_path,
                'name_en' => $this->chalnaUnion->name_en,
            ],
        ], $overrides);

        $h = $this->base64UrlEncode(json_encode($header));
        $p = $this->base64UrlEncode(json_encode($payload));
        $sig = '';
        openssl_sign("{$h}.{$p}", $sig, $this->privateKey, OPENSSL_ALGO_SHA256);

        return "{$h}.{$p}." . $this->base64UrlEncode($sig);
    }

    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    protected function tokenForRole(string $sub, string $role, AdministrativeUnit $unit): string
    {
        return $this->createToken([
            'sub' => $sub,
            'preferred_username' => strtolower($role) . '_' . $unit->code,
            'name' => "{$role} ({$unit->name_en})",
            'email' => strtolower($role) . '.' . $unit->code . '@modmr.gov.bd',
            'roles' => [$role],
            'tier_level' => $unit->tier_level,
            'scope_id' => $unit->id,
            'ancestry_path' => $unit->ancestry_path,
            'administrative_profile' => [
                'tier_level' => $unit->tier_level,
                'scope_id' => $unit->id,
                'ancestry_path' => $unit->ancestry_path,
                'name_en' => $unit->name_en,
            ],
        ]);
    }

    public function test_unauthenticated_and_external_partner_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/workflow/dashboard')->assertStatus(401);
        $this->getJson('/api/v1/workflow/instances')->assertStatus(401);

        $extToken = $this->tokenForRole('e1111111-1111-4111-8111-111111111111', 'ROLE_EXTERNAL_PARTNER', $this->nationalUnit);
        $this->withToken($extToken)->getJson('/api/v1/workflow/instances')->assertStatus(403);
    }

    public function test_super_admin_configuration_management_and_operational_blackout(): void
    {
        $superToken = $this->tokenForRole('s1111111-1111-4111-8111-111111111111', 'ROLE_SUPER_ADMIN', $this->nationalUnit);
        $pioToken = $this->tokenForRole('p1111111-1111-4111-8111-111111111111', 'ROLE_PIO', $this->dacopeUpazila);

        // Non-super-admin cannot create workflow configuration
        $payload = [
            'code' => 'WF_CUSTOM_TEST',
            'name_en' => 'Custom Test Workflow',
            'name_bn' => 'কাস্টম টেস্ট কর্মপ্রবাহ',
            'module_code' => 'GENERAL',
            'entity_type' => 'GENERAL_REQUEST',
            'sla_hours' => 36,
            'stages' => [
                [
                    'stage_order' => 1,
                    'stage_code' => 'INITIAL_REVIEW',
                    'name_en' => 'PIO Review',
                    'name_bn' => 'পিআইও পর্যালোচনা',
                    'approval_level' => 'INITIAL_REVIEW',
                    'execution_mode' => 'SEQUENTIAL',
                    'sla_hours' => 12,
                    'assignments' => [
                        ['assignee_type' => 'ROLE', 'role_code' => 'ROLE_PIO', 'authority_type' => 'REVIEWER'],
                    ],
                ],
                [
                    'stage_order' => 2,
                    'stage_code' => 'FINAL_APPROVAL',
                    'name_en' => 'DRRO Final Approval',
                    'name_bn' => 'ডিআরআরও চূড়ান্ত অনুমোদন',
                    'approval_level' => 'FINAL_APPROVAL',
                    'execution_mode' => 'SEQUENTIAL',
                    'sla_hours' => 24,
                    'is_terminal' => true,
                    'assignments' => [
                        ['assignee_type' => 'ROLE', 'role_code' => 'ROLE_DRRO', 'authority_type' => 'APPROVER'],
                    ],
                ],
            ],
        ];

        $this->withToken($pioToken)->postJson('/api/v1/workflow/definitions', $payload)->assertStatus(403);

        // Super admin creates configuration and it is audited
        $createRes = $this->withToken($superToken)->postJson('/api/v1/workflow/definitions', $payload)->assertStatus(201);
        $defId = $createRes->json('data.id');

        $this->assertTrue(AuditLog::where('action_name', 'WORKFLOW_DEFINITION_CREATED')->where('auditable_id', $defId)->exists());

        // Toggle inactive -> active
        $this->withToken($superToken)
            ->postJson("/api/v1/workflow/definitions/{$defId}/toggle-status", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        // Super admin is blocked from operational submission & approvals
        $this->withToken($superToken)
            ->postJson('/api/v1/workflow/instances', [
                'entity_type' => 'GENERAL_REQUEST',
                'administrative_unit_id' => $this->dacopeUpazila->id,
                'title_en' => 'Blocked Super Admin Submission',
            ])
            ->assertStatus(403);

        // Delete custom unused definition works
        $this->withToken($superToken)->deleteJson("/api/v1/workflow/definitions/{$defId}")->assertOk();
    }

    public function test_multi_tier_sequential_and_parallel_workflow_with_sod_and_scoping(): void
    {
        $chairToken = $this->tokenForRole('c1111111-1111-4111-8111-111111111111', 'ROLE_UP_CHAIRMAN', $this->chalnaUnion);
        $pioToken = $this->tokenForRole('p1111111-1111-4111-8111-111111111111', 'ROLE_PIO', $this->dacopeUpazila);
        $unoToken = $this->tokenForRole('u1111111-1111-4111-8111-111111111111', 'ROLE_UNO', $this->dacopeUpazila);
        $drroToken = $this->tokenForRole('d1111111-1111-4111-8111-111111111111', 'ROLE_DRRO', $this->khulnaDistrict);
        $dcToken = $this->tokenForRole('d2222222-2222-4222-8222-222222222222', 'ROLE_DEPUTY_COMMISSIONER', $this->khulnaDistrict);
        $barishalDrroToken = $this->tokenForRole('b1111111-1111-4111-8111-111111111111', 'ROLE_DRRO', $this->barishalDistrict);
        $ddmToken = $this->tokenForRole('m1111111-1111-4111-8111-111111111111', 'ROLE_DDM_RELIEF_DIRECTOR', $this->nationalUnit);
        $ministryToken = $this->tokenForRole('m2222222-2222-4222-8222-222222222222', 'ROLE_MINISTRY_EXECUTIVE', $this->nationalUnit);

        // Sync chairman user so created_by FK resolves cleanly, then create a ReliefRequisition
        $this->withToken($chairToken)->getJson('/api/v1/auth/user')->assertOk();
        $chairUser = \App\Core\Models\User::where('keycloak_sub', 'c1111111-1111-4111-8111-111111111111')->firstOrFail();
        $disaster = \App\Modules\Disaster\Models\DisasterEvent::firstOrFail();

        $req = ReliefRequisition::create([
            'disaster_event_id' => $disaster->id,
            'administrative_unit_id' => $this->chalnaUnion->id,
            'requisition_no' => 'REQ-WF-TEST-001',
            'requesting_organization' => 'Chalna Union Parishad',
            'estimated_beneficiaries' => 250,
            'priority' => 'HIGH',
            'request_date' => now()->toDateString(),
            'status' => ReliefRequisition::STATUS_SUBMITTED,
            'created_by' => $chairUser->id,
            'submitted_by' => $chairUser->id,
            'submitted_at' => now(),
        ]);

        // UP Chairman submits a Relief Requisition into the configurable 5-stage workflow
        $startRes = $this->withToken($chairToken)
            ->postJson('/api/v1/workflow/instances', [
                'entity_type' => 'RELIEF_REQUISITION',
                'entity_id' => $req->id,
                'priority' => 'HIGH',
                'comments' => 'Urgent cyclone relief approval needed.',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.current_state', 'INITIAL_REVIEW')
            ->assertJsonPath('data.module_code', 'RELIEF');

        $instanceId = $startRes->json('data.id');

        // Duplicate active workflow on same entity & definition blocked
        $this->withToken($chairToken)
            ->postJson('/api/v1/workflow/instances', [
                'entity_type' => 'RELIEF_REQUISITION',
                'entity_id' => $req->id,
            ])
            ->assertStatus(422);

        // Lateral isolation: Barishal DRRO cannot view or act on Khulna/Dacope workflow
        $this->withToken($barishalDrroToken)->getJson("/api/v1/workflow/instances/{$instanceId}")->assertStatus(403);
        $this->withToken($barishalDrroToken)->postJson("/api/v1/workflow/instances/{$instanceId}/approve")->assertStatus(403);

        // Self-approval blocked even if submitter tries
        $this->withToken($chairToken)->postJson("/api/v1/workflow/instances/{$instanceId}/approve")->assertStatus(422);

        // Upload attachment & download it
        $file = UploadedFile::fake()->create('requisition_brief.pdf', 120, 'application/pdf');
        $attRes = $this->withToken($chairToken)
            ->post("/api/v1/workflow/instances/{$instanceId}/attachments", ['file' => $file, 'title' => 'Union Memo'])
            ->assertStatus(201);
        $attId = $attRes->json('data.id');
        $this->withToken($pioToken)->get("/api/v1/workflow/instances/{$instanceId}/attachments/{$attId}")->assertOk();

        // Stage 1: PIO sees it in my_pending queue and approves -> advances to SUPERVISOR_APPROVAL (UNO)
        $this->withToken($pioToken)
            ->getJson('/api/v1/workflow/instances?queue=my_pending')
            ->assertOk()
            ->assertJsonFragment(['id' => $instanceId]);

        $this->withToken($pioToken)
            ->postJson("/api/v1/workflow/instances/{$instanceId}/approve", ['comments' => 'Verified union requisition quantities.'])
            ->assertOk()
            ->assertJsonPath('data.current_state', 'SUPERVISOR_APPROVAL');

        // Stage 2: UNO approves -> advances to PARALLEL_ALL DISTRICT_APPROVAL (DRRO + DC both required!)
        $this->withToken($unoToken)
            ->postJson("/api/v1/workflow/instances/{$instanceId}/approve", ['comments' => 'UNO endorsed.'])
            ->assertOk()
            ->assertJsonPath('data.current_state', 'DISTRICT_APPROVAL');

        // Stage 3a: DRRO approves -> stays in DISTRICT_APPROVAL awaiting DC parallel sign-off
        $this->withToken($drroToken)
            ->postJson("/api/v1/workflow/instances/{$instanceId}/approve", ['comments' => 'DRRO endorsed.'])
            ->assertOk()
            ->assertJsonPath('data.current_state', 'DISTRICT_APPROVAL');

        // Stage 3b: DC approves -> completes parallel stage & advances to DIRECTORATE_APPROVAL
        $this->withToken($dcToken)
            ->postJson("/api/v1/workflow/instances/{$instanceId}/approve", ['comments' => 'DC sanctioned.'])
            ->assertOk()
            ->assertJsonPath('data.current_state', 'DIRECTORATE_APPROVAL');

        // Stage 4: DDM Relief Director approves -> advances to MINISTRY_APPROVAL
        $this->withToken($ddmToken)
            ->postJson("/api/v1/workflow/instances/{$instanceId}/approve", ['comments' => 'DDM vetted.'])
            ->assertOk()
            ->assertJsonPath('data.current_state', 'MINISTRY_APPROVAL');

        // Stage 5: Ministry Executive grants Final Approval -> FINAL_APPROVED
        $finalRes = $this->withToken($ministryToken)
            ->postJson("/api/v1/workflow/instances/{$instanceId}/approve", ['comments' => 'Ministry final approval granted.'])
            ->assertOk()
            ->assertJsonPath('data.current_state', 'FINAL_APPROVED');

        // Transition history preserves every decision (Submit + 6 approvals = 7 transitions)
        $this->assertCount(7, $finalRes->json('data.transitions'));

        // Entity history lookup works
        $this->withToken($ministryToken)
            ->getJson("/api/v1/workflow/entities/RELIEF_REQUISITION/{$req->id}/history")
            ->assertOk()
            ->assertJsonFragment(['id' => $instanceId]);
    }

    public function test_correction_request_info_forward_reject_and_delegation_rules(): void
    {
        $enumToken = $this->tokenForRole('f1111111-1111-4111-8111-111111111111', 'ROLE_FIELD_ENUMERATOR', $this->chalnaUnion);
        $pioToken = $this->tokenForRole('p2222222-2222-4222-8222-222222222222', 'ROLE_PIO', $this->dacopeUpazila);
        $unoToken = $this->tokenForRole('u2222222-2222-4222-8222-222222222222', 'ROLE_UNO', $this->dacopeUpazila);
        $delegateToken = $this->tokenForRole('u3333333-3333-4333-8333-333333333333', 'ROLE_TAG_OFFICER', $this->dacopeUpazila);
        $barishalOfficerToken = $this->tokenForRole('b2222222-2222-4222-8222-222222222222', 'ROLE_DRRO', $this->barishalDistrict);
        $superToken = $this->tokenForRole('s2222222-2222-4222-8222-222222222222', 'ROLE_SUPER_ADMIN', $this->nationalUnit);

        // Sync users into DB first so delegation can reference delegate_user_id
        $this->withToken($delegateToken)->getJson('/api/v1/auth/user')->assertOk();
        $this->withToken($barishalOfficerToken)->getJson('/api/v1/auth/user')->assertOk();
        $this->withToken($superToken)->getJson('/api/v1/auth/user')->assertOk();

        $assessment = DamageAssessment::firstOrFail();

        $startRes = $this->withToken($enumToken)
            ->postJson('/api/v1/workflow/instances', [
                'entity_type' => 'DAMAGE_ASSESSMENT',
                'entity_id' => $assessment->id,
                'comments' => 'D-Form submitted for verification.',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.current_state', 'INITIAL_REVIEW');

        $instanceId = $startRes->json('data.id');

        // Request additional info requires comments
        $this->withToken($pioToken)->postJson("/api/v1/workflow/instances/{$instanceId}/request-info", [])->assertStatus(422);

        $this->withToken($pioToken)
            ->postJson("/api/v1/workflow/instances/{$instanceId}/request-info", ['comments' => 'Attach ward survey sheet.'])
            ->assertOk()
            ->assertJsonPath('data.current_state', 'INFO_REQUESTED');

        // Submitter provides info -> restores stage
        $this->withToken($enumToken)
            ->postJson("/api/v1/workflow/instances/{$instanceId}/provide-info", ['comments' => 'Ward sheet attached.'])
            ->assertOk()
            ->assertJsonPath('data.current_state', 'INITIAL_REVIEW');

        // Return for correction -> Resubmit
        $this->withToken($pioToken)
            ->postJson("/api/v1/workflow/instances/{$instanceId}/return-correction", ['comments' => 'Recalculate crop loss BDT.'])
            ->assertOk()
            ->assertJsonPath('data.current_state', 'RETURNED_FOR_CORRECTION');

        $this->withToken($enumToken)
            ->postJson("/api/v1/workflow/instances/{$instanceId}/resubmit", ['comments' => 'Crop loss updated and resubmitted.'])
            ->assertOk()
            ->assertJsonPath('data.current_state', 'INITIAL_REVIEW');

        // Forward to UNO supervisor stage
        $def = WorkflowDefinition::where('code', 'WF_DAMAGE_DFORM')->with('stages')->firstOrFail();
        $unoStage = $def->stages->firstWhere('stage_code', 'SUPERVISOR_APPROVAL');

        $this->withToken($pioToken)
            ->postJson("/api/v1/workflow/instances/{$instanceId}/forward", [
                'target_stage_id' => $unoStage->id,
                'comments' => 'Forwarding directly to UNO for urgent review.',
            ])
            ->assertOk()
            ->assertJsonPath('data.current_state', 'SUPERVISOR_APPROVAL');

        // Authorized Delegation: UNO delegates to dacope Tag Officer; invalid delegations blocked
        $this->withToken($unoToken)
            ->postJson('/api/v1/workflow/delegations', [
                'delegate_user_id' => 'u2222222-2222-4222-8222-222222222222', // self
                'valid_from' => now()->subHour()->toIso8601String(),
                'valid_until' => now()->addDays(2)->toIso8601String(),
                'reason' => 'Invalid self delegation',
            ])
            ->assertStatus(422);

        $barishalUserId = \App\Core\Models\User::where('keycloak_sub', 'b2222222-2222-4222-8222-222222222222')->value('id');
        $this->withToken($unoToken)
            ->postJson('/api/v1/workflow/delegations', [
                'delegate_user_id' => $barishalUserId,
                'administrative_unit_id' => $this->dacopeUpazila->id,
                'valid_from' => now()->subHour()->toIso8601String(),
                'valid_until' => now()->addDays(2)->toIso8601String(),
                'reason' => 'Cross-district lateral delegation must fail',
            ])
            ->assertStatus(422);

        $delegateUserId = \App\Core\Models\User::where('keycloak_sub', 'u3333333-3333-4333-8333-333333333333')->value('id');
        $delRes = $this->withToken($unoToken)
            ->postJson('/api/v1/workflow/delegations', [
                'delegate_user_id' => $delegateUserId,
                'administrative_unit_id' => $this->dacopeUpazila->id,
                'valid_from' => now()->subHour()->toIso8601String(),
                'valid_until' => now()->addDays(2)->toIso8601String(),
                'reason' => 'Official leave delegation to Tag Officer',
            ])
            ->assertStatus(201);

        $delegationId = $delRes->json('data.id');

        // Delegate acts on behalf of UNO to reject (and verify mandatory comment + acted_on_behalf_of recorded)
        $this->withToken($delegateToken)
            ->postJson("/api/v1/workflow/instances/{$instanceId}/reject", ['comments' => 'Rejected due to duplicate D-Form filing.'])
            ->assertOk()
            ->assertJsonPath('data.current_state', 'REJECTED');

        $detail = $this->withToken($unoToken)->getJson("/api/v1/workflow/instances/{$instanceId}")->assertOk();
        $lastTransition = collect($detail->json('data.transitions'))->last();
        $this->assertEquals('REJECT', $lastTransition['action']);
        $this->assertNotNull($lastTransition['acted_on_behalf_of_user_id']);

        // Revoke delegation
        $this->withToken($unoToken)->postJson("/api/v1/workflow/delegations/{$delegationId}/revoke")->assertOk();
    }

    public function test_overdue_escalation_notifications_dashboard_and_multi_module_integration(): void
    {
        $pioToken = $this->tokenForRole('p3333333-3333-4333-8333-333333333333', 'ROLE_PIO', $this->dacopeUpazila);
        $unoToken = $this->tokenForRole('u4444444-4444-4444-8444-444444444444', 'ROLE_UNO', $this->dacopeUpazila);
        $drroToken = $this->tokenForRole('d3333333-3333-4333-8333-333333333333', 'ROLE_DRRO', $this->khulnaDistrict);
        $ministryToken = $this->tokenForRole('m3333333-3333-4333-8333-333333333333', 'ROLE_MINISTRY_EXECUTIVE', $this->nationalUnit);

        // Verify target entity lookup across Beneficiary & Project modules
        $this->withToken($pioToken)->getJson('/api/v1/workflow/target-entities?entity_type=HOUSEHOLD')->assertOk();
        $this->withToken($pioToken)->getJson('/api/v1/workflow/target-entities?entity_type=PROJECT')->assertOk();

        $household = Household::firstOrFail();
        $project = Project::firstOrFail();

        // Start a Project workflow
        $projWf = $this->withToken($pioToken)
            ->postJson('/api/v1/workflow/instances', [
                'entity_type' => 'PROJECT',
                'entity_id' => $project->id,
                'priority' => 'URGENT',
            ])
            ->assertStatus(201);
        $projInstanceId = $projWf->json('data.id');

        // Start a Beneficiary workflow
        $this->withToken($pioToken)
            ->postJson('/api/v1/workflow/instances', [
                'entity_type' => 'HOUSEHOLD',
                'entity_id' => $household->id,
            ])
            ->assertStatus(201);

        // Make project workflow overdue & run escalation sweep
        WorkflowInstance::whereKey($projInstanceId)->update([
            'due_at' => now()->subHours(10),
        ]);

        $escRes = $this->withToken($ministryToken)
            ->postJson('/api/v1/workflow/escalations/run')
            ->assertOk();

        $this->assertGreaterThanOrEqual(1, $escRes->json('data.escalated'));

        // Notifications should be visible for UNO/DRRO & markable as read
        $notifRes = $this->withToken($unoToken)->getJson('/api/v1/workflow/notifications')->assertOk();
        $this->assertGreaterThanOrEqual(1, count($notifRes->json('data')));

        $notifId = $notifRes->json('data.0.id');
        $this->withToken($unoToken)->postJson("/api/v1/workflow/notifications/{$notifId}/read")->assertOk();
        $this->withToken($unoToken)->postJson('/api/v1/workflow/notifications/read-all')->assertOk();

        // Dashboard shows overdue, submitted, and module stats
        $dash = $this->withToken($drroToken)->getJson('/api/v1/workflow/dashboard')->assertOk();
        $this->assertGreaterThanOrEqual(1, $dash->json('data.kpis.overdue_workflows'));
        $this->assertGreaterThanOrEqual(2, $dash->json('data.kpis.total_workflows'));
    }

    public function test_security_hardening_guards_against_tampering_and_invalid_inputs(): void
    {
        $pioToken = $this->tokenForRole('p4444444-4444-4444-8444-444444444444', 'ROLE_PIO', $this->dacopeUpazila);
        $unoToken = $this->tokenForRole('u5555555-5555-4555-8555-555555555555', 'ROLE_UNO', $this->dacopeUpazila);
        $barishalUnoToken = $this->tokenForRole('b3333333-3333-4333-8333-333333333333', 'ROLE_UNO', $this->barishalDistrict);
        $auditorToken = $this->tokenForRole('n1111111-1111-4111-8111-111111111111', 'ROLE_NATIONAL_AUDITOR', $this->nationalUnit);
        $superToken = $this->tokenForRole('s3333333-3333-4333-8333-333333333333', 'ROLE_SUPER_ADMIN', $this->nationalUnit);

        $this->withToken($superToken)->getJson('/api/v1/auth/user')->assertOk();
        $superUserId = \App\Core\Models\User::where('keycloak_sub', 's3333333-3333-4333-8333-333333333333')->value('id');

        // Conditional assignment validation rejects ROLE missing role_code
        $this->withToken($superToken)->postJson('/api/v1/workflow/definitions', [
            'code' => 'WF_INVALID_ASSIGN',
            'name_en' => 'Invalid Assignment Workflow',
            'name_bn' => 'অবৈধ অ্যাসাইনমেন্ট',
            'module_code' => 'GENERAL',
            'entity_type' => 'GENERAL_REQUEST',
            'stages' => [
                [
                    'stage_order' => 1,
                    'stage_code' => 'INITIAL_REVIEW',
                    'name_en' => 'Review',
                    'approval_level' => 'INITIAL_REVIEW',
                    'assignments' => [
                        ['assignee_type' => 'ROLE'],
                    ],
                ],
            ],
        ])->assertStatus(422);

        $project = Project::firstOrFail();
        $startRes = $this->withToken($pioToken)
            ->postJson('/api/v1/workflow/instances', [
                'entity_type' => 'PROJECT',
                'entity_id' => $project->id,
            ])
            ->assertStatus(201);
        $instanceId = $startRes->json('data.id');

        // National Auditor & Super Admin blocked from uploading operational attachments
        $file = UploadedFile::fake()->create('memo.pdf', 50, 'application/pdf');
        $this->withToken($auditorToken)->post("/api/v1/workflow/instances/{$instanceId}/attachments", ['file' => $file])->assertStatus(403);
        $this->withToken($superToken)->post("/api/v1/workflow/instances/{$instanceId}/attachments", ['file' => $file])->assertStatus(403);

        // Cross-jurisdiction UNO cannot mark Dacope UNO's notification as read
        $unoNotifs = $this->withToken($unoToken)->getJson('/api/v1/workflow/notifications')->assertOk();
        $notifId = $unoNotifs->json('data.0.id');
        $this->withToken($barishalUnoToken)->postJson("/api/v1/workflow/notifications/{$notifId}/read")->assertStatus(403);

        // Forwarding to Super Admin is rejected
        $this->withToken($unoToken)
            ->postJson("/api/v1/workflow/instances/{$instanceId}/forward", [
                'forward_to_user_id' => $superUserId,
                'comments' => 'Attempt invalid forward',
            ])
            ->assertStatus(422);

        // Malformed UUID in entity history route returns 404 (not 500)
        $this->withToken($pioToken)->getJson('/api/v1/workflow/entities/PROJECT/not-a-valid-uuid/history')->assertStatus(404);
    }
}
