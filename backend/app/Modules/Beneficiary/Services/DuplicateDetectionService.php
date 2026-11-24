<?php

namespace App\Modules\Beneficiary\Services;

use App\Core\Models\AuditLog;
use App\Core\Models\User;
use App\Modules\Beneficiary\Models\DuplicateSuspect;
use App\Modules\Beneficiary\Models\Household;
use Illuminate\Support\Collection;

/**
 * Class DuplicateDetectionService
 *
 * Multi-factor heuristic and cryptographic duplicate detection engine.
 * Never performs destructive automatic merges; flags suspects for human review.
 *
 * @package App\Modules\Beneficiary\Services
 */
class DuplicateDetectionService
{
    /**
     * Scan for potential duplicates of a target household.
     *
     * @param Household $household
     * @return Collection<DuplicateSuspect>
     */
    public function scan(Household $household): Collection
    {
        $suspects = collect();

        // 1. Exact NID match (100% confidence)
        $nidMatches = Household::where('id', '!=', $household->id)
            ->where('hoh_nid_hash', $household->hoh_nid_hash)
            ->get();

        foreach ($nidMatches as $match) {
            $suspects->push($this->recordSuspect($household, $match, 'NID_EXACT', 100.00));
        }

        // 2. Exact Mobile match (85% confidence)
        if ($household->hoh_mobile_hash) {
            $mobileMatches = Household::where('id', '!=', $household->id)
                ->where('hoh_mobile_hash', $household->hoh_mobile_hash)
                ->get();

            foreach ($mobileMatches as $match) {
                // Avoid redundant entry if already matched by NID
                if (!$suspects->contains('matched_household_id', $match->id)) {
                    $suspects->push($this->recordSuspect($household, $match, 'MOBILE_MATCH', 85.00));
                }
            }
        }

        // 3. Name & Date of Birth Match (75% confidence)
        if ($household->hoh_dob) {
            $nameDobMatches = Household::where('id', '!=', $household->id)
                ->where('hoh_dob', $household->hoh_dob)
                ->where(function ($q) use ($household) {
                    $q->whereRaw('LOWER(hoh_name_en) = ?', [strtolower(trim($household->hoh_name_en))])
                      ->orWhere('hoh_name_bn', trim($household->hoh_name_bn));
                })
                ->get();

            foreach ($nameDobMatches as $match) {
                if (!$suspects->contains('matched_household_id', $match->id)) {
                    $suspects->push($this->recordSuspect($household, $match, 'NAME_DOB_FUZZY', 75.00));
                }
            }
        }

        // 4. GPS Coordinates Proximity (within ~50 meters = ~0.0005 deg lat/lng)
        if ($household->latitude && $household->longitude) {
            $delta = 0.0005;
            $geoMatches = Household::where('id', '!=', $household->id)
                ->whereBetween('latitude', [$household->latitude - $delta, $household->latitude + $delta])
                ->whereBetween('longitude', [$household->longitude - $delta, $household->longitude + $delta])
                ->where('administrative_unit_id', $household->administrative_unit_id)
                ->get();

            foreach ($geoMatches as $match) {
                if (!$suspects->contains('matched_household_id', $match->id)) {
                    $suspects->push($this->recordSuspect($household, $match, 'ADDRESS_COORDINATE_PROXIMITY', 60.00));
                }
            }
        }

        return $suspects;
    }

    /**
     * Create or retrieve a DuplicateSuspect record.
     */
    protected function recordSuspect(
        Household $household,
        Household $matched,
        string $matchType,
        float $confidenceScore
    ): DuplicateSuspect {
        return DuplicateSuspect::firstOrCreate(
            [
                'household_id' => $household->id,
                'matched_household_id' => $matched->id,
            ],
            [
                'match_type' => $matchType,
                'confidence_score' => $confidenceScore,
                'status' => 'PENDING_REVIEW',
            ]
        );
    }

    /**
     * Resolve a duplicate suspect record (Authorized override or confirmation).
     *
     * @param DuplicateSuspect $suspect
     * @param string $status 'CONFIRMED_DUPLICATE' | 'FALSE_POSITIVE_RESOLVED'
     * @param string|null $notes
     * @param User $user
     * @return DuplicateSuspect
     */
    public function resolve(DuplicateSuspect $suspect, string $status, ?string $notes, User $user): DuplicateSuspect
    {
        $oldValues = $suspect->toArray();

        $suspect->update([
            'status' => $status,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'resolution_notes' => $notes,
        ]);

        AuditLog::log(
            'DUPLICATE_FLAG_RESOLVED',
            DuplicateSuspect::class,
            $suspect->id,
            $oldValues,
            $suspect->toArray(),
            $user,
            $suspect->household->administrative_unit_id
        );

        return $suspect;
    }
}
