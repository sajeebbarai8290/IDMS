<?php

namespace Tests\Unit\Notification;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Modules\Notification\Adapters\FcmPushNotificationAdapter;
use App\Modules\Notification\Adapters\MockPushNotificationAdapter;
use App\Modules\Notification\Adapters\MockSmsGatewayAdapter;
use App\Modules\Notification\Adapters\TeletalkSmsGatewayAdapter;
use App\Modules\Notification\Models\NotificationDevice;
use App\Modules\Notification\Models\NotificationDispatch;
use App\Modules\Notification\Models\NotificationPreference;
use App\Modules\Notification\Models\NotificationTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class NotificationEngineUnitTest extends TestCase
{
    use RefreshDatabase;

    public function test_bilingual_template_renders_variables_accurately(): void
    {
        $template = new NotificationTemplate([
            'title_bn' => '{{area_bn}} সতর্কবার্তা',
            'title_en' => '{{area_en}} Warning',
            'subject_bn' => 'বিষয়: {{disaster_name}}',
            'subject_en' => 'Subject: {{disaster_name}}',
            'body_bn' => '{{area_bn}} এলাকায় {{disaster_name}} সংকেত {{signal}}।',
            'body_en' => '{{disaster_name}} signal {{signal}} in {{area_en}}.',
        ]);

        $rendered = $template->render([
            'area_bn' => 'দাকোপ',
            'area_en' => 'Dacope',
            'disaster_name' => 'Cyclone Remal',
            'signal' => '8',
        ]);

        $this->assertSame('দাকোপ সতর্কবার্তা', $rendered['title_bn']);
        $this->assertSame('Dacope Warning', $rendered['title_en']);
        $this->assertStringContainsString('দাকোপ এলাকায় Cyclone Remal সংকেত 8', $rendered['body_bn']);
        $this->assertStringContainsString('Cyclone Remal signal 8 in Dacope', $rendered['body_en']);
    }

    public function test_destination_masking_hides_sensitive_contact_details(): void
    {
        $this->assertSame('un***@modmr.gov.bd', NotificationDispatch::maskDestination('uno.dacope@modmr.gov.bd', 'EMAIL'));
        $this->assertSame('**********2233', NotificationDispatch::maskDestination('+8801711002233', 'SMS'));
        $this->assertSame('device:abcdef***', NotificationDispatch::maskDestination('abcdef1234567890', 'PUSH'));
        $this->assertSame('in-app', NotificationDispatch::maskDestination('in-app', 'IN_APP'));
        $this->assertSame('***', NotificationDispatch::maskDestination(null, 'SMS'));
    }

    public function test_push_device_token_is_encrypted_and_hidden_from_serialization(): void
    {
        $unit = AdministrativeUnit::create([
            'id' => (string) Str::uuid(),
            'code' => '00',
            'name_en' => 'National',
            'name_bn' => 'জাতীয়',
            'tier_level' => 'NATIONAL',
            'ancestry_path' => '/',
            'is_active' => true,
        ]);

        $user = User::create([
            'id' => (string) Str::uuid(),
            'keycloak_sub' => (string) Str::uuid(),
            'email' => 'flutter.field@modmr.gov.bd',
            'full_name_en' => 'Flutter Officer',
            'role_code' => 'ROLE_FIELD_ENUMERATOR',
            'administrative_unit_id' => $unit->id,
            'is_active' => true,
        ]);

        $device = new NotificationDevice([
            'user_id' => $user->id,
            'device_uuid' => 'flutter-unit-001',
            'platform' => 'ANDROID',
            'push_provider' => 'FCM',
            'is_active' => true,
        ]);
        $rawToken = 'fcm_live_secret_token_abcdef123456';
        $device->setPlainPushToken($rawToken);
        $device->save();

        $this->assertNotSame($rawToken, $device->push_token_encrypted);
        $this->assertSame($rawToken, $device->getPlainPushToken());
        $this->assertSame(hash('sha256', $rawToken), $device->push_token_hash);

        $serialized = $device->toArray();
        $this->assertArrayNotHasKey('push_token_encrypted', $serialized);
        $this->assertArrayNotHasKey('push_token_hash', $serialized);
    }

    public function test_critical_emergency_preference_policy_overrides_channel_mute(): void
    {
        $pref = new NotificationPreference([
            'category' => NotificationTemplate::CATEGORY_EMERGENCY_ALERT,
            'in_app_enabled' => false,
            'sms_enabled' => false,
            'email_enabled' => false,
            'push_enabled' => false,
            'policy_locked' => true,
        ]);

        $this->assertTrue($pref->allowsChannel('IN_APP', 'HIGH'));
        $this->assertTrue($pref->allowsChannel('SMS', 'HIGH'));
        $this->assertFalse($pref->allowsChannel('EMAIL', 'HIGH'));

        // Any category with CRITICAL severity forces IN_APP and SMS delivery
        $generalPref = new NotificationPreference([
            'category' => NotificationTemplate::CATEGORY_SYSTEM_ANNOUNCEMENT,
            'in_app_enabled' => false,
            'sms_enabled' => false,
            'email_enabled' => false,
            'push_enabled' => false,
        ]);

        $this->assertTrue($generalPref->allowsChannel('IN_APP', 'CRITICAL'));
        $this->assertTrue($generalPref->allowsChannel('SMS', 'CRITICAL'));
        $this->assertFalse($generalPref->allowsChannel('IN_APP', 'INFO'));
    }

    public function test_production_sms_and_push_adapters_fail_closed_without_credentials(): void
    {
        config()->set('services.sms.base_url', null);
        config()->set('services.sms.api_key', null);
        config()->set('services.push.fcm_server_key', null);

        $mockSms = new MockSmsGatewayAdapter();
        $resSms = $mockSms->sendSms('+8801711002233', 'ঘূর্ণিঝড় সতর্কতা');
        $this->assertSame('DELIVERED', $resSms['status']);
        $this->assertSame('mock_sms', $resSms['provider']);

        $mockPush = new MockPushNotificationAdapter();
        $resPush = $mockPush->sendPush('token-1234567890', 'ANDROID', 'Alert', 'Body');
        $this->assertSame('DELIVERED', $resPush['status']);
        $this->assertSame('mock_push', $resPush['provider']);

        $teletalk = new TeletalkSmsGatewayAdapter();
        $this->assertSame('UNCONFIGURED', $teletalk->healthCheck()['status']);
        $this->expectException(RuntimeException::class);
        $teletalk->sendSms('+8801711002233', 'Test');
    }

    public function test_production_fcm_adapter_fails_closed_without_server_key(): void
    {
        config()->set('services.push.fcm_server_key', null);

        $fcm = new FcmPushNotificationAdapter();
        $this->assertSame('UNCONFIGURED', $fcm->healthCheck()['status']);
        $this->expectException(RuntimeException::class);
        $fcm->sendPush('token-1234567890', 'ANDROID', 'Alert', 'Body');
    }
}
