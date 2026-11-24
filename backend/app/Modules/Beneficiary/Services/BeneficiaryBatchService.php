<?php

namespace App\Modules\Beneficiary\Services;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\AuditLog;
use App\Core\Models\User;
use App\Modules\Beneficiary\Models\Household;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Class BeneficiaryBatchService
 *
 * Handles chunked CSV/Excel imports, validation error logs, and export of muster rolls.
 *
 * @package App\Modules\Beneficiary\Services
 */
class BeneficiaryBatchService
{
    public function __construct(
        protected VulnerabilityCalculationService $vulnerabilityService,
        protected DuplicateDetectionService $duplicateService
    ) {}

    /**
     * Process an imported CSV string.
     *
     * @param string $csvContent
     * @param User $user
     * @return array
     */
    public function importCsv(string $csvContent, User $user): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($csvContent));
        if (empty($lines)) {
            return [
                'success' => false,
                'message' => 'Uploaded CSV file is empty.',
                'processed' => 0,
                'created' => 0,
                'errors' => [],
            ];
        }

        $header = str_getcsv(array_shift($lines));
        $header = array_map(fn($col) => strtolower(trim($col)), $header);

        $processed = 0;
        $created = 0;
        $errors = [];

        // Preload administrative units for quick mapping by code or name
        $adminUnits = AdministrativeUnit::where('is_active', true)->get();

        foreach ($lines as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            $processed++;
            $rowNumber = $index + 2; // Accounting for 1-based index and header
            $row = str_getcsv($line);

            if (count($row) !== count($header)) {
                $errors[] = [
                    'row' => $rowNumber,
                    'field' => 'general',
                    'message' => 'Column count does not match header definition.',
                ];
                continue;
            }

            $data = array_combine($header, $row);

            // Validation rules
            $nameEn = trim($data['hoh_name_en'] ?? $data['name_en'] ?? '');
            $nameBn = trim($data['hoh_name_bn'] ?? $data['name_bn'] ?? $nameEn);
            $rawNid = trim($data['nid'] ?? $data['hoh_nid'] ?? '');
            $mobile = trim($data['mobile'] ?? $data['hoh_mobile'] ?? '');
            $adminCode = trim($data['admin_code'] ?? $data['union_code'] ?? '');
            $gender = strtoupper(trim($data['gender'] ?? $data['hoh_gender'] ?? 'MALE'));
            $income = (float) ($data['income'] ?? $data['monthly_income'] ?? 0);

            if (!$nameEn) {
                $errors[] = ['row' => $rowNumber, 'field' => 'hoh_name_en', 'message' => 'Head of Household English name is required.'];
                continue;
            }

            if (!preg_match('/^(?:\d{10}|\d{13}|\d{17})$/', $rawNid)) {
                $errors[] = ['row' => $rowNumber, 'field' => 'nid', 'message' => "Invalid National ID format: '{$rawNid}'. Must be 10, 13, or 17 digits."];
                continue;
            }

            $nidHash = Household::hashNid($rawNid);
            if (Household::where('hoh_nid_hash', $nidHash)->exists()) {
                $errors[] = ['row' => $rowNumber, 'field' => 'nid', 'message' => "Duplicate National ID detected in master registry: '{$rawNid}'."];
                continue;
            }

            // Find Administrative Unit
            $adminUnit = null;
            if ($adminCode) {
                $adminUnit = $adminUnits->firstWhere('code', $adminCode);
            }
            if (!$adminUnit && !empty($data['admin_id'])) {
                $adminUnit = $adminUnits->firstWhere('id', $data['admin_id']);
            }
            if (!$adminUnit && $user->administrative_unit_id) {
                $adminUnit = $adminUnits->firstWhere('id', $user->administrative_unit_id);
            }
            if (!$adminUnit) {
                // Fallback to first available Union or District
                $adminUnit = $adminUnits->firstWhere('tier_level', 'UNION') ?? $adminUnits->first();
            }

            // Calculate vulnerability
            $vulnProfile = $this->vulnerabilityService->calculate([
                'hoh_gender' => $gender,
                'monthly_income_bdt' => $income,
                'is_displaced' => !empty($data['is_displaced']),
                'has_disabled' => !empty($data['has_disabled']),
                'has_elderly' => !empty($data['has_elderly']),
            ]);

            try {
                $code = 'HH-' . ($adminUnit ? $adminUnit->code : '00') . '-' . strtoupper(Str::random(6));

                $household = Household::create([
                    'household_code' => $code,
                    'administrative_unit_id' => $adminUnit->id,
                    'village_ward_name' => $data['village'] ?? $data['village_ward_name'] ?? 'Ward 01',
                    'present_address' => $data['present_address'] ?? 'Village',
                    'permanent_address' => $data['permanent_address'] ?? 'Village',
                    'hoh_name_en' => $nameEn,
                    'hoh_name_bn' => $nameBn,
                    'hoh_nid_hash' => $nidHash,
                    'hoh_nid_encrypted' => Crypt::encryptString($rawNid),
                    'hoh_nid_masked' => Household::maskNid($rawNid),
                    'hoh_dob' => !empty($data['dob']) ? $data['dob'] : null,
                    'hoh_gender' => in_array($gender, ['MALE', 'FEMALE', 'OTHER']) ? $gender : 'MALE',
                    'hoh_mobile_hash' => Household::hashMobile($mobile),
                    'hoh_mobile_encrypted' => $mobile ? Crypt::encryptString($mobile) : null,
                    'hoh_mobile_masked' => Household::maskMobile($mobile),
                    'monthly_income_bdt' => $income,
                    'income_category' => $vulnProfile['income_category'],
                    'family_member_count' => (int) ($data['member_count'] ?? 1),
                    'vulnerability_score' => $vulnProfile['vulnerability_score'],
                    'is_female_headed' => $vulnProfile['is_female_headed'],
                    'has_elderly' => $vulnProfile['has_elderly'],
                    'has_disabled' => $vulnProfile['has_disabled'],
                    'has_children_infants' => $vulnProfile['has_children_infants'],
                    'has_pregnant_lactating' => $vulnProfile['has_pregnant_lactating'],
                    'is_displaced' => $vulnProfile['is_displaced'],
                    'verification_status' => 'PENDING_VERIFICATION',
                    'created_by' => $user->id,
                ]);

                // Scan duplicates
                $this->duplicateService->scan($household);

                $created++;
            } catch (\Throwable $e) {
                $errors[] = [
                    'row' => $rowNumber,
                    'field' => 'database',
                    'message' => 'Persistence error: ' . $e->getMessage(),
                ];
            }
        }

        AuditLog::log(
            'BENEFICIARIES_BATCH_IMPORTED',
            Household::class,
            null,
            null,
            ['processed' => $processed, 'created' => $created, 'errors_count' => count($errors)],
            $user
        );

        return [
            'success' => true,
            'processed' => $processed,
            'created' => $created,
            'failed' => count($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Export muster roll records as CSV string.
     *
     * @param mixed $households Query or collection
     * @param bool $decryptPii
     * @return string
     */
    public function exportCsv($households, bool $decryptPii = false): string
    {
        $csv = [];
        $csv[] = implode(',', [
            'Household Code',
            'Name (English)',
            'Name (Bangla)',
            'National ID (NID)',
            'Mobile Number',
            'Gender',
            'Jurisdiction',
            'Village/Ward',
            'Vulnerability Score',
            'Status',
            'Displaced',
        ]);

        foreach ($households as $hh) {
            $nid = $decryptPii ? ($hh->getDecryptedNid() ?? $hh->hoh_nid_masked) : $hh->hoh_nid_masked;
            $mobile = $decryptPii ? ($hh->getDecryptedMobile() ?? $hh->hoh_mobile_masked) : $hh->hoh_mobile_masked;

            $csv[] = implode(',', array_map(function ($val) {
                return '"' . str_replace('"', '""', (string) $val) . '"';
            }, [
                $hh->household_code,
                $hh->hoh_name_en,
                $hh->hoh_name_bn,
                $nid,
                $mobile,
                $hh->hoh_gender,
                $hh->administrativeUnit?->name_en ?? 'N/A',
                $hh->village_ward_name,
                $hh->vulnerability_score,
                $hh->verification_status,
                $hh->is_displaced ? 'YES' : 'NO',
            ]));
        }

        return implode("\n", $csv);
    }
}
