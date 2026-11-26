<?php

namespace Database\Seeders;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Modules\Disaster\Models\DisasterEvent;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectCategory;
use App\Modules\Project\Models\ProjectDisbursement;
use App\Modules\Project\Models\ProjectLocation;
use App\Modules\Project\Models\ProjectMilestone;
use App\Modules\Project\Models\ProjectMonitoringReport;
use App\Modules\Project\Models\ProjectRisk;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'code' => 'DEVELOPMENT',
                'name_en' => 'Development Project',
                'name_bn' => 'উন্নয়ন প্রকল্প',
                'description' => 'Long-term disaster-resilient infrastructure and capacity development projects.',
                'color_hex' => '#0d6efd',
                'is_system' => true,
            ],
            [
                'code' => 'DRR',
                'name_en' => 'Disaster Risk Reduction (DRR)',
                'name_bn' => 'দুর্যোগ ঝুঁকি হ্রাস (ডিআরআর)',
                'description' => 'Proactive mitigation, early-warning infrastructure, and community resilience schemes.',
                'color_hex' => '#198754',
                'is_system' => true,
            ],
            [
                'code' => 'REHABILITATION',
                'name_en' => 'Post-Disaster Rehabilitation',
                'name_bn' => 'দুর্যোগ-পরবর্তী পুনর্বাসন',
                'description' => 'Restoration of damaged housing, embankments, rural roads, and public utilities.',
                'color_hex' => '#fd7e14',
                'is_system' => true,
            ],
            [
                'code' => 'RECOVERY',
                'name_en' => 'Recovery & Livelihood Initiative',
                'name_bn' => 'পুনরুদ্ধার ও জীবিকা উদ্যোগ',
                'description' => 'Socio-economic recovery, cash-for-work, and community asset restoration.',
                'color_hex' => '#6f42c1',
                'is_system' => true,
            ],
            [
                'code' => 'TR_KABITA',
                'name_en' => 'TR / KABITA Scheme',
                'name_bn' => 'টিআর / কাবিটা কর্মসূচি',
                'description' => 'Test Relief (TR) and Work for Money (KABITA) rural resilience schemes.',
                'color_hex' => '#0dcaf0',
                'is_system' => true,
            ],
            [
                'code' => 'SHELTER_INFRA',
                'name_en' => 'Cyclone & Flood Shelter Construction',
                'name_bn' => 'ঘূর্ণিঝড় ও বন্যা আশ্রয়কেন্দ্র নির্মাণ',
                'description' => 'Construction and retrofitting of multi-purpose disaster shelters.',
                'color_hex' => '#20c997',
                'is_system' => true,
            ],
            [
                'code' => 'EMBANKMENT',
                'name_en' => 'Embankment & Polder Protection',
                'name_bn' => 'বেড়িবাঁধ ও পোল্ডার সংরক্ষণ',
                'description' => 'Coastal polder strengthening, river erosion protection, and sluice gate repair.',
                'color_hex' => '#d63384',
                'is_system' => true,
            ],
        ];

        foreach ($categories as $cat) {
            ProjectCategory::firstOrCreate(
                ['code' => $cat['code']],
                array_merge($cat, ['is_active' => true])
            );
        }

        $dacope = AdministrativeUnit::where('code', '12')->first();
        $khulna = AdministrativeUnit::where('code', '47')->first();
        $barishal = AdministrativeUnit::where('code', '10')->first();

        if (!$dacope && !$khulna && !$barishal) {
            return;
        }

        $planner = User::first() ?? User::create([
            'keycloak_sub' => '00000000-0000-4000-8000-000000000601',
            'email' => 'planning.director@modmr.gov.bd',
            'full_name_en' => 'Director (Planning), DDM',
            'full_name_bn' => 'পরিচালক (পরিকল্পনা), ডিডিএম',
            'role_code' => 'ROLE_DDM_PLANNING_OFFICER',
            'administrative_unit_id' => $khulna?->id ?? $dacope?->id ?? $barishal?->id,
            'designation' => 'Director (Planning)',
            'is_active' => true,
        ]);

        $rehabCat = ProjectCategory::where('code', 'REHABILITATION')->firstOrFail();
        $drrCat = ProjectCategory::where('code', 'DRR')->firstOrFail();
        $remal = DisasterEvent::where('disaster_code', 'CYC-REMAL-2024')->first();

        $primaryUnit = $dacope ?? $khulna ?? $barishal;

        $proj1 = Project::firstOrCreate(
            ['project_code' => 'PRJ-MODMR-2026-001'],
            [
                'category_id' => $rehabCat->id,
                'disaster_event_id' => $remal?->id,
                'administrative_unit_id' => $primaryUnit->id,
                'reference_no' => 'MoDMR/REHAB/2026/47-12/01',
                'title_en' => 'Dacope Polder-31 Embankment & Rural Access Rehabilitation',
                'title_bn' => 'দাকোপ পোল্ডার-৩১ বেড়িবাঁধ ও গ্রামীণ সড়ক পুনর্বাসন প্রকল্প',
                'description' => 'Emergency and medium-term rehabilitation of storm-surge damaged embankment stretches and evacuation feeder roads in Dacope Upazila.',
                'objectives' => 'Restore 14.5 km of coastal embankment protection and elevate 6 km of cyclone shelter access roads.',
                'implementing_organization' => 'Department of Disaster Management (DDM) & LGED',
                'project_manager_name' => 'Engr. Mahmudul Hasan (PIO Dacope)',
                'project_manager_contact' => '+8801711000601',
                'funding_source' => 'GoB / MoDMR Rehabilitation Fund',
                'approved_budget_bdt' => 45000000.00,
                'revised_budget_bdt' => 48000000.00,
                'released_funds_bdt' => 24000000.00,
                'expenditure_bdt' => 16800000.00,
                'physical_progress_pct' => 42.50,
                'financial_progress_pct' => 35.00,
                'start_date' => '2026-01-15',
                'expected_end_date' => '2026-12-31',
                'ward_no' => 'Ward 03, Ward 05',
                'location_summary' => 'Sutarkhali & Chalna Unions, Dacope, Khulna',
                'latitude' => 22.5721000,
                'longitude' => 89.5106000,
                'boundary_geojson' => [
                    'type' => 'Polygon',
                    'coordinates' => [[[89.44, 22.41], [89.55, 22.41], [89.55, 22.61], [89.44, 22.61], [89.44, 22.41]]],
                ],
                'status' => Project::STATUS_ONGOING,
                'status_notes' => 'Earthwork and geo-bag protection actively progressing.',
                'is_delayed' => false,
                'delay_days' => 0,
                'created_by' => $planner->id,
                'updated_by' => $planner->id,
                'approved_by' => $planner->id,
                'approved_at' => now()->subDays(45),
            ]
        );

        if ($proj1->wasRecentlyCreated) {
            ProjectLocation::create([
                'project_id' => $proj1->id,
                'administrative_unit_id' => $primaryUnit->id,
                'ward_no' => 'Ward 03',
                'site_name_en' => 'Sutarkhali Breach Segment A',
                'site_name_bn' => 'সুতারখালী ভাঙন অংশ-ক',
                'latitude' => 22.4219000,
                'longitude' => 89.4511000,
            ]);

            $m1 = ProjectMilestone::create([
                'project_id' => $proj1->id,
                'wbs_code' => '1.1',
                'milestone_title' => 'Hydrographic Survey & Geo-Bag Mobilization',
                'milestone_title_bn' => 'জরিপ ও জিও-ব্যাগ সংগ্রহ',
                'item_type' => ProjectMilestone::TYPE_ACTIVITY,
                'deliverables' => 'Approved survey drawings and 25,000 geo-bags at site',
                'responsible_organization' => 'Upazila PIO Office & BWDB',
                'assigned_officer_name' => 'Sub-Assistant Engineer Karim',
                'planned_start_date' => '2026-01-15',
                'planned_end_date' => '2026-03-30',
                'actual_start_date' => '2026-01-18',
                'actual_end_date' => '2026-03-28',
                'weight_percentage' => 25.00,
                'target_percentage' => 100.00,
                'completion_percentage' => 100.00,
                'approval_status' => ProjectMilestone::APPROVAL_APPROVED_PIO,
                'certified_by' => $planner->id,
                'certified_at' => now()->subDays(30),
            ]);

            ProjectMilestone::create([
                'project_id' => $proj1->id,
                'parent_milestone_id' => $m1->id,
                'wbs_code' => '1.2',
                'milestone_title' => 'Embankment Slope Compaction & Brick Herringbone Road',
                'milestone_title_bn' => 'বাঁধের ঢাল কম্প্যাকশন ও এইচবিবি সড়ক নির্মাণ',
                'item_type' => ProjectMilestone::TYPE_MILESTONE,
                'deliverables' => '8.5 km compacted embankment and 3 km HBB feeder road',
                'responsible_organization' => 'LGED Dacope',
                'assigned_officer_name' => 'Engr. Mahmudul Hasan',
                'planned_start_date' => '2026-04-01',
                'planned_end_date' => '2026-10-30',
                'actual_start_date' => '2026-04-05',
                'weight_percentage' => 75.00,
                'target_percentage' => 100.00,
                'completion_percentage' => 23.33,
                'approval_status' => ProjectMilestone::APPROVAL_SUBMITTED,
            ]);

            ProjectDisbursement::create([
                'project_id' => $proj1->id,
                'project_milestone_id' => $m1->id,
                'record_type' => ProjectDisbursement::TYPE_FUND_RELEASE,
                'voucher_no' => 'REL-2026-001-01',
                'fiscal_year' => '2025-2026',
                'amount_bdt' => 24000000.00,
                'payment_status' => ProjectDisbursement::STATUS_RELEASED,
                'description' => 'First tranche fund release from MoDMR rehabilitation allocation.',
                'disbursed_at' => now()->subDays(40),
                'created_by' => $planner->id,
                'approved_by' => $planner->id,
                'approved_at' => now()->subDays(39),
            ]);

            ProjectDisbursement::create([
                'project_id' => $proj1->id,
                'project_milestone_id' => $m1->id,
                'record_type' => ProjectDisbursement::TYPE_EXPENDITURE,
                'voucher_no' => 'EXP-2026-001-01',
                'fiscal_year' => '2025-2026',
                'amount_bdt' => 16800000.00,
                'payment_status' => ProjectDisbursement::STATUS_RECONCILED,
                'description' => 'Verified geo-bag procurement and initial earthwork RA Bill-1.',
                'disbursed_at' => now()->subDays(25),
                'created_by' => $planner->id,
                'approved_by' => $planner->id,
                'approved_at' => now()->subDays(24),
            ]);

            ProjectMonitoringReport::create([
                'project_id' => $proj1->id,
                'administrative_unit_id' => $primaryUnit->id,
                'report_no' => 'MON-2026-001-01',
                'visit_date' => now()->subDays(10)->toDateString(),
                'inspector_name' => 'DRRO Khulna Inspection Team',
                'inspector_designation' => 'District Relief & Rehabilitation Officer',
                'inspector_user_id' => $planner->id,
                'observed_physical_pct' => 42.50,
                'observed_financial_pct' => 35.00,
                'delay_observed' => false,
                'delay_days' => 0,
                'findings' => 'Geo-bag placement matches engineering specifications; compaction testing verified.',
                'recommendations' => 'Accelerate HBB brick laying prior to peak monsoon.',
                'latitude' => 22.5721000,
                'longitude' => 89.5106000,
            ]);

            ProjectRisk::create([
                'project_id' => $proj1->id,
                'risk_code' => 'RSK-001',
                'record_kind' => ProjectRisk::KIND_RISK,
                'title' => 'Early monsoon tidal surge affecting compaction works',
                'description' => 'High spring tides may temporarily halt toe-protection works along Sutarkhali.',
                'severity' => ProjectRisk::SEVERITY_HIGH,
                'likelihood' => 'MEDIUM',
                'mitigation_plan' => 'Deploy temporary cofferdam barriers and double shift labor during neap tide windows.',
                'owner_name' => 'Engr. Mahmudul Hasan',
                'status' => ProjectRisk::STATUS_MITIGATING,
                'identified_date' => now()->subDays(20)->toDateString(),
                'created_by' => $planner->id,
            ]);
        }

        if ($barishal) {
            Project::firstOrCreate(
                ['project_code' => 'PRJ-MODMR-2026-002'],
                [
                    'category_id' => $drrCat->id,
                    'disaster_event_id' => null,
                    'administrative_unit_id' => $barishal->id,
                    'reference_no' => 'MoDMR/DRR/2026/10/02',
                    'title_en' => 'Barishal Multi-Purpose Flood & Cyclone Resilience Center',
                    'title_bn' => 'বরিশাল বহুমুখী বন্যা ও ঘূর্ণিঝড় সহনশীল আশ্রয়কেন্দ্র',
                    'description' => 'Standalone DRR preparedness initiative constructing elevated multi-purpose shelter capacity.',
                    'objectives' => 'Increase safe evacuation capacity for 1,800 residents and solar-powered emergency communications.',
                    'implementing_organization' => 'Department of Disaster Management (DDM)',
                    'project_manager_name' => 'DRRO Barishal',
                    'project_manager_contact' => '+8801711000602',
                    'funding_source' => 'GoB Annual Development Programme (ADP)',
                    'approved_budget_bdt' => 32000000.00,
                    'released_funds_bdt' => 0.00,
                    'expenditure_bdt' => 0.00,
                    'physical_progress_pct' => 0.00,
                    'financial_progress_pct' => 0.00,
                    'start_date' => '2026-07-01',
                    'expected_end_date' => '2027-06-30',
                    'ward_no' => 'Ward 01',
                    'location_summary' => 'Barishal Coastal Cluster',
                    'latitude' => 22.7010000,
                    'longitude' => 90.3535000,
                    'status' => Project::STATUS_APPROVED,
                    'status_notes' => 'Approved under FY 2026-27 ADP DRR portfolio.',
                    'is_delayed' => false,
                    'delay_days' => 0,
                    'created_by' => $planner->id,
                    'approved_by' => $planner->id,
                    'approved_at' => now()->subDays(10),
                ]
            );
        }
    }
}
