<?php

namespace Tests\Unit\Volunteer;

use App\Modules\Volunteer\Models\Volunteer;
use App\Modules\Volunteer\Services\VolunteerManagementService;
use Carbon\Carbon;
use Tests\TestCase;

class VolunteerWorkflowAndPrivacyTest extends TestCase
{
    public function test_volunteer_pii_hashing_and_masking_hides_raw_values(): void
    {
        $rawNid = '1992471234567';
        $rawMobile = '01712345678';
        $rawEmail = 'volunteer@modmr.gov.bd';

        $hash1 = Volunteer::hashNid($rawNid);
        $hash2 = Volunteer::hashNid(' ' . $rawNid . ' ');
        $this->assertSame($hash1, $hash2);
        $this->assertSame(64, strlen($hash1));

        $maskedNid = Volunteer::maskNid($rawNid);
        $this->assertStringNotContainsString('471234', $maskedNid);
        $this->assertStringStartsWith('1992', $maskedNid);
        $this->assertStringEndsWith('567', $maskedNid);

        $maskedMobile = Volunteer::maskMobile($rawMobile);
        $this->assertSame('017*****678', $maskedMobile);

        $maskedEmail = Volunteer::maskEmail($rawEmail);
        $this->assertSame('vo***@modmr.gov.bd', $maskedEmail);
    }

    public function test_attendance_duration_calculation_is_non_negative_minutes(): void
    {
        $service = app(VolunteerManagementService::class);
        $checkIn = Carbon::parse('2026-11-26 08:15:00');
        $checkOut = Carbon::parse('2026-11-26 16:45:00');

        $this->assertSame(510, $service->computeDurationMinutes($checkIn, $checkOut));
        $this->assertNull($service->computeDurationMinutes($checkIn, null));
    }

    public function test_deployable_availabilities_exclude_deployed_and_inactive(): void
    {
        $this->assertContains(Volunteer::AVAILABILITY_AVAILABLE, Volunteer::DEPLOYABLE_AVAILABILITIES);
        $this->assertContains(Volunteer::AVAILABILITY_STANDBY, Volunteer::DEPLOYABLE_AVAILABILITIES);
        $this->assertNotContains(Volunteer::AVAILABILITY_DEPLOYED, Volunteer::DEPLOYABLE_AVAILABILITIES);
        $this->assertNotContains(Volunteer::AVAILABILITY_INACTIVE, Volunteer::DEPLOYABLE_AVAILABILITIES);
    }
}
