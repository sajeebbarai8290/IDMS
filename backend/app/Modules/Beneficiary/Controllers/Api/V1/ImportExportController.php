<?php

namespace App\Modules\Beneficiary\Controllers\Api\V1;

use App\Modules\Beneficiary\Models\Household;
use App\Modules\Beneficiary\Services\BeneficiaryBatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

class ImportExportController extends Controller
{
    public function __construct(
        protected BeneficiaryBatchService $batchService
    ) {}

    /**
     * Import beneficiaries from uploaded CSV file or raw payload.
     */
    public function import(Request $request): JsonResponse
    {
        $user = $request->user();

        $content = '';
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $content = file_get_contents($file->getRealPath());
        } elseif ($request->filled('csv_data')) {
            $content = $request->input('csv_data');
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Please provide a valid CSV file upload or csv_data parameter.',
            ], 422);
        }

        $result = $this->batchService->importCsv($content, $user);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Export authorized beneficiary records as a CSV download.
     */
    public function export(Request $request): Response
    {
        $user = $request->user();

        $query = Household::with(['administrativeUnit'])
            ->inJurisdiction($user);

        if ($status = $request->query('verification_status')) {
            $query->where('verification_status', $status);
        }

        if ($adminUnitId = $request->query('administrative_unit_id')) {
            $query->where('administrative_unit_id', $adminUnitId);
        }

        $households = $query->limit(5000)->get();

        $decryptPii = $request->boolean('decrypt_pii') && $user->can('decryptPii', Household::class);
        $csvData = $this->batchService->exportCsv($households, $decryptPii);

        $filename = 'idms_beneficiaries_' . now()->format('Ymd_His') . '.csv';

        return response($csvData, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Download standard CSV import template.
     */
    public function template(): Response
    {
        $headers = [
            'hoh_name_en',
            'hoh_name_bn',
            'nid',
            'mobile',
            'gender',
            'dob',
            'admin_code',
            'village',
            'present_address',
            'income',
            'member_count',
            'has_elderly',
            'has_disabled',
            'is_displaced',
        ];

        $sampleRow = [
            'Md. Karim Ullah',
            'মোঃ করিম উল্লাহ',
            '1985471234567',
            '01712345678',
            'MALE',
            '1985-06-15',
            '35',
            'Chalna Bazar',
            'Ward 02, Chalna',
            '4500',
            '4',
            '1',
            '0',
            '1',
        ];

        $csv = implode(',', $headers) . "\n" . implode(',', $sampleRow);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="idms_beneficiary_import_template.csv"',
        ]);
    }
}
