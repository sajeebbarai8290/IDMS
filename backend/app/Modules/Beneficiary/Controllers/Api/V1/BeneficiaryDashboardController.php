<?php

namespace App\Modules\Beneficiary\Controllers\Api\V1;

use App\Core\Models\AdministrativeUnit;
use App\Modules\Beneficiary\Models\DisasterEligibility;
use App\Modules\Beneficiary\Models\DuplicateSuspect;
use App\Modules\Beneficiary\Models\Household;
use App\Modules\Disaster\Models\DisasterEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class BeneficiaryDashboardController extends Controller
{
    /**
     * Aggregated metrics for the Beneficiary Management dashboard.
     */
    public function metrics(Request $request): JsonResponse
    {
        $user = $request->user();

        $baseQuery = Household::inJurisdiction($user);

        $totalHouseholds = (clone $baseQuery)->count();
        $totalIndividuals = (clone $baseQuery)->sum('family_member_count');

        $verifiedCount = (clone $baseQuery)->where('verification_status', 'APPROVED')->count();
        $fieldVerifiedCount = (clone $baseQuery)->where('verification_status', 'FIELD_VERIFIED')->count();
        $pendingCount = (clone $baseQuery)->whereIn('verification_status', ['DRAFT', 'PENDING_VERIFICATION'])->count();
        $rejectedCount = (clone $baseQuery)->where('verification_status', 'REJECTED')->count();

        // Vulnerability metrics
        $vulnerableCount = (clone $baseQuery)->where('vulnerability_score', '>=', 50.0)->count();
        $femaleHeadedCount = (clone $baseQuery)->where('is_female_headed', true)->count();
        $hasDisabledCount = (clone $baseQuery)->where('has_disabled', true)->count();
        $hasElderlyCount = (clone $baseQuery)->where('has_elderly', true)->count();
        $displacedCount = (clone $baseQuery)->where('is_displaced', true)->count();

        // Income breakdown
        $extremePoorCount = (clone $baseQuery)->where('income_category', 'EXTREME_POOR')->count();
        $poorCount = (clone $baseQuery)->where('income_category', 'POOR')->count();
        $lowerMiddleCount = (clone $baseQuery)->where('income_category', 'LOWER_MIDDLE')->count();

        // Duplicate suspect review count
        $pendingDuplicatesCount = DuplicateSuspect::where('status', 'PENDING_REVIEW')
            ->whereHas('household', function ($q) use ($user) {
                $q->inJurisdiction($user);
            })->count();

        $metricsPayload = [
            'total_households' => $totalHouseholds,
            'total_individuals' => (int) $totalIndividuals,
            'verification' => [
                'approved' => $verifiedCount,
                'field_verified' => $fieldVerifiedCount,
                'pending' => $pendingCount,
                'rejected' => $rejectedCount,
            ],
            'vulnerability' => [
                'high_vulnerability_households' => $vulnerableCount,
                'female_headed_households' => $femaleHeadedCount,
                'households_with_disabled' => $hasDisabledCount,
                'households_with_elderly' => $hasElderlyCount,
                'displaced_households' => $displacedCount,
            ],
            'poverty_distribution' => [
                'extreme_poor' => $extremePoorCount,
                'poor' => $poorCount,
                'lower_middle' => $lowerMiddleCount,
            ],
            'pending_duplicate_reviews' => $pendingDuplicatesCount,
        ];

        return response()->json([
            'success' => true,
            'data' => $metricsPayload,
            'metrics' => $metricsPayload,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Geographical aggregation breakdown.
     */
    public function geoBreakdown(Request $request): JsonResponse
    {
        $user = $request->user();

        $households = Household::with('administrativeUnit')
            ->inJurisdiction($user)
            ->get();

        $byJurisdiction = $households->groupBy(function ($hh) {
            return $hh->administrativeUnit?->name_en ?? 'Unknown';
        })->map(function ($group, $name) {
            return [
                'jurisdiction_name' => $name,
                'households_count' => $group->count(),
                'individuals_count' => $group->sum('family_member_count'),
                'approved_count' => $group->where('verification_status', 'APPROVED')->count(),
                'avg_vulnerability_score' => round($group->avg('vulnerability_score') ?? 0, 2),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $byJurisdiction,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Disaster linkage breakdown.
     */
    public function disasterBreakdown(Request $request): JsonResponse
    {
        $user = $request->user();

        $disasters = DisasterEvent::all();

        $breakdown = $disasters->map(function ($disaster) use ($user) {
            $count = DisasterEligibility::where('disaster_event_id', $disaster->id)
                ->whereHas('household', function ($q) use ($user) {
                    $q->inJurisdiction($user);
                })->count();

            $disbursedCount = DisasterEligibility::where('disaster_event_id', $disaster->id)
                ->where('eligibility_status', 'ASSISTANCE_DISBURSED')
                ->whereHas('household', function ($q) use ($user) {
                    $q->inJurisdiction($user);
                })->count();

            return [
                'disaster_id' => $disaster->id,
                'disaster_code' => $disaster->disaster_code,
                'name_en' => $disaster->name_en,
                'name_bn' => $disaster->name_bn,
                'type' => $disaster->disaster_type,
                'enrolled_households' => $count,
                'assistance_disbursed' => $disbursedCount,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $breakdown,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
