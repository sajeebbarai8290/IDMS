<?php

namespace Tests\Feature\Api\V1;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\AuditLog;
use App\Core\Models\User;
use App\Modules\Notification\Contracts\SmsGatewayInterface;
use App\Modules\Notification\Models\Alert;
use App\Modules\Notification\Models\AlertRule;
use App\Modules\Notification\Models\Notification;
use App\Modules\Notification\Models\NotificationDevice;
use App\Modules\Notification\Models\NotificationDispatch;
use App\Modules\Notification\Models\NotificationTemplate;
use App\Modules\UserManagement\Models\Organization;
use Database\Seeders\AdministrativeUnitSeeder;
use Database\Seeders\NotificationSeeder;
use Database\Seeders\UserManagementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class NotificationModuleTest extends TestCase
{
    use RefreshDatabase;

    protected string $privateKey;
    protected string $cleanX5cCert;
    protected string $kid = 'idms-notif-test-key-2026';
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

        $this->seed(AdministrativeUnitSeeder::class);
        $this->seed(UserManagementSeeder::class);
        $this->seed(NotificationSeeder::class);

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
            'sub' => (string) Str::uuid(),
            'preferred_username' => 'neoc_director',
            'name' => 'NEOC Director',
            'email' => 'neoc.dir@modmr.gov.bd',
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'exp' => $now + 3600,
            'nbf' => $now - 60,
            'iat' => $now,
            'roles' => ['ROLE_NEOC_DIRECTOR'],
            'tier_level' => 'NATIONAL',
            'ancestry_path' => '/',
            'scope_id' => $this->nationalUnit->id,
            'designation' => 'Director, NEOC',
        ], $payloadOverrides);

        $h = rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '=');
        $p = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        openssl_sign("{$h}.{$p}", $sig, $this->privateKey, OPENSSL_ALGO_SHA256);
        $s = rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');

        return "{$h}.{$p}.{$s}";
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/notifications/dashboard')->assertStatus(401);
        $this->getJson('/api/v1/notifications/inbox')->assertStatus(401);
        $this->postJson('/api/v1/notifications', [])->assertStatus(401);
        $this->getJson('/api/v1/notifications/alerts')->assertStatus(401);
    }

    public function test_rbac_and_super_admin_operational_blackout_are_enforced(): void
    {
        $superAdminToken = $this->createToken([
            'sub' => '22222222-2222-4222-8222-222222222221',
            'preferred_username' => 'super_admin_notif',
            'email' => 'super.admin.notif@modmr.gov.bd',
            'roles' => ['ROLE_SUPER_ADMIN'],
        ]);

        // Super Admin can manage templates and view dashboard/logs
        $this->withToken($superAdminToken)
            ->getJson('/api/v1/notifications/dashboard')
            ->assertOk();

        $tplRes = $this->withToken($superAdminToken)->postJson('/api/v1/notifications/templates', [
            'template_code' => 'TEST_CUSTOM_TPL',
            'category' => 'SYSTEM_ANNOUNCEMENT',
            'channel' => 'MULTI',
            'default_severity' => 'INFO',
            'title_bn' => 'নতুন বিজ্ঞপ্তি',
            'title_en' => 'New Notice',
            'body_bn' => 'পরীক্ষামূলক বাংলা বার্তা।',
            'body_en' => 'Test English message.',
        ]);
        $tplRes->assertStatus(201);

        // Super Admin is blocked from operational emergency broadcasts and alert acknowledgement
        $this->withToken($superAdminToken)->postJson('/api/v1/notifications', [
            'category' => 'EMERGENCY_ALERT',
            'severity' => 'CRITICAL',
            'channels' => ['IN_APP'],
            'title_bn' => 'জরুরি',
            'title_en' => 'Emergency',
            'message_bn' => 'জরুরি বার্তা',
            'message_en' => 'Emergency message',
            'target_type' => 'ALL',
        ])->assertStatus(403);

        $alert = Alert::firstOrFail();
        $this->withToken($superAdminToken)
            ->postJson("/api/v1/notifications/alerts/{$alert->id}/acknowledge", ['notes' => 'Attempt'])
            ->assertStatus(403);

        // National Auditor cannot send notifications
        $auditorToken = $this->createToken([
            'sub' => '22222222-2222-4222-8222-222222222222',
            'preferred_username' => 'auditor_notif',
            'email' => 'auditor.notif@modmr.gov.bd',
            'roles' => ['ROLE_NATIONAL_AUDITOR'],
        ]);

        $this->withToken($auditorToken)->getJson('/api/v1/notifications/dispatches')->assertOk();
        $this->withToken($auditorToken)->postJson('/api/v1/notifications', [
            'category' => 'SYSTEM_ANNOUNCEMENT',
            'severity' => 'INFO',
            'channels' => ['IN_APP'],
            'title_bn' => 'ক',
            'title_en' => 'A',
            'message_bn' => 'খ',
            'message_en' => 'B',
            'target_type' => 'ALL',
        ])->assertStatus(403);
    }

    public function test_multichannel_notification_dispatch_masking_dedup_and_flutter_device_push(): void
    {
        $recipient = User::create([
            'id' => (string) Str::uuid(),
            'keycloak_sub' => '33333333-3333-4333-8333-333333333331',
            'email' => 'pio.dacope.notif@modmr.gov.bd',
            'mobile_number' => '+8801711889900',
            'full_name_en' => 'Dacope PIO',
            'full_name_bn' => 'দাকোপ পিআইও',
            'role_code' => 'ROLE_PIO',
            'administrative_unit_id' => $this->dacopeUpazila->id,
            'is_active' => true,
            'account_status' => User::STATUS_ACTIVE,
        ]);

        $recipientToken = $this->createToken([
            'sub' => $recipient->keycloak_sub,
            'preferred_username' => 'pio_dacope_notif',
            'email' => $recipient->email,
            'roles' => ['ROLE_PIO'],
            'tier_level' => 'UPAZILA',
            'ancestry_path' => $this->dacopeUpazila->ancestry_path,
            'scope_id' => $this->dacopeUpazila->id,
        ]);

        // Register Flutter device securely
        $devRes = $this->withToken($recipientToken)->postJson('/api/v1/notifications/devices', [
            'device_uuid' => 'flutter-dacope-01',
            'platform' => 'ANDROID',
            'push_provider' => 'FCM',
            'push_token' => 'fcm_secret_token_for_dacope_field_app_12345',
            'device_name' => 'Dacope Field Tablet',
            'app_version' => '1.2.0',
            'locale' => 'bn',
        ]);
        $devRes->assertStatus(201)
            ->assertJsonMissing(['push_token_encrypted', 'push_token_hash'])
            ->assertJsonPath('data.platform', 'ANDROID');

        $neocToken = $this->createToken([
            'sub' => '33333333-3333-4333-8333-333333333332',
            'email' => 'neoc.dispatch@modmr.gov.bd',
        ]);

        $template = NotificationTemplate::where('template_code', 'DISASTER_EARLY_WARNING')->firstOrFail();
        $idempotencyKey = 'cyclone-warn-dacope-001';

        $sendPayload = [
            'template_id' => $template->id,
            'template_variables' => [
                'area_bn' => 'দাকোপ',
                'area_en' => 'Dacope',
                'disaster_name' => 'Cyclone Shakti',
                'signal_level' => 'Signal 8',
            ],
            'category' => 'DISASTER_WARNING',
            'severity' => 'CRITICAL',
            'priority' => 'URGENT',
            'channels' => ['IN_APP', 'EMAIL', 'SMS', 'PUSH'],
            'target_type' => 'USER',
            'target_criteria' => ['user_ids' => [$recipient->id]],
            'administrative_unit_id' => $this->dacopeUpazila->id,
            'idempotency_key' => $idempotencyKey,
        ];

        $sendRes = $this->withToken($neocToken)->postJson('/api/v1/notifications', $sendPayload);
        $sendRes->assertStatus(201)
            ->assertJsonPath('data.status', 'DISPATCHED')
            ->assertJsonPath('data.total_recipients', 1)
            ->assertJsonPath('data.delivered_count', 4);

        $notificationId = $sendRes->json('data.id');

        // Duplicate idempotency_key must not create a second notification or dispatches
        $dupRes = $this->withToken($neocToken)->postJson('/api/v1/notifications', $sendPayload);
        $dupRes->assertStatus(201)->assertJsonPath('data.id', $notificationId);
        $this->assertSame(1, Notification::where('idempotency_key', $idempotencyKey)->count());
        $this->assertSame(4, NotificationDispatch::where('notification_id', $notificationId)->count());

        // Delivery logs must mask recipient email and mobile number
        $logsRes = $this->withToken($neocToken)
            ->getJson("/api/v1/notifications/dispatches?notification_id={$notificationId}");
        $logsRes->assertOk();
        $bodyText = $logsRes->getContent();
        $this->assertStringNotContainsString($recipient->email, $bodyText);
        $this->assertStringNotContainsString($recipient->mobile_number, $bodyText);
        $this->assertStringNotContainsString('fcm_secret_token_for_dacope_field_app_12345', $bodyText);

        // Recipient views In-App inbox and marks notification read
        $inboxRes = $this->withToken($recipientToken)->getJson('/api/v1/notifications/inbox?unread_only=true');
        $inboxRes->assertOk()->assertJsonPath('meta.unread_count', 1);
        $inAppDispatchId = $inboxRes->json('data.0.id');

        $this->withToken($recipientToken)
            ->postJson("/api/v1/notifications/inbox/{$inAppDispatchId}/read")
            ->assertOk()
            ->assertJsonPath('data.is_read', true);

        $this->assertTrue(AuditLog::where('action_name', 'NOTIFICATION_CREATED')->exists());
    }

    public function test_organizational_jurisdiction_isolation_is_enforced(): void
    {
        $khulnaDcToken = $this->createToken([
            'sub' => '44444444-4444-4444-8444-444444444441',
            'preferred_username' => 'dc_khulna',
            'email' => 'dc.khulna@modmr.gov.bd',
            'roles' => ['ROLE_DEPUTY_COMMISSIONER'],
            'tier_level' => 'DISTRICT',
            'ancestry_path' => $this->khulnaDistrict->ancestry_path,
            'scope_id' => $this->khulnaDistrict->id,
        ]);

        $barishalOfficer = User::create([
            'id' => (string) Str::uuid(),
            'keycloak_sub' => '44444444-4444-4444-8444-444444444442',
            'email' => 'pio.barishal@modmr.gov.bd',
            'full_name_en' => 'Barishal Officer',
            'role_code' => 'ROLE_PIO',
            'administrative_unit_id' => $this->barishalDistrict->id,
            'is_active' => true,
            'account_status' => User::STATUS_ACTIVE,
        ]);

        // Khulna DC cannot target Barishal geography
        $this->withToken($khulnaDcToken)->postJson('/api/v1/notifications', [
            'category' => 'DISASTER_WARNING',
            'severity' => 'HIGH',
            'channels' => ['IN_APP'],
            'title_bn' => 'সতর্কতা',
            'title_en' => 'Warning',
            'message_bn' => 'বার্তা',
            'message_en' => 'Message',
            'target_type' => 'GEOGRAPHY',
            'administrative_unit_id' => $this->barishalDistrict->id,
        ])->assertStatus(403);

        // Khulna DC cannot target a user in Barishal
        $this->withToken($khulnaDcToken)->postJson('/api/v1/notifications', [
            'category' => 'SYSTEM_ANNOUNCEMENT',
            'severity' => 'INFO',
            'channels' => ['IN_APP'],
            'title_bn' => 'সতর্কতা',
            'title_en' => 'Warning',
            'message_bn' => 'বার্তা',
            'message_en' => 'Message',
            'target_type' => 'USER',
            'target_criteria' => ['user_ids' => [$barishalOfficer->id]],
        ])->assertStatus(403);

        // Barishal alert cannot be viewed or acknowledged by Khulna DC
        $barishalAlert = Alert::create([
            'alert_no' => 'ALT-BARISHAL-9001',
            'category' => 'EMERGENCY_ALERT',
            'severity' => 'HIGH',
            'status' => Alert::STATUS_ACTIVE,
            'title_bn' => 'বরিশাল সতর্কতা',
            'title_en' => 'Barishal Alert',
            'message_bn' => 'বরিশালে সতর্কতা',
            'message_en' => 'Alert in Barishal',
            'administrative_unit_id' => $this->barishalDistrict->id,
            'triggered_at' => now(),
        ]);

        $this->withToken($khulnaDcToken)
            ->getJson("/api/v1/notifications/alerts/{$barishalAlert->id}")
            ->assertStatus(403);

        $this->withToken($khulnaDcToken)
            ->postJson("/api/v1/notifications/alerts/{$barishalAlert->id}/acknowledge", [])
            ->assertStatus(403);
    }

    public function test_user_preferences_policy_and_skipped_channel_behavior(): void
    {
        $unoUser = User::create([
            'id' => (string) Str::uuid(),
            'keycloak_sub' => '55555555-5555-4555-8555-555555555551',
            'email' => 'uno.dacope.pref@modmr.gov.bd',
            'mobile_number' => '+8801711223344',
            'full_name_en' => 'UNO Dacope',
            'role_code' => 'ROLE_UNO',
            'administrative_unit_id' => $this->dacopeUpazila->id,
            'is_active' => true,
            'account_status' => User::STATUS_ACTIVE,
        ]);

        $unoToken = $this->createToken([
            'sub' => $unoUser->keycloak_sub,
            'email' => $unoUser->email,
            'roles' => ['ROLE_UNO'],
            'tier_level' => 'UPAZILA',
            'ancestry_path' => $this->dacopeUpazila->ancestry_path,
            'scope_id' => $this->dacopeUpazila->id,
        ]);

        // Standard user cannot mute SMS/IN_APP for locked EMERGENCY_ALERT
        $this->withToken($unoToken)->putJson('/api/v1/notifications/preferences', [
            'preferences' => [
                [
                    'category' => 'EMERGENCY_ALERT',
                    'in_app_enabled' => true,
                    'sms_enabled' => false,
                ],
            ],
        ])->assertStatus(422);

        // User CAN disable EMAIL for SYSTEM_ANNOUNCEMENT
        $this->withToken($unoToken)->putJson('/api/v1/notifications/preferences', [
            'preferences' => [
                [
                    'category' => 'SYSTEM_ANNOUNCEMENT',
                    'in_app_enabled' => true,
                    'email_enabled' => false,
                    'sms_enabled' => false,
                    'push_enabled' => true,
                ],
            ],
        ])->assertOk();

        $sendRes = $this->withToken($unoToken)->postJson('/api/v1/notifications', [
            'category' => 'SYSTEM_ANNOUNCEMENT',
            'severity' => 'INFO',
            'channels' => ['IN_APP', 'EMAIL'],
            'title_bn' => 'সাধারণ ঘোষণা',
            'title_en' => 'General Notice',
            'message_bn' => 'মেইনটেন্যান্স নোটিশ',
            'message_en' => 'Maintenance notice',
            'target_type' => 'USER',
            'target_criteria' => ['user_ids' => [$unoUser->id]],
            'administrative_unit_id' => $this->dacopeUpazila->id,
        ]);
        $sendRes->assertStatus(201);

        $notifId = $sendRes->json('data.id');
        $emailDispatch = NotificationDispatch::where('notification_id', $notifId)
            ->where('channel', 'EMAIL')
            ->firstOrFail();
        $inAppDispatch = NotificationDispatch::where('notification_id', $notifId)
            ->where('channel', 'IN_APP')
            ->firstOrFail();

        $this->assertSame(NotificationDispatch::STATUS_SKIPPED, $emailDispatch->delivery_status);
        $this->assertSame(NotificationDispatch::STATUS_DELIVERED, $inAppDispatch->delivery_status);
    }

    public function test_alert_rules_threshold_acknowledgement_escalation_and_scheduled_notifications(): void
    {
        $neocToken = $this->createToken([
            'sub' => '66666666-6666-4666-8666-666666666661',
            'email' => 'neoc.alerts@modmr.gov.bd',
        ]);

        // Create alert rule
        $ruleRes = $this->withToken($neocToken)->postJson('/api/v1/notifications/alert-rules', [
            'rule_code' => 'RULE-RIVER-DANGER',
            'name_bn' => 'নদীর পানি বিপদসীমা অতিক্রম',
            'name_en' => 'River Water Danger Level Breach',
            'category' => 'DISASTER_WARNING',
            'event_type' => 'FLOOD_DANGER_LEVEL',
            'severity' => 'CRITICAL',
            'condition_config' => ['metric' => 'water_level_cm', 'operator' => '>=', 'threshold' => 500],
            'channels' => ['IN_APP', 'SMS'],
            'target_roles' => ['ROLE_NEOC_DIRECTOR'],
            'escalation_delay_minutes' => 10,
            'escalation_role_code' => 'ROLE_MINISTRY_EXECUTIVE',
            'cooldown_minutes' => 30,
            'administrative_unit_id' => $this->khulnaDistrict->id,
        ]);
        $ruleRes->assertStatus(201);

        // Below threshold does NOT trigger
        $noTrigger = $this->withToken($neocToken)->postJson('/api/v1/notifications/alerts/trigger', [
            'rule_code' => 'RULE-RIVER-DANGER',
            'message_bn' => 'পানির স্তর ৪২০ সেমি',
            'message_en' => 'Water level 420cm',
            'metric_value' => 420,
            'administrative_unit_id' => $this->khulnaDistrict->id,
        ]);
        $noTrigger->assertStatus(200)->assertJsonPath('data.triggered', false);

        // Above threshold triggers alert
        $triggerRes = $this->withToken($neocToken)->postJson('/api/v1/notifications/alerts/trigger', [
            'rule_code' => 'RULE-RIVER-DANGER',
            'message_bn' => 'পানির স্তর ৫৩০ সেমি অতিক্রম করেছে',
            'message_en' => 'Water level exceeded 530cm',
            'metric_value' => 530,
            'administrative_unit_id' => $this->khulnaDistrict->id,
        ]);
        $triggerRes->assertStatus(201)->assertJsonPath('data.triggered', true);
        $alertId = $triggerRes->json('data.alert.id');

        // Backdate triggered_at to verify SLA escalation sweep
        Alert::whereKey($alertId)->update(['triggered_at' => now()->subMinutes(20)]);

        $escRes = $this->withToken($neocToken)->postJson('/api/v1/notifications/alerts/escalate');
        $escRes->assertOk();
        $this->assertGreaterThanOrEqual(1, $escRes->json('data.escalated'));
        $this->assertSame(Alert::STATUS_ESCALATED, Alert::findOrFail($alertId)->status);

        // Acknowledge & resolve alert
        $this->withToken($neocToken)
            ->postJson("/api/v1/notifications/alerts/{$alertId}/acknowledge", ['notes' => 'Team dispatched'])
            ->assertOk()
            ->assertJsonPath('data.status', 'ACKNOWLEDGED');

        $this->withToken($neocToken)
            ->postJson("/api/v1/notifications/alerts/{$alertId}/resolve", ['notes' => 'Water receded'])
            ->assertOk()
            ->assertJsonPath('data.status', 'RESOLVED');

        // Scheduled notification test
        $schedRes = $this->withToken($neocToken)->postJson('/api/v1/notifications', [
            'category' => 'PROJECT_MONITORING',
            'severity' => 'WARNING',
            'channels' => ['IN_APP'],
            'title_bn' => 'নির্ধারিত স্মারক',
            'title_en' => 'Scheduled Reminder',
            'message_bn' => 'প্রকল্প প্রতিবেদন দাখিল করুন',
            'message_en' => 'Submit project inspection report',
            'target_type' => 'USER',
            'target_criteria' => ['user_ids' => [User::where('email', 'neoc.alerts@modmr.gov.bd')->firstOrFail()->id]],
            'scheduled_at' => now()->addHour()->toIso8601String(),
        ]);
        $schedRes->assertStatus(201)->assertJsonPath('data.status', 'SCHEDULED');
        $schedId = $schedRes->json('data.id');

        // Advance scheduled_at into the past and trigger scheduled processor
        Notification::whereKey($schedId)->update(['scheduled_at' => now()->subMinute()]);
        $procRes = $this->withToken($neocToken)->postJson('/api/v1/notifications/process-scheduled');
        $procRes->assertOk()->assertJsonPath('data.dispatched', 1);
        $this->assertSame(Notification::STATUS_DISPATCHED, Notification::findOrFail($schedId)->status);
    }

    public function test_failed_sms_dispatch_is_logged_and_manual_retry_succeeds(): void
    {
        $targetUser = User::create([
            'id' => (string) Str::uuid(),
            'keycloak_sub' => '77777777-7777-4777-8777-777777777771',
            'email' => 'retry.target@modmr.gov.bd',
            'mobile_number' => '+8801711998877',
            'full_name_en' => 'Retry Target Officer',
            'role_code' => 'ROLE_DRRO',
            'administrative_unit_id' => $this->khulnaDistrict->id,
            'is_active' => true,
            'account_status' => User::STATUS_ACTIVE,
        ]);

        $neocToken = $this->createToken([
            'sub' => '77777777-7777-4777-8777-777777777772',
            'email' => 'neoc.retry@modmr.gov.bd',
        ]);

        // Force SMS gateway failure for initial attempt
        $failingGateway = new class implements SmsGatewayInterface {
            public function sendSms(string $phoneNumber, string $message, array $context = []): array
            {
                throw new RuntimeException('Simulated Teletalk gateway timeout.');
            }
            public function healthCheck(): array
            {
                return ['driver' => 'mock_sms', 'configured' => true, 'status' => 'DEGRADED'];
            }
        };
        $this->app->instance(SmsGatewayInterface::class, $failingGateway);

        try {
            $this->withToken($neocToken)->postJson('/api/v1/notifications', [
                'category' => 'EMERGENCY_ALERT',
                'severity' => 'HIGH',
                'channels' => ['SMS'],
                'title_bn' => 'এসএমএস পরীক্ষা',
                'title_en' => 'SMS Retry Test',
                'message_bn' => 'পরীক্ষা বার্তা',
                'message_en' => 'Test retry message',
                'target_type' => 'USER',
                'target_criteria' => ['user_ids' => [$targetUser->id]],
                'administrative_unit_id' => $this->khulnaDistrict->id,
            ]);
        } catch (RuntimeException) {
            // Sync queue rethrows transient failure on attempt < maxAttempts
        }

        $dispatch = NotificationDispatch::where('recipient_user_id', $targetUser->id)
            ->where('channel', 'SMS')
            ->firstOrFail();

        // Mark as FAILED (simulating exhausted retries) and restore healthy gateway
        $dispatch->update(['delivery_status' => NotificationDispatch::STATUS_FAILED]);

        $healthyGateway = new \App\Modules\Notification\Adapters\MockSmsGatewayAdapter();
        $this->app->instance(SmsGatewayInterface::class, $healthyGateway);

        $retryRes = $this->withToken($neocToken)
            ->postJson("/api/v1/notifications/dispatches/{$dispatch->id}/retry");

        $retryRes->assertOk()->assertJsonPath('data.delivery_status', NotificationDispatch::STATUS_DELIVERED);

        // Verify dashboard KPIs respond cleanly
        $dashRes = $this->withToken($neocToken)->getJson('/api/v1/notifications/dashboard');
        $dashRes->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'kpis' => [
                        'total_notifications',
                        'delivered_notifications',
                        'failed_notifications',
                        'pending_notifications',
                        'active_alerts',
                        'unacknowledged_alerts',
                    ],
                ],
            ]);
    }

    public function test_validation_uuid_constraints_directory_privacy_and_rule_crud(): void
    {
        $neocToken = $this->createToken([
            'sub' => '88888888-8888-4888-8888-888888888881',
            'email' => 'neoc.review@modmr.gov.bd',
        ]);

        // 1. Non-UUID path parameters fail safely with 404 (not 500)
        $this->withToken($neocToken)->getJson('/api/v1/notifications/not-a-uuid')->assertStatus(404);
        $this->withToken($neocToken)->getJson('/api/v1/notifications/alerts/not-a-uuid')->assertStatus(404);

        // 2. External partner lookups do not expose internal officer roster or alert rules
        $partnerToken = $this->createToken([
            'sub' => '88888888-8888-4888-8888-888888888882',
            'email' => 'partner.review@modmr.gov.bd',
            'roles' => ['ROLE_EXTERNAL_PARTNER'],
        ]);
        $partnerLookups = $this->withToken($partnerToken)->getJson('/api/v1/notifications/lookups');
        $partnerLookups->assertOk()
            ->assertJsonCount(0, 'data.users')
            ->assertJsonCount(0, 'data.alert_rules');

        // 3. Invalid template_id and missing targeting criteria return 422
        $this->withToken($neocToken)->postJson('/api/v1/notifications', [
            'template_id' => (string) Str::uuid(),
            'category' => 'SYSTEM_ANNOUNCEMENT',
            'severity' => 'INFO',
            'channels' => ['IN_APP'],
            'title_bn' => 'ক',
            'title_en' => 'A',
            'message_bn' => 'খ',
            'message_en' => 'B',
            'target_type' => 'ALL',
        ])->assertStatus(422);

        $this->withToken($neocToken)->postJson('/api/v1/notifications', [
            'category' => 'SYSTEM_ANNOUNCEMENT',
            'severity' => 'INFO',
            'channels' => ['IN_APP'],
            'title_bn' => 'ক',
            'title_en' => 'A',
            'message_bn' => 'খ',
            'message_en' => 'B',
            'target_type' => 'ROLE',
            'target_criteria' => [],
        ])->assertStatus(422);

        // 4. Alert Rule Update & Delete CRUD + Template Update & Delete (system template protected)
        $ruleRes = $this->withToken($neocToken)->postJson('/api/v1/notifications/alert-rules', [
            'rule_code' => 'RULE-REVIEW-TEST',
            'name_bn' => 'পর্যালোচনা নিয়ম',
            'name_en' => 'Review Rule',
            'category' => 'INVENTORY_ALERT',
            'event_type' => 'LOW_STOCK_THRESHOLD',
            'severity' => 'WARNING',
            'condition_config' => ['operator' => '<=', 'threshold' => 15],
            'channels' => ['IN_APP'],
            'target_roles' => ['ROLE_DRRO'],
        ]);
        $ruleRes->assertStatus(201);
        $ruleId = $ruleRes->json('data.id');

        $this->withToken($neocToken)->putJson("/api/v1/notifications/alert-rules/{$ruleId}", [
            'severity' => 'HIGH',
            'is_active' => false,
        ])->assertOk()->assertJsonPath('data.severity', 'HIGH')->assertJsonPath('data.is_active', false);

        $this->withToken($neocToken)->deleteJson("/api/v1/notifications/alert-rules/{$ruleId}")
            ->assertOk();
        $this->assertSoftDeleted(AlertRule::class, ['id' => $ruleId]);

        $sysTpl = NotificationTemplate::where('is_system', true)->firstOrFail();
        $this->withToken($neocToken)->deleteJson("/api/v1/notifications/templates/{$sysTpl->id}")
            ->assertStatus(422);
    }
}
