<?php

namespace Tests\Unit\Project;

use App\Core\Services\AdministrativeScopeService;
use App\Modules\Project\Models\ProjectMilestone;
use App\Modules\Project\Services\ProjectManagementService;
use App\Modules\Project\Services\ProjectSpatialService;
use Tests\TestCase;

class ProjectCalculationTest extends TestCase
{
    public function test_weighted_physical_progress_calculation(): void
    {
        $service = new ProjectManagementService(
            new AdministrativeScopeService(),
            new ProjectSpatialService()
        );

        $m1 = new ProjectMilestone(['weight_percentage' => 25, 'completion_percentage' => 100]);
        $m2 = new ProjectMilestone(['weight_percentage' => 75, 'completion_percentage' => 40]);

        // (25*100 + 75*40) / 100 = 55.00
        $this->assertSame(55.0, $service->computeWeightedPhysicalProgress([$m1, $m2]));
        $this->assertSame(0.0, $service->computeWeightedPhysicalProgress([]));
    }

    public function test_financial_summary_calculation_uses_revised_budget_when_present(): void
    {
        $service = new ProjectManagementService(
            new AdministrativeScopeService(),
            new ProjectSpatialService()
        );

        $summary = $service->calculateFinancialSummary(1000000.0, 1200000.0, 600000.0, 300000.0);

        $this->assertSame(1200000.0, $summary['effective_budget_bdt']);
        $this->assertSame(900000.0, $summary['remaining_balance_bdt']);
        $this->assertSame(600000.0, $summary['unreleased_budget_bdt']);
        $this->assertSame(300000.0, $summary['unspent_released_bdt']);
        $this->assertSame(25.0, $summary['financial_progress_pct']);
    }

    public function test_spatial_service_normalizes_feature_collection_to_multipolygon(): void
    {
        $spatial = new ProjectSpatialService();

        $fc = [
            'type' => 'FeatureCollection',
            'features' => [
                [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Polygon',
                        'coordinates' => [[[89.4, 22.4], [89.5, 22.4], [89.5, 22.5], [89.4, 22.5], [89.4, 22.4]]],
                    ],
                ],
                [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Polygon',
                        'coordinates' => [[[89.6, 22.6], [89.7, 22.6], [89.7, 22.7], [89.6, 22.7], [89.6, 22.6]]],
                    ],
                ],
            ],
        ];

        $normalized = $spatial->normalizeToPolygonGeometry($fc);

        $this->assertNotNull($normalized);
        $this->assertSame('MultiPolygon', $normalized['type']);
        $this->assertCount(2, $normalized['coordinates']);
    }
}
