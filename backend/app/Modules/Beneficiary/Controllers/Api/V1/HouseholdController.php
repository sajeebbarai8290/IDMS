<?php

namespace App\Modules\Beneficiary\Controllers\Api\V1;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\AuditLog;
use App\Modules\Beneficiary\Models\Household;
use App\Modules\Beneficiary\Models\HouseholdMember;
use App\Modules\Beneficiary\Requests\StoreHouseholdRequest;
use App\Modules\Beneficiary\Requests\UpdateHouseholdRequest;
use App\Modules\Beneficiary\Resources\HouseholdDetailResource;
use App\Modules\Beneficiary\Resources\HouseholdResource;
use App\Modules\Beneficiary\Services\DuplicateDetectionService;
use App\Modules\Beneficiary\Services\VulnerabilityCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HouseholdController extends Controller
{
    public function __construct(
        protected VulnerabilityCalculationService $vulnerabilityService,
        protected DuplicateDetectionService $duplicateService
    ) {}

    /**
     * Display a paginated listing of households scoped to officer's administrative jurisdiction.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Household::with(['administrativeUnit'])
            ->inJurisdiction($user);

        // Search by keyword: household code, english name, bangla name, village
        if ($search = $request->query('search')) {
            $search = trim($search);
            $query->where(function ($q) use ($search) {
                $q->where('household_code', 'ilike', "%{$search}%")
                    ->orWhere('hoh_name_en', 'ilike', "%{$search}%")
                    ->orWhere('hoh_name_bn', 'like', "%{$search}%")
                    ->orWhere('village_ward_name', 'ilike', "%{$search}%");

                // If numeric, check NID hash or mobile hash
                if (is_numeric($search)) {
                    $nidHash = Household::hashNid($search);
                    $mobileHash = Household::hashMobile($search);
                    $q->orWhere('hoh_nid_hash', $nidHash)
                      ->orWhere('hoh_mobile_hash', $mobileHash);
                }
            });
        }

        // Filter by Verification Status
        if ($status = $request->query('verification_status')) {
            $query->where('verification_status', $status);
        }

        // Filter by Gender
        if ($gender = $request->query('hoh_gender')) {
            $query->where('hoh_gender', strtoupper($gender));
        }

        // Filter by Income Category
        if ($incomeCat = $request->query('income_category')) {
            $query->where('income_category', strtoupper($incomeCat));
        }

        // Filter by Administrative Unit
        if ($adminUnitId = $request->query('administrative_unit_id')) {
            $query->where('administrative_unit_id', $adminUnitId);
        }

        // Filter by Vulnerability Threshold
        if ($minScore = $request->query('min_vulnerability')) {
            $query->where('vulnerability_score', '>=', (float) $minScore);
        }

        // Filter by specific vulnerability indicators
        if ($request->boolean('female_headed_only')) {
            $query->where('is_female_headed', true);
        }
        if ($request->boolean('has_disabled_only')) {
            $query->where('has_disabled', true);
        }
        if ($request->boolean('has_elderly_only')) {
            $query->where('has_elderly', true);
        }
        if ($request->boolean('is_displaced_only')) {
            $query->where('is_displaced', true);
        }

        // Sorting
        $sortBy = $request->query('sort_by', 'created_at');
        $sortOrder = strtolower($request->query('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        $allowedSorts = ['household_code', 'hoh_name_en', 'vulnerability_score', 'created_at', 'verification_status'];
        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = min(100, max(5, (int) $request->query('per_page', 15)));
        $households = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => HouseholdResource::collection($households->items()),
            'pagination' => [
                'current_page' => $households->currentPage(),
                'per_page' => $households->perPage(),
                'total' => $households->total(),
                'last_page' => $households->lastPage(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Store a newly created household in the master registry.
     */
    public function store(StoreHouseholdRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $rawNid = trim($validated['hoh_nid']);
        $rawMobile = !empty($validated['hoh_mobile']) ? trim($validated['hoh_mobile']) : null;

        $nidHash = Household::hashNid($rawNid);
        $mobileHash = Household::hashMobile($rawMobile);

        // Pre-check for duplicate NID
        if (Household::where('hoh_nid_hash', $nidHash)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'A household with this National ID already exists in the national database.',
                'errors' => [
                    'hoh_nid' => ['এই জাতীয় পরিচয়পত্র নম্বর দিয়ে ইতিমধ্যে একটি পরিবার নিবন্ধিত রয়েছে।'],
                ],
            ], 422);
        }

        // Resolve Administrative Unit Code for Household Code generation
        $adminUnit = AdministrativeUnit::findOrFail($validated['administrative_unit_id']);
        $code = 'HH-' . $adminUnit->code . '-' . strtoupper(Str::random(6));

        // Calculate Vulnerability Score
        $membersData = $validated['members'] ?? [];
        $vulnProfile = $this->vulnerabilityService->calculate($validated, $membersData);

        return DB::transaction(function () use ($validated, $membersData, $vulnProfile, $rawNid, $nidHash, $rawMobile, $mobileHash, $code, $user) {
            $household = Household::create([
                'household_code' => $code,
                'administrative_unit_id' => $validated['administrative_unit_id'],
                'village_ward_name' => $validated['village_ward_name'] ?? null,
                'present_address' => $validated['present_address'],
                'permanent_address' => $validated['permanent_address'] ?? $validated['present_address'],
                'hoh_name_en' => $validated['hoh_name_en'],
                'hoh_name_bn' => $validated['hoh_name_bn'],
                'hoh_nid_hash' => $nidHash,
                'hoh_nid_encrypted' => Crypt::encryptString($rawNid),
                'hoh_nid_masked' => Household::maskNid($rawNid),
                'hoh_dob' => $validated['hoh_dob'] ?? null,
                'hoh_gender' => $validated['hoh_gender'],
                'hoh_mobile_hash' => $mobileHash,
                'hoh_mobile_encrypted' => $rawMobile ? Crypt::encryptString($rawMobile) : null,
                'hoh_mobile_masked' => Household::maskMobile($rawMobile),
                'hoh_occupation' => $validated['hoh_occupation'] ?? null,
                'monthly_income_bdt' => $validated['monthly_income_bdt'] ?? 0.00,
                'income_category' => $vulnProfile['income_category'],
                'family_member_count' => count($membersData) + 1,
                'vulnerability_score' => $vulnProfile['vulnerability_score'],
                'is_female_headed' => $vulnProfile['is_female_headed'],
                'has_elderly' => $vulnProfile['has_elderly'],
                'has_disabled' => $vulnProfile['has_disabled'],
                'has_children_infants' => $vulnProfile['has_children_infants'],
                'has_pregnant_lactating' => $vulnProfile['has_pregnant_lactating'],
                'is_displaced' => $vulnProfile['is_displaced'],
                'other_vulnerabilities' => $validated['other_vulnerabilities'] ?? null,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'verification_status' => 'DRAFT',
                'created_by' => $user->id,
            ]);

            // Save individual members
            foreach ($membersData as $m) {
                $mNid = !empty($m['nid']) ? trim($m['nid']) : null;
                HouseholdMember::create([
                    'household_id' => $household->id,
                    'full_name_en' => $m['full_name_en'],
                    'full_name_bn' => $m['full_name_bn'] ?? null,
                    'relationship_to_hoh' => $m['relationship_to_hoh'],
                    'gender' => $m['gender'],
                    'age' => $m['age'] ?? null,
                    'date_of_birth' => $m['date_of_birth'] ?? null,
                    'nid_hash' => $mNid ? Household::hashNid($mNid) : null,
                    'nid_encrypted' => $mNid ? Crypt::encryptString($mNid) : null,
                    'nid_masked' => $mNid ? Household::maskNid($mNid) : null,
                    'has_disability' => !empty($m['has_disability']),
                    'disability_type' => $m['disability_type'] ?? null,
                    'is_pregnant_lactating' => !empty($m['is_pregnant_lactating']),
                    'occupation' => $m['occupation'] ?? null,
                ]);
            }

            // Run duplicate suspect scan
            $suspects = $this->duplicateService->scan($household);

            AuditLog::log(
                'BENEFICIARY_REGISTERED',
                Household::class,
                $household->id,
                null,
                ['household_code' => $household->household_code, 'name' => $household->hoh_name_en],
                $user,
                $household->administrative_unit_id
            );

            return response()->json([
                'success' => true,
                'message' => 'Beneficiary household registered successfully.',
                'household' => new HouseholdDetailResource($household->fresh(['administrativeUnit', 'members'])),
                'duplicate_flags_detected' => $suspects->count(),
                'timestamp' => now()->toIso8601String(),
            ], 201);
        });
    }

    /**
     * Display a specific household's full profile.
     */
    public function show(Request $request, Household $household): JsonResponse
    {
        $user = $request->user();

        if ($user && !$user->can('view', $household)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. This household record is outside your administrative jurisdiction scope.',
            ], 403);
        }

        $household->load([
            'administrativeUnit',
            'members',
            'verifications.verifier',
            'disasterEligibilities.disasterEvent',
            'duplicateSuspects.matchedHousehold',
            'creator',
        ]);

        return response()->json([
            'success' => true,
            'household' => new HouseholdDetailResource($household),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Update an existing household record.
     */
    public function update(UpdateHouseholdRequest $request, Household $household): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $oldValues = $household->toArray();

        // Handle mobile update
        if (array_key_exists('hoh_mobile', $validated)) {
            $rawMobile = $validated['hoh_mobile'] ? trim($validated['hoh_mobile']) : null;
            $validated['hoh_mobile_hash'] = Household::hashMobile($rawMobile);
            $validated['hoh_mobile_encrypted'] = $rawMobile ? Crypt::encryptString($rawMobile) : null;
            $validated['hoh_mobile_masked'] = Household::maskMobile($rawMobile);
            unset($validated['hoh_mobile']);
        }

        $validated['updated_by'] = $user->id;

        $household->update($validated);

        // Recalculate vulnerability score
        $this->vulnerabilityService->recalculateForModel($household);

        // Re-scan duplicate suspects
        $this->duplicateService->scan($household);

        AuditLog::log(
            'BENEFICIARY_UPDATED',
            Household::class,
            $household->id,
            $oldValues,
            $household->fresh()->toArray(),
            $user,
            $household->administrative_unit_id
        );

        return response()->json([
            'success' => true,
            'message' => 'Beneficiary household updated successfully.',
            'household' => new HouseholdDetailResource($household->fresh(['administrativeUnit', 'members'])),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Soft delete an authorized household record.
     */
    public function destroy(Request $request, Household $household): JsonResponse
    {
        $user = $request->user();

        if (!$user->can('delete', $household)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Insufficient permissions to delete beneficiary record.',
            ], 403);
        }

        $oldValues = $household->toArray();
        $household->delete();

        AuditLog::log(
            'BENEFICIARY_DELETED',
            Household::class,
            $household->id,
            $oldValues,
            null,
            $user,
            $household->administrative_unit_id
        );

        return response()->json([
            'success' => true,
            'message' => 'Beneficiary household record soft-deleted successfully.',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Unmask and reveal full PII (Audited gated endpoint).
     */
    public function revealPii(Request $request, Household $household): JsonResponse
    {
        $user = $request->user();

        if (!$user->can('decryptPii', $household)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Decryption of sensitive PII is restricted to authorized statutory officers.',
            ], 403);
        }

        AuditLog::log(
            'PII_DECRYPTION_ACCESSED',
            Household::class,
            $household->id,
            null,
            ['action' => 'MANUAL_UNMASK_REVEALED', 'code' => $household->household_code],
            $user,
            $household->administrative_unit_id
        );

        $piiData = [
            'household_id' => $household->id,
            'household_code' => $household->household_code,
            'unmasked_nid' => $household->getDecryptedNid(),
            'unmasked_mobile' => $household->getDecryptedMobile(),
            'warning' => 'This action has been logged in the permanent national audit trail.',
        ];

        return response()->json([
            'success' => true,
            'data' => $piiData,
            'household_id' => $household->id,
            'household_code' => $household->household_code,
            'unmasked_nid' => $household->getDecryptedNid(),
            'unmasked_mobile' => $household->getDecryptedMobile(),
            'warning' => 'This action has been logged in the permanent national audit trail.',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
