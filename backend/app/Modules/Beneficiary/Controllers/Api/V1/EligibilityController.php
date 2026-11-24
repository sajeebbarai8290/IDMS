<?php

namespace App\Modules\Beneficiary\Controllers\Api\V1;

use App\Core\Models\AuditLog;
use App\Modules\Beneficiary\Models\DisasterEligibility;
use App\Modules\Beneficiary\Models\Household;
use App\Modules\Disaster\Models\DisasterEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class EligibilityController extends Controller
{
    /**
     * Link household to a specific disaster declaration.
     */
    public function linkDisaster(Request $request, Household $household): JsonResponse
    {
        $user = $request->user();

        if (!$user->can('update', $household)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $request->validate([
            'disaster_event_id' => 'required|uuid',
            'eligibility_criteria' => 'nullable|string|max:1000',
            'eligibility_status' => 'nullable|in:ELIGIBLE,INELIGIBLE,ENROLLED,ASSISTANCE_DISBURSED',
        ]);

        $disaster = DisasterEvent::findOrFail($request->input('disaster_event_id'));

        $eligibility = DisasterEligibility::firstOrCreate(
            [
                'household_id' => $household->id,
                'disaster_event_id' => $disaster->id,
            ],
            [
                'eligibility_status' => $request->input('eligibility_status', 'ELIGIBLE'),
                'eligibility_criteria' => $request->input('eligibility_criteria'),
                'assistance_count' => 0,
                'enrolled_by' => $user->id,
            ]
        );

        AuditLog::log(
            'BENEFICIARY_DISASTER_LINKED',
            DisasterEligibility::class,
            $eligibility->id,
            null,
            ['disaster_code' => $disaster->disaster_code, 'household_code' => $household->household_code],
            $user,
            $household->administrative_unit_id
        );

        return response()->json([
            'success' => true,
            'message' => 'Household successfully linked to disaster declaration.',
            'eligibility' => [
                'id' => $eligibility->id,
                'disaster_code' => $disaster->disaster_code,
                'disaster_name' => $disaster->name_en,
                'eligibility_status' => $eligibility->eligibility_status,
                'assistance_count' => (int) ($eligibility->assistance_count ?? 0),
            ],
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    /**
     * Record aid/assistance disbursement delivery for this disaster.
     */
    public function recordAssistance(Request $request, Household $household, DisasterEligibility $eligibility): JsonResponse
    {
        $user = $request->user();

        if (!$user->can('update', $household)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $eligibility->increment('assistance_count');
        $eligibility->update([
            'last_assistance_at' => now(),
            'eligibility_status' => 'ASSISTANCE_DISBURSED',
        ]);

        AuditLog::log(
            'ASSISTANCE_DISBURSED_LOGGED',
            DisasterEligibility::class,
            $eligibility->id,
            null,
            ['count' => $eligibility->assistance_count, 'household_code' => $household->household_code],
            $user,
            $household->administrative_unit_id
        );

        return response()->json([
            'success' => true,
            'message' => 'Assistance disbursement logged successfully.',
            'assistance_count' => $eligibility->assistance_count,
            'last_assistance_at' => $eligibility->last_assistance_at->toIso8601String(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
