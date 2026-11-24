<?php

namespace App\Modules\Beneficiary\Controllers\Api\V1;

use App\Modules\Beneficiary\Models\DuplicateSuspect;
use App\Modules\Beneficiary\Requests\ResolveDuplicateRequest;
use App\Modules\Beneficiary\Resources\DuplicateSuspectResource;
use App\Modules\Beneficiary\Services\DuplicateDetectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DuplicateDetectionController extends Controller
{
    public function __construct(
        protected DuplicateDetectionService $duplicateService
    ) {}

    /**
     * List suspected duplicates requiring human review.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = DuplicateSuspect::with(['household.administrativeUnit', 'matchedHousehold.administrativeUnit', 'reviewer'])
            ->whereHas('household', function ($q) use ($user) {
                $q->inJurisdiction($user);
            });

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        } else {
            $query->where('status', 'PENDING_REVIEW');
        }

        if ($matchType = $request->query('match_type')) {
            $query->where('match_type', $matchType);
        }

        $perPage = min(100, max(5, (int) $request->query('per_page', 15)));
        $suspects = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => DuplicateSuspectResource::collection($suspects->items()),
            'pagination' => [
                'current_page' => $suspects->currentPage(),
                'per_page' => $suspects->perPage(),
                'total' => $suspects->total(),
                'last_page' => $suspects->lastPage(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Resolve a duplicate suspect record (Authorized override or confirmation).
     */
    public function resolve(ResolveDuplicateRequest $request, DuplicateSuspect $suspect): JsonResponse
    {
        $user = $request->user();

        if (!$user->can('overrideDuplicate', $suspect->household)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Overriding duplicate suspect records requires authorized officer privileges.',
            ], 403);
        }

        $validated = $request->validated();

        $resolved = $this->duplicateService->resolve(
            $suspect,
            $validated['status'],
            $validated['resolution_notes'],
            $user
        );

        return response()->json([
            'success' => true,
            'message' => 'Duplicate suspect status updated successfully.',
            'suspect' => new DuplicateSuspectResource($resolved->load(['household', 'matchedHousehold', 'reviewer'])),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
