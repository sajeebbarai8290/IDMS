<?php

namespace Database\Seeders;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Modules\Disaster\Models\DisasterEvent;
use App\Modules\Resource\Models\HumanResourceDeployment;
use App\Modules\Resource\Models\ResourceAllocation;
use App\Modules\Resource\Models\ResourceAsset;
use App\Modules\Resource\Models\ResourceCategory;
use App\Modules\Resource\Models\ResourceMaintenance;
use App\Modules\Response\Models\ResponseCenter;
use App\Modules\Response\Models\ResponseOperation;
use App\Modules\Response\Models\ResponseTeam;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ResourceSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'code' => 'VEHICLES',
                'name_en' => 'Vehicles',
                'name_bn' => 'যানবাহন',
                'description' => '4WD pickups, relief trucks, ambulances, and command vehicles.',
                'icon' => 'truck',
                'color_hex' => '#0d6efd',
                'is_human_resource' => false,
                'is_system' => true,
            ],
            [
                'code' => 'BOATS',
                'name_en' => 'Boats',
                'name_bn' => 'নৌযান ও বোট',
                'description' => 'High-speed fiberglass rescue boats, engine boats, and amphibious craft.',
                'icon' => 'water',
                'color_hex' => '#0284c7',
                'is_human_resource' => false,
                'is_system' => true,
            ],
            [
                'code' => 'RESCUE_EQUIPMENT',
                'name_en' => 'Rescue Equipment',
                'name_bn' => 'উদ্ধার সরঞ্জাম',
                'description' => 'Life jackets, thermal cameras, hydraulic cutters, chainsaws, and rope kits.',
                'icon' => 'life-preserver',
                'color_hex' => '#dc2626',
                'is_human_resource' => false,
                'is_system' => true,
            ],
            [
                'code' => 'MEDICAL_EQUIPMENT',
                'name_en' => 'Medical Equipment',
                'name_bn' => 'চিকিৎসা সরঞ্জাম',
                'description' => 'Field triage kits, oxygen concentrators, stretchers, and mobile clinics.',
                'icon' => 'heart-pulse',
                'color_hex' => '#e11d48',
                'is_human_resource' => false,
                'is_system' => true,
            ],
            [
                'code' => 'COMMUNICATION_EQUIPMENT',
                'name_en' => 'Communication Equipment',
                'name_bn' => 'যোগাযোগ সরঞ্জাম',
                'description' => 'VHF/HF radios, satellite phones, portable repeaters, and drone relays.',
                'icon' => 'broadcast',
                'color_hex' => '#7c3aed',
                'is_human_resource' => false,
                'is_system' => true,
            ],
            [
                'code' => 'HEAVY_MACHINERY',
                'name_en' => 'Heavy Machinery',
                'name_bn' => 'ভারী যন্ত্রপাতি',
                'description' => 'Excavators, bulldozers, cranes, dewatering pumps, and heavy generators.',
                'icon' => 'gear-wide-connected',
                'color_hex' => '#d97706',
                'is_human_resource' => false,
                'is_system' => true,
            ],
            [
                'code' => 'EMERGENCY_RESPONSE_EQUIPMENT',
                'name_en' => 'Emergency Response Equipment',
                'name_bn' => 'জরুরি সাড়াদান সরঞ্জাম',
                'description' => 'Water purification plants, floodlights, emergency tents, and solar kits.',
                'icon' => 'shield-exclamation',
                'color_hex' => '#16a34a',
                'is_human_resource' => false,
                'is_system' => true,
            ],
            [
                'code' => 'HUMAN_RESOURCES',
                'name_en' => 'Human Resources',
                'name_bn' => 'মানবসম্পদ ও বিশেষজ্ঞ দল',
                'description' => 'Specialist SAR operators, medical officers, engineers, and rapid response staff.',
                'icon' => 'people-fill',
                'color_hex' => '#0f766e',
                'is_human_resource' => true,
                'is_system' => true,
            ],
        ];

        $catByCode = [];
        foreach ($categories as $catDef) {
            $catByCode[$catDef['code']] = ResourceCategory::updateOrCreate(
                ['code' => $catDef['code']],
                array_merge($catDef, ['is_active' => true])
            );
        }

        // Backfill category & metadata on ResponseCatalogSeeder assets if already seeded
        $backfillMap = [
            'AST-BOAT-KHL-01' => [
                'category_id' => $catByCode['BOATS']->id,
                'resource_type' => 'FIBERGLASS_SPEEDBOAT',
                'owning_organization' => 'Department of Disaster Management (DDM - Khulna)',
                'current_location' => 'Khulna District EOC Boat Yard',
                'responsible_officer_name' => 'Md. Kamrul Hasan (DRRO Office)',
                'responsible_officer_contact' => '+8801711-000101',
                'acquisition_date' => '2024-03-15',
                'acquisition_value_bdt' => 2850000.00,
                'next_maintenance_due_at' => now()->addDays(12)->toDateString(),
            ],
            'AST-4WD-KHL-02' => [
                'category_id' => $catByCode['VEHICLES']->id,
                'resource_type' => '4WD_PICKUP',
                'owning_organization' => 'District Administration, Khulna',
                'current_location' => 'DC Office Pool, Khulna',
                'responsible_officer_name' => 'Sohel Rana (Transport Supervisor)',
                'responsible_officer_contact' => '+8801711-000102',
                'acquisition_date' => '2023-11-10',
                'acquisition_value_bdt' => 6200000.00,
                'next_maintenance_due_at' => now()->addDays(5)->toDateString(),
            ],
            'AST-AMB-DAC-01' => [
                'category_id' => $catByCode['VEHICLES']->id,
                'resource_type' => 'EMERGENCY_AMBULANCE',
                'owning_organization' => 'Upazila Health & DMC Dacope',
                'current_location' => 'Dacope Upazila Health Complex',
                'responsible_officer_name' => 'Dr. Farhana Yasmin',
                'responsible_officer_contact' => '+8801711-000103',
                'acquisition_date' => '2024-01-20',
                'acquisition_value_bdt' => 4500000.00,
                'next_maintenance_due_at' => now()->addDays(25)->toDateString(),
            ],
            'AST-WTP-DAC-01' => [
                'category_id' => $catByCode['EMERGENCY_RESPONSE_EQUIPMENT']->id,
                'resource_type' => 'MOBILE_RO_PLANT',
                'owning_organization' => 'DPHE / MoDMR Joint Rapid Unit',
                'current_location' => 'Dacope Upazila Command Post',
                'responsible_officer_name' => 'Engr. Tariqul Islam',
                'responsible_officer_contact' => '+8801711-000104',
                'acquisition_date' => '2024-05-02',
                'acquisition_value_bdt' => 1950000.00,
                'next_maintenance_due_at' => now()->subDays(2)->toDateString(), // overdue reminder demo
            ],
        ];

        foreach ($backfillMap as $code => $fields) {
            if ($existing = ResourceAsset::where('asset_code', $code)->first()) {
                $existing->update($fields);
                $this->syncAssetPoint($existing);
            }
        }

        $khulna = AdministrativeUnit::where('code', '47')->first();
        $dacope = AdministrativeUnit::where('code', '12')->first();
        $barishal = AdministrativeUnit::where('code', '10')->first();

        if (!$khulna && !$dacope) {
            return;
        }

        $actor = User::first() ?? User::create([
            'keycloak_sub' => '00000000-0000-4000-8000-000000000701',
            'email' => 'resource.drro.khulna@modmr.gov.bd',
            'full_name_en' => 'DRRO Khulna (Resource Custodian)',
            'full_name_bn' => 'ডিআরআরও খুলনা (সম্পদ তত্ত্বাবধায়ক)',
            'role_code' => 'ROLE_DRRO',
            'administrative_unit_id' => ($khulna ?? $dacope)->id,
            'designation' => 'DRRO',
            'is_active' => true,
        ]);

        $newAssets = array_filter([
            $khulna ? [
                'asset_code' => 'RES-COM-KHL-01',
                'category_id' => $catByCode['COMMUNICATION_EQUIPMENT']->id,
                'administrative_unit_id' => $khulna->id,
                'asset_type' => 'VHF_BASE_REPEATER',
                'resource_type' => 'VHF_EMERGENCY_REPEATER',
                'name_en' => 'Portable VHF/HF Emergency Repeater Kit',
                'name_bn' => 'পোর্টেবল ভিএইচএফ/এইচএফ জরুরি রিপিটার কিট',
                'description' => 'Solar-backed dual-band emergency communication repeater for blackout zones.',
                'registration_no' => 'BTRC-EMRG-4701',
                'capacity_info' => '45 km coastal range / 72h battery',
                'specifications' => ['band' => 'VHF/UHF', 'power_watts' => 50, 'battery_backup_hours' => 72],
                'owning_organization' => 'NEOC / Khulna District Control Room',
                'current_location' => 'Khulna District EOC',
                'responsible_officer_name' => 'Md. Jahidul Alam',
                'responsible_officer_contact' => '+8801711-000201',
                'acquisition_date' => '2025-02-14',
                'acquisition_value_bdt' => 840000.00,
                'next_maintenance_due_at' => now()->addDays(6)->toDateString(),
                'status' => ResourceAsset::STATUS_AVAILABLE,
                'latitude' => 22.8162,
                'longitude' => 89.5675,
                'created_by' => $actor->id,
            ] : null,
            $dacope ? [
                'asset_code' => 'RES-HVM-DAC-01',
                'category_id' => $catByCode['HEAVY_MACHINERY']->id,
                'administrative_unit_id' => $dacope->id,
                'asset_type' => 'DEWATERING_PUMP',
                'resource_type' => 'HIGH_CAPACITY_PUMP',
                'name_en' => 'Heavy Diesel Dewatering & Flood Pump (50 HP)',
                'name_bn' => 'ভারী ডিজেল পানি নিষ্কাশন পাম্প (৫০ হর্সপাওয়ার)',
                'description' => 'Trailer-mounted dewatering pump for embankment breach & waterlogging response.',
                'registration_no' => 'BWDB-PMP-1204',
                'capacity_info' => '1,200 m3/hour discharge',
                'specifications' => ['horsepower' => 50, 'fuel' => 'Diesel', 'discharge_m3_hr' => 1200],
                'owning_organization' => 'Dacope Upazila Parishad / PIO Office',
                'current_location' => 'Chalna Godown Yard, Dacope',
                'responsible_officer_name' => 'Sub-Asst. Engr. Masud Rana',
                'responsible_officer_contact' => '+8801711-000202',
                'acquisition_date' => '2024-08-19',
                'acquisition_value_bdt' => 1650000.00,
                'next_maintenance_due_at' => now()->addDays(20)->toDateString(),
                'status' => ResourceAsset::STATUS_AVAILABLE,
                'latitude' => 22.5738,
                'longitude' => 89.5119,
                'created_by' => $actor->id,
            ] : null,
            $khulna ? [
                'asset_code' => 'RES-HR-KHL-01',
                'category_id' => $catByCode['HUMAN_RESOURCES']->id,
                'administrative_unit_id' => $khulna->id,
                'asset_type' => 'SPECIALIST_SAR_PERSONNEL',
                'resource_type' => 'SWIFT_WATER_SAR_SPECIALIST',
                'name_en' => 'Engr. Mahbubur Rahman — Swift-Water Rescue Specialist',
                'name_bn' => 'প্রকৌশলী মাহবুবুর রহমান — দ্রুত-জল উদ্ধার বিশেষজ্ঞ',
                'description' => 'Certified swift-water rescue diver and boat coxswain instructor.',
                'registration_no' => 'HR-SAR-4711',
                'capacity_info' => 'Certified Level-3 SAR / First Aid',
                'specifications' => ['certifications' => ['Swift-Water SAR', 'Trauma First Aid'], 'clearance' => '24/7 Rapid Callout'],
                'owning_organization' => 'DDM Regional Rapid Response Pool, Khulna',
                'current_location' => 'Khulna Regional Training & EOC Hub',
                'responsible_officer_name' => 'Engr. Mahbubur Rahman',
                'responsible_officer_contact' => '+8801711-000203',
                'acquisition_date' => '2023-06-01',
                'acquisition_value_bdt' => 0.00,
                'status' => ResourceAsset::STATUS_AVAILABLE,
                'latitude' => 22.8140,
                'longitude' => 89.5648,
                'created_by' => $actor->id,
            ] : null,
            $barishal ? [
                'asset_code' => 'RES-MED-BAR-01',
                'category_id' => $catByCode['MEDICAL_EQUIPMENT']->id,
                'administrative_unit_id' => $barishal->id,
                'asset_type' => 'MOBILE_TRIAGE_UNIT',
                'resource_type' => 'EMERGENCY_MEDICAL_KIT',
                'name_en' => 'Barishal Field Trauma & Triage Kit',
                'name_bn' => 'বরিশাল ফিল্ড ট্রমা ও ট্রায়াজ কিট',
                'description' => 'Portable trauma stabilization kit for 100 patients.',
                'registration_no' => 'DGHS-BAR-901',
                'capacity_info' => '100-patient capacity',
                'owning_organization' => 'Barishal District Health & DMC',
                'current_location' => 'Barishal District EOC',
                'responsible_officer_name' => 'Dr. Nabil Chowdhury',
                'responsible_officer_contact' => '+8801711-000204',
                'acquisition_date' => '2025-01-10',
                'acquisition_value_bdt' => 720000.00,
                'status' => ResourceAsset::STATUS_AVAILABLE,
                'latitude' => 22.7010,
                'longitude' => 90.3535,
                'created_by' => $actor->id,
            ] : null,
        ]);

        foreach ($newAssets as $assetDef) {
            $asset = ResourceAsset::updateOrCreate(
                ['asset_code' => $assetDef['asset_code']],
                array_merge($assetDef, ['is_active' => true])
            );
            $this->syncAssetPoint($asset);
        }

        // Seed sample Response Operations & Team if none exist yet so Resource & HR dropdowns are populated
        $disaster = DisasterEvent::where('disaster_code', 'CYC-REMAL-2024')->first() ?? DisasterEvent::first();
        $dacopeOp = ResponseOperation::first();

        if (!$dacopeOp && $disaster && ($dacope || $khulna)) {
            $dacopeCenter = ResponseCenter::where('center_code', 'EOC-DAC-01')->first();
            $khulnaCenter = ResponseCenter::where('center_code', 'EOC-KHL-01')->first();

            if ($dacope) {
                $dacopeOp = ResponseOperation::create([
                    'disaster_event_id' => $disaster->id,
                    'administrative_unit_id' => $dacope->id,
                    'response_center_id' => $dacopeCenter?->id,
                    'operation_no' => 'OP-REMAL-DAC-01',
                    'title_en' => 'Cyclone Remal Dacope Coastal SAR & Relief Support',
                    'title_bn' => 'ঘূর্ণিঝড় রেমাল দাকোপ উপকূলীয় উদ্ধার ও ত্রাণ সহায়তা অভিযান',
                    'objectives' => 'Coordinate coastal boat evacuation, dewatering, and emergency shelter support.',
                    'priority' => 'CRITICAL',
                    'severity_level' => 'GREAT_DANGER',
                    'status' => ResponseOperation::STATUS_ACTIVATED,
                    'start_date' => now()->subDays(10)->toDateString(),
                    'expected_completion_date' => now()->addDays(15)->toDateString(),
                    'lead_organization' => 'Upazila DMC, Dacope',
                    'commander_name' => 'UNO Dacope',
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                    'activated_by' => $actor->id,
                    'activated_at' => now()->subDays(10),
                ]);

                ResponseTeam::firstOrCreate(
                    ['team_code' => 'TEAM-DAC-SAR-01'],
                    [
                        'response_operation_id' => $dacopeOp->id,
                        'administrative_unit_id' => $dacope->id,
                        'team_name' => 'Dacope Riverine SAR Unit Alpha',
                        'agency_name' => 'Bangladesh Fire Service & Civil Defence',
                        'team_type' => 'FIRE_SERVICE',
                        'leader_name' => 'Station Officer Kamal Uddin',
                        'leader_contact' => '+8801711-000301',
                        'member_count' => 12,
                        'status' => 'ACTIVE',
                        'latitude' => 22.5728,
                        'longitude' => 89.5112,
                        'last_checkin_at' => now(),
                    ]
                );
            }

            if ($khulna) {
                $khulnaOp = ResponseOperation::create([
                    'disaster_event_id' => $disaster->id,
                    'administrative_unit_id' => $khulna->id,
                    'response_center_id' => $khulnaCenter?->id,
                    'operation_no' => 'OP-REMAL-KHL-01',
                    'title_en' => 'Khulna District Emergency Logistics & Dewatering Operation',
                    'title_bn' => 'খুলনা জেলা জরুরি লজিস্টিকস ও পানি নিষ্কাশন অভিযান',
                    'objectives' => 'District-wide fleet mobilization, emergency communication relay, and medical triage.',
                    'priority' => 'HIGH',
                    'severity_level' => 'DANGER',
                    'status' => ResponseOperation::STATUS_ONGOING,
                    'start_date' => now()->subDays(8)->toDateString(),
                    'expected_completion_date' => now()->addDays(20)->toDateString(),
                    'lead_organization' => 'District Disaster Management Committee, Khulna',
                    'commander_name' => 'DRRO Khulna',
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                    'activated_by' => $actor->id,
                    'activated_at' => now()->subDays(8),
                ]);

                $dacopeOp = $dacopeOp ?? $khulnaOp;
            }
        }

        // Seed sample historical allocation, maintenance log, and human resource deployment for dashboard analytics
        $boat = ResourceAsset::where('asset_code', 'AST-BOAT-KHL-01')->first();
        $pump = ResourceAsset::where('asset_code', 'RES-HVM-DAC-01')->first();
        $hrSpecialist = ResourceAsset::where('asset_code', 'RES-HR-KHL-01')->first();

        if ($boat && $disaster && $dacope) {
            ResourceAllocation::firstOrCreate(
                ['allocation_no' => 'RES-ALLOC-2026-0001'],
                [
                    'resource_asset_id' => $boat->id,
                    'disaster_event_id' => $disaster->id,
                    'response_operation_id' => $dacopeOp?->id,
                    'administrative_unit_id' => $dacope->id,
                    'allocation_location' => 'Sutarkhali & Kamarkhola Riverine Ghats, Dacope',
                    'latitude' => 22.5480,
                    'longitude' => 89.5150,
                    'assignment_date' => now()->subDays(18)->toDateString(),
                    'expected_return_date' => now()->subDays(10)->toDateString(),
                    'actual_return_date' => now()->subDays(11)->toDateString(),
                    'responsible_officer_name' => 'Station Officer Kamal Uddin',
                    'responsible_officer_contact' => '+8801711-000301',
                    'status' => ResourceAllocation::STATUS_RETURNED,
                    'purpose' => 'Emergency coastal evacuation during Cyclone Remal response.',
                    'utilization_notes' => 'Completed 14 riverine sorties and evacuated 210 residents.',
                    'return_condition_notes' => 'Returned in operational condition; hull inspected.',
                    'allocated_by' => $actor->id,
                    'released_by' => $actor->id,
                ]
            );
        }

        if ($pump && $dacope) {
            ResourceMaintenance::firstOrCreate(
                ['maintenance_no' => 'MNT-2026-0001'],
                [
                    'resource_asset_id' => $pump->id,
                    'administrative_unit_id' => $dacope->id,
                    'record_type' => ResourceMaintenance::TYPE_PREVENTIVE,
                    'title' => 'Quarterly Diesel Injector & Impeller Inspection',
                    'description' => 'Scheduled pre-monsoon preventive check and oil replacement.',
                    'scheduled_date' => now()->subDays(25)->toDateString(),
                    'performed_date' => now()->subDays(24)->toDateString(),
                    'next_due_date' => now()->addDays(20)->toDateString(),
                    'reminder_days_before' => 7,
                    'inspection_findings' => 'Impeller clearance within tolerance; fuel filter replaced.',
                    'inspection_result' => ResourceMaintenance::INSPECTION_PASS,
                    'repair_cost_bdt' => 14500.00,
                    'vendor_or_workshop' => 'Khulna Govt Marine & Mechanical Workshop',
                    'status' => ResourceMaintenance::STATUS_COMPLETED,
                    'performed_by_name' => 'Md. Rafiqul Islam (Senior Mechanic)',
                    'recorded_by' => $actor->id,
                    'completed_by' => $actor->id,
                ]
            );
        }

        if ($hrSpecialist && $disaster && $dacope) {
            HumanResourceDeployment::firstOrCreate(
                ['deployment_no' => 'HR-DEP-2026-0001'],
                [
                    'resource_asset_id' => $hrSpecialist->id,
                    'disaster_event_id' => $disaster->id,
                    'response_operation_id' => $dacopeOp?->id,
                    'administrative_unit_id' => $dacope->id,
                    'personnel_name' => 'Engr. Mahbubur Rahman',
                    'personnel_designation' => 'Swift-Water Rescue Specialist',
                    'personnel_contact' => '+8801711-000203',
                    'assigned_duty' => 'Lead coastal boat safety briefing and riverine evacuation drills.',
                    'deployment_location' => 'Dacope Forward Command Post',
                    'deployment_start_date' => now()->subDays(16)->toDateString(),
                    'deployment_end_date' => now()->subDays(12)->toDateString(),
                    'actual_return_date' => now()->subDays(12)->toDateString(),
                    'availability_status' => HumanResourceDeployment::STATUS_COMPLETED,
                    'notes' => 'Completed pre-cyclone readiness drills with 40 CPP volunteers.',
                    'assigned_by' => $actor->id,
                    'released_by' => $actor->id,
                ]
            );
        }
    }

    private function syncAssetPoint(ResourceAsset $asset): void
    {
        if (DB::getDriverName() === 'pgsql' && $asset->latitude !== null && $asset->longitude !== null) {
            DB::statement(
                'UPDATE resource.resource_assets SET location_geom = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?',
                [(float) $asset->longitude, (float) $asset->latitude, $asset->id]
            );
        }
    }
}
