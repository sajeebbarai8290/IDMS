<?php

namespace Tests\Unit\DamageAssessment;

use App\Core\Services\AdministrativeScopeService;
use App\Modules\DamageAssessment\Models\DamageAssessment;
use App\Modules\DamageAssessment\Policies\DamageAssessmentPolicy;
use App\Modules\DamageAssessment\Services\DamageAssessmentService;
use App\Modules\DamageAssessment\Services\DamageSpatialService;
use PHPUnit\Framework\TestCase;

class DamageCalculationAndSyncTest extends TestCase
{
    public function test_sector_totals_calculation_aggregates_counts_and_bdt_loss(): void
    {
        $scopeService = new AdministrativeScopeService();
        $spatial = new DamageSpatialService();
        $policy = new DamageAssessmentPolicy($scopeService);
        $service = new DamageAssessmentService($scopeService, $spatial, $policy);

        $totals = $service->calculateSectorTotals([
            [
                'fully_damaged_count' => 12,
                'partially_damaged_count' => 25,
                'minor_damaged_count' => 10,
                'estimated_loss_bdt' => 1250000.55,
            ],
            [
                'fully_damaged_count' => 8,
                'partially_damaged_count' => 15,
                'minor_damaged_count' => 5,
                'estimated_loss_bdt' => 750000.45,
            ],
        ]);

        $this->assertSame(20, $totals['total_fully_damaged']);
        $this->assertSame(40, $totals['total_partially_damaged']);
        $this->assertSame(15, $totals['total_minor_damaged']);
        $this->assertSame(2000001.00, $totals['total_estimated_loss_bdt']);
    }

    public function test_severity_derivation_thresholds(): void
    {
        $scopeService = new AdministrativeScopeService();
        $spatial = new DamageSpatialService();
        $policy = new DamageAssessmentPolicy($scopeService);
        $service = new DamageAssessmentService($scopeService, $spatial, $policy);

        $this->assertSame(DamageAssessment::SEVERITY_LOW, $service->deriveSeverityLevel(5, 20, 200000));
        $this->assertSame(DamageAssessment::SEVERITY_MODERATE, $service->deriveSeverityLevel(25, 150, 1500000));
        $this->assertSame(DamageAssessment::SEVERITY_SEVERE, $service->deriveSeverityLevel(120, 600, 12000000));
        $this->assertSame(DamageAssessment::SEVERITY_CRITICAL, $service->deriveSeverityLevel(600, 2500, 65000000));
    }

    public function test_spatial_geojson_normalization_and_heatmap_intensity_weight(): void
    {
        $spatial = new DamageSpatialService();

        $fc = [
            'type' => 'FeatureCollection',
            'features' => [
                [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Polygon',
                        'coordinates' => [[[89.5, 22.5], [89.6, 22.5], [89.6, 22.6], [89.5, 22.6], [89.5, 22.5]]],
                    ],
                ],
            ],
        ];

        $normalized = $spatial->normalizeToPolygonGeometry($fc);
        $this->assertIsArray($normalized);
        $this->assertSame('Polygon', $normalized['type']);

        $weightLow = $spatial->computeIntensityWeight(DamageAssessment::SEVERITY_LOW, 100000, 10, 50000000);
        $weightCrit = $spatial->computeIntensityWeight(DamageAssessment::SEVERITY_CRITICAL, 45000000, 1500, 50000000);

        $this->assertGreaterThanOrEqual(0.15, $weightLow);
        $this->assertLessThanOrEqual(1.0, $weightCrit);
        $this->assertGreaterThan($weightLow, $weightCrit);
    }
}
