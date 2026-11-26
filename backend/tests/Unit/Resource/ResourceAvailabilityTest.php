<?php

namespace Tests\Unit\Resource;

use App\Modules\Resource\Models\ResourceAsset;
use Tests\TestCase;

class ResourceAvailabilityTest extends TestCase
{
    public function test_only_active_available_resources_are_eligible_for_allocation(): void
    {
        $available = new ResourceAsset(['status' => ResourceAsset::STATUS_AVAILABLE, 'is_active' => true]);
        $this->assertTrue($available->isAvailableForAllocation());

        foreach ([
            ResourceAsset::STATUS_ASSIGNED,
            ResourceAsset::STATUS_IN_USE,
            ResourceAsset::STATUS_DEPLOYED,
            ResourceAsset::STATUS_UNDER_MAINTENANCE,
            ResourceAsset::STATUS_MAINTENANCE,
            ResourceAsset::STATUS_DAMAGED,
            ResourceAsset::STATUS_RETIRED,
        ] as $unavailableStatus) {
            $asset = new ResourceAsset(['status' => $unavailableStatus, 'is_active' => true]);
            $this->assertFalse(
                $asset->isAvailableForAllocation(),
                "Expected status {$unavailableStatus} to be unavailable for allocation."
            );
        }

        $inactive = new ResourceAsset(['status' => ResourceAsset::STATUS_AVAILABLE, 'is_active' => false]);
        $this->assertFalse($inactive->isAvailableForAllocation());
    }
}
