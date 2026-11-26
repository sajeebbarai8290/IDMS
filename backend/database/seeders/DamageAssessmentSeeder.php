<?php

namespace Database\Seeders;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Modules\DamageAssessment\Models\DamageAssessment;
use App\Modules\DamageAssessment\Models\DamageCategory;
use App\Modules\DamageAssessment\Models\DamageLocation;
use App\Modules\DamageAssessment\Models\DamageSectorItem;
use App\Modules\DamageAssessment\Models\DamageVerificationLog;
use App\Modules\DamageAssessment\Services\DamageSpatialService;
use App\Modules\Disaster\Models\DisasterEvent;
use Illuminate\Database\Seeder;

class DamageAssessmentSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'code' => 'HOUSING',
                'name_en' => 'Houses and Buildings',
                'name_bn' => 'ঘরবাড়ি ও ভবন',
                'description' => 'Residential dwellings, katcha/semi-pucca/pucca houses, and community buildings.',
                'default_unit' => 'HOUSES',
                'color_hex' => '#dc3545',
                'sort_order' => 10,
                'is_system' => true,
            ],
            [
                'code' => 'ROADS_BRIDGES',
                'name_en' => 'Roads and Bridges',
                'name_bn' => 'সড়ক ও সেতু',
                'description' => 'Rural roads, paved highways, culverts, and Bailey/concrete bridges.',
                'default_unit' => 'KM / STRUCTURES',
                'color_hex' => '#fd7e14',
                'sort_order' => 20,
                'is_system' => true,
            ],
            [
                'code' => 'EDUCATION',
                'name_en' => 'Educational Institutions',
                'name_bn' => 'শিক্ষা প্রতিষ্ঠান',
                'description' => 'Primary/secondary schools, madrasas, colleges, and school-cum-shelters.',
                'default_unit' => 'INSTITUTIONS',
                'color_hex' => '#0d6efd',
                'sort_order' => 30,
                'is_system' => true,
            ],
            [
                'code' => 'HEALTHCARE',
                'name_en' => 'Healthcare Facilities',
                'name_bn' => 'স্বাস্থ্যসেবা কেন্দ্র',
                'description' => 'Community clinics, union health sub-centers, and upazila health complexes.',
                'default_unit' => 'FACILITIES',
                'color_hex' => '#d63384',
                'sort_order' => 40,
                'is_system' => true,
            ],
            [
                'code' => 'AGRICULTURE',
                'name_en' => 'Agriculture and Crops',
                'name_bn' => 'কৃষি ও ফসল',
                'description' => 'Paddy, seasonal vegetables, seedbeds, orchards, and irrigation infrastructure.',
                'default_unit' => 'HECTARES',
                'color_hex' => '#198754',
                'sort_order' => 50,
                'is_system' => true,
            ],
            [
                'code' => 'LIVESTOCK_FISHERIES',
                'name_en' => 'Livestock and Fisheries',
                'name_bn' => 'প্রাণিসম্পদ ও মৎস্য',
                'description' => 'Cattle, poultry, shrimp enclosures (gher), fish ponds, and hatcheries.',
                'default_unit' => 'FARMS / HEADS',
                'color_hex' => '#20c997',
                'sort_order' => 60,
                'is_system' => true,
            ],
            [
                'code' => 'WATER_SANITATION',
                'name_en' => 'Water and Sanitation',
                'name_bn' => 'পানি ও স্যানিটেশন',
                'description' => 'Tube-wells, pond sand filters (PSF), rainwater harvesters, and sanitary latrines.',
                'default_unit' => 'POINTS',
                'color_hex' => '#0dcaf0',
                'sort_order' => 70,
                'is_system' => true,
            ],
            [
                'code' => 'ELECTRICITY_COMM',
                'name_en' => 'Electricity and Communication',
                'name_bn' => 'বিদ্যুৎ ও যোগাযোগ',
                'description' => 'Power distribution poles, transformers, telecom towers, and optical fiber lines.',
                'default_unit' => 'POLES / LINES',
                'color_hex' => '#6610f2',
                'sort_order' => 80,
                'is_system' => true,
            ],
            [
                'code' => 'PUBLIC_INFRASTRUCTURE',
                'name_en' => 'Public Infrastructure',
                'name_bn' => 'সরকারি অবকাঠামো',
                'description' => 'Flood embankments, sluice gates, UP offices, cyclone shelters, and markets.',
                'default_unit' => 'STRUCTURES',
                'color_hex' => '#6f42c1',
                'sort_order' => 90,
                'is_system' => true,
            ],
        ];

        foreach ($categories as $cat) {
            DamageCategory::firstOrCreate(
                ['code' => $cat['code']],
                array_merge($cat, ['is_active' => true])
            );
        }

        $dacope = AdministrativeUnit::where('code', '12')->first();
        $chalna = AdministrativeUnit::where('code', '35')->first();
        $khulna = AdministrativeUnit::where('code', '47')->first();
        $barishal = AdministrativeUnit::where('code', '10')->first();

        $remal = DisasterEvent::where('disaster_code', 'CYC-REMAL-2024')->first();
        $flood = DisasterEvent::where('disaster_code', 'FLD-MONSOON-2024')->first();

        if (!$remal || (!$dacope && !$khulna)) {
            return;
        }

        $fieldOfficer = User::where('role_code', 'ROLE_FIELD_ENUMERATOR')->first()
            ?? User::first()
            ?? User::create([
                'keycloak_sub' => '00000000-0000-4000-8000-000000000901',
                'email' => 'enumerator.dacope@modmr.gov.bd',
                'full_name_en' => 'Md. Kamal Hossain',
                'full_name_bn' => 'মোঃ কামাল হোসেন',
                'role_code' => 'ROLE_FIELD_ENUMERATOR',
                'administrative_unit_id' => $chalna?->id ?? $dacope?->id ?? $khulna?->id,
                'designation' => 'Sub-Assistant Engineer (Field Enumerator)',
                'is_active' => true,
            ]);

        $supervisor = User::where('role_code', 'ROLE_DRRO')->first()
            ?? User::where('id', '!=', $fieldOfficer->id)->first()
            ?? User::create([
                'keycloak_sub' => '00000000-0000-4000-8000-000000000902',
                'email' => 'drro.khulna@modmr.gov.bd',
                'full_name_en' => 'Md. Rafiqul Islam',
                'full_name_bn' => 'মোঃ রফিকুল ইসলাম',
                'role_code' => 'ROLE_DRRO',
                'administrative_unit_id' => $khulna?->id ?? $dacope?->id,
                'designation' => 'District Relief & Rehabilitation Officer',
                'is_active' => true,
            ]);

        $spatial = app(DamageSpatialService::class);
        $catMap = DamageCategory::all()->keyBy('code');

        $primaryUnit = $chalna ?? $dacope ?? $khulna;

        // 1. Approved D-Form (Chalna / Dacope - Cyclone Remal)
        if (!DamageAssessment::where('d_form_code', 'DF-MODMR-2026-0001')->exists()) {
            $a1 = DamageAssessment::create([
                'disaster_event_id' => $remal->id,
                'administrative_unit_id' => $primaryUnit->id,
                'd_form_code' => 'DF-MODMR-2026-0001',
                'reference_no' => 'MoDMR/D-FORM/47-12/2026-01',
                'assessment_type' => DamageAssessment::TYPE_FINAL_CONSOLIDATED,
                'assessment_date' => '2026-11-10',
                'assessed_at' => '2026-11-10 10:30:00',
                'assessing_organization' => 'Upazila Disaster Management Committee (Dacope)',
                'team_name' => 'Dacope Rapid Survey Team Alpha',
                'team_lead_name' => 'Md. Kamal Hossain',
                'team_members' => [
                    ['name' => 'Md. Kamal Hossain', 'designation' => 'Sub-Assistant Engineer', 'role' => 'Team Lead'],
                    ['name' => 'Farida Yasmin', 'designation' => 'Union Health Assistant', 'role' => 'Wash & Health'],
                ],
                'assessor_user_id' => $fieldOfficer->id,
                'assessor_name' => $fieldOfficer->full_name_en,
                'assessor_designation' => 'Sub-Assistant Engineer',
                'assessor_contact' => '+8801711000901',
                'ward_no' => 'Ward-03, Ward-05',
                'location_summary' => 'Chalna Union & Polder-31 Coastal Wards, Dacope, Khulna',
                'latitude' => 22.6025000,
                'longitude' => 89.5125000,
                'boundary_geojson' => [
                    'type' => 'Polygon',
                    'coordinates' => [[[89.49, 22.58], [89.54, 22.58], [89.54, 22.63], [89.49, 22.63], [89.49, 22.58]]],
                ],
                'severity_level' => DamageAssessment::SEVERITY_CRITICAL,
                'affected_households' => 1450,
                'affected_population' => 6250,
                'displaced_population' => 1820,
                'deaths_count' => 1,
                'injured_count' => 18,
                'missing_count' => 0,
                'total_fully_damaged' => 420,
                'total_partially_damaged' => 760,
                'total_minor_damaged' => 390,
                'total_estimated_loss_bdt' => 48500000.00,
                'field_observations' => 'Storm surge breached 1.8 km earthen embankment and inundated low-lying katcha dwellings and shrimp ghers.',
                'verification_status' => DamageAssessment::STATUS_APPROVED,
                'submitted_by' => $fieldOfficer->id,
                'submitted_at' => now()->subDays(5),
                'verified_by' => $supervisor->id,
                'verified_at' => now()->subDays(3),
                'approved_by' => $supervisor->id,
                'approved_at' => now()->subDays(2),
                'created_by' => $fieldOfficer->id,
                'updated_by' => $supervisor->id,
                'sync_status' => DamageAssessment::SYNC_ONLINE,
            ]);

            DamageLocation::create([
                'damage_assessment_id' => $a1->id,
                'administrative_unit_id' => $primaryUnit->id,
                'ward_no' => 'Ward-03',
                'locality_name_en' => 'Chalna Paschim Para',
                'locality_name_bn' => 'চালনা পশ্চিম পাড়া',
                'affected_households' => 820,
                'affected_population' => 3520,
                'severity_level' => DamageAssessment::SEVERITY_CRITICAL,
                'latitude' => 22.6051,
                'longitude' => 89.5082,
            ]);

            $items1 = [
                ['HOUSING', 'Katcha & Semi-Pucca Dwellings', 'HOUSES', 320, 540, 260, 18500000, 22.6031, 89.5112],
                ['PUBLIC_INFRASTRUCTURE', 'Polder-31 Embankment Section', 'METERS', 18, 24, 10, 14500000, 22.5985, 89.5190],
                ['LIVESTOCK_FISHERIES', 'Coastal Shrimp Ghers & Ponds', 'FARMS', 52, 110, 75, 9800000, 22.6095, 89.5050],
                ['WATER_SANITATION', 'Tube-wells & Sanitary Latrines', 'POINTS', 30, 86, 45, 5700000, 22.6012, 89.5150],
            ];
            foreach ($items1 as [$code, $title, $unit, $full, $part, $minor, $loss, $lat, $lng]) {
                $cat = $catMap[$code];
                $si = DamageSectorItem::create([
                    'damage_assessment_id' => $a1->id,
                    'category_id' => $cat->id,
                    'sector_code' => $cat->code,
                    'item_title' => $title,
                    'unit_of_measure' => $unit,
                    'fully_damaged_count' => $full,
                    'partially_damaged_count' => $part,
                    'minor_damaged_count' => $minor,
                    'affected_households' => 350,
                    'estimated_loss_bdt' => $loss,
                    'latitude' => $lat,
                    'longitude' => $lng,
                ]);
                $spatial->syncSectorItemGeometry($si);
            }

            DamageVerificationLog::create([
                'damage_assessment_id' => $a1->id,
                'action' => 'APPROVED',
                'from_status' => DamageAssessment::STATUS_VERIFIED,
                'to_status' => DamageAssessment::STATUS_APPROVED,
                'actor_id' => $supervisor->id,
                'actor_name' => $supervisor->full_name_en,
                'actor_role' => $supervisor->role_code,
                'notes' => 'Consolidated and endorsed for rehabilitation budget allocation.',
                'created_at' => now()->subDays(2),
            ]);

            $spatial->syncAssessmentGeometry($a1);
        }

        // 2. Verified D-Form (Dacope Upazila - Cyclone Remal)
        if ($dacope && !DamageAssessment::where('d_form_code', 'DF-MODMR-2026-0002')->exists()) {
            $a2 = DamageAssessment::create([
                'disaster_event_id' => $remal->id,
                'administrative_unit_id' => $dacope->id,
                'd_form_code' => 'DF-MODMR-2026-0002',
                'reference_no' => 'MoDMR/D-FORM/47-12/2026-02',
                'assessment_type' => DamageAssessment::TYPE_DETAILED_SECTORAL,
                'assessment_date' => '2026-11-14',
                'assessed_at' => '2026-11-14 14:15:00',
                'assessing_organization' => 'LGED & PIO Joint Assessment Unit, Dacope',
                'team_name' => 'Infrastructure & Education Team',
                'team_lead_name' => 'Engr. Tariqul Islam',
                'assessor_user_id' => $fieldOfficer->id,
                'assessor_name' => $fieldOfficer->full_name_en,
                'assessor_designation' => 'Sub-Assistant Engineer',
                'ward_no' => 'Ward-01, Ward-02',
                'location_summary' => 'Dacope Sadar Feeder Roads, Schools & Clinic',
                'latitude' => 22.5720000,
                'longitude' => 89.5160000,
                'severity_level' => DamageAssessment::SEVERITY_SEVERE,
                'affected_households' => 680,
                'affected_population' => 2940,
                'displaced_population' => 410,
                'deaths_count' => 0,
                'injured_count' => 6,
                'missing_count' => 0,
                'total_fully_damaged' => 24,
                'total_partially_damaged' => 58,
                'total_minor_damaged' => 42,
                'total_estimated_loss_bdt' => 21400000.00,
                'field_observations' => 'Two rural brick-soling roads washed out; primary school roof tin sheets blown away.',
                'verification_status' => DamageAssessment::STATUS_VERIFIED,
                'submitted_by' => $fieldOfficer->id,
                'submitted_at' => now()->subDays(3),
                'verified_by' => $supervisor->id,
                'verified_at' => now()->subDay(),
                'created_by' => $fieldOfficer->id,
                'updated_by' => $supervisor->id,
                'sync_status' => DamageAssessment::SYNC_ONLINE,
            ]);

            $items2 = [
                ['ROADS_BRIDGES', 'Upazila Rural Connecting Roads', 'KM', 12, 22, 16, 12800000, 22.5750, 89.5200],
                ['EDUCATION', 'Government Primary Schools', 'INSTITUTIONS', 3, 9, 11, 5100000, 22.5690, 89.5110],
                ['HEALTHCARE', 'Union Health Sub-Center & Clinics', 'FACILITIES', 1, 4, 5, 2200000, 22.5715, 89.5145],
                ['ELECTRICITY_COMM', 'REB 11kV Distribution Poles', 'POLES', 8, 23, 10, 1300000, 22.5735, 89.5180],
            ];
            foreach ($items2 as [$code, $title, $unit, $full, $part, $minor, $loss, $lat, $lng]) {
                $cat = $catMap[$code];
                $si = DamageSectorItem::create([
                    'damage_assessment_id' => $a2->id,
                    'category_id' => $cat->id,
                    'sector_code' => $cat->code,
                    'item_title' => $title,
                    'unit_of_measure' => $unit,
                    'fully_damaged_count' => $full,
                    'partially_damaged_count' => $part,
                    'minor_damaged_count' => $minor,
                    'affected_households' => 170,
                    'estimated_loss_bdt' => $loss,
                    'latitude' => $lat,
                    'longitude' => $lng,
                ]);
                $spatial->syncSectorItemGeometry($si);
            }

            $spatial->syncAssessmentGeometry($a2);
        }

        // 3. Submitted D-Form (Barishal - Eastern Flash Flood)
        if ($barishal && $flood && !DamageAssessment::where('d_form_code', 'DF-MODMR-2026-0003')->exists()) {
            $a3 = DamageAssessment::create([
                'disaster_event_id' => $flood->id,
                'administrative_unit_id' => $barishal->id,
                'd_form_code' => 'DF-MODMR-2026-0003',
                'assessment_type' => DamageAssessment::TYPE_PRELIMINARY_D_FORM,
                'assessment_date' => '2026-11-20',
                'assessed_at' => '2026-11-20 09:45:00',
                'assessing_organization' => 'Barishal District Agriculture & DRRO Field Team',
                'team_name' => 'Barishal Agro-Flood Assessment Unit',
                'assessor_user_id' => $fieldOfficer->id,
                'assessor_name' => 'Afsana Begum',
                'assessor_designation' => 'Field Enumerator',
                'location_summary' => 'Barishal Riverine Floodplain Croplands',
                'latitude' => 22.7010000,
                'longitude' => 90.3535000,
                'severity_level' => DamageAssessment::SEVERITY_MODERATE,
                'affected_households' => 420,
                'affected_population' => 1850,
                'total_fully_damaged' => 85,
                'total_partially_damaged' => 160,
                'total_minor_damaged' => 90,
                'total_estimated_loss_bdt' => 9600000.00,
                'field_observations' => 'Aman paddy seedbeds submerged for 72 hours; awaiting supervisor verification.',
                'verification_status' => DamageAssessment::STATUS_SUBMITTED,
                'submitted_by' => $fieldOfficer->id,
                'submitted_at' => now()->subHours(18),
                'created_by' => $fieldOfficer->id,
                'updated_by' => $fieldOfficer->id,
                'sync_status' => DamageAssessment::SYNC_SYNCED,
                'synced_at' => now()->subHours(18),
                'client_uuid' => '99999999-0000-4000-8000-000000000003',
            ]);

            $cat = $catMap['AGRICULTURE'];
            $si = DamageSectorItem::create([
                'damage_assessment_id' => $a3->id,
                'category_id' => $cat->id,
                'sector_code' => $cat->code,
                'item_title' => 'Aman Paddy & Seasonal Vegetables',
                'unit_of_measure' => 'HECTARES',
                'fully_damaged_count' => 85,
                'partially_damaged_count' => 160,
                'minor_damaged_count' => 90,
                'affected_households' => 420,
                'estimated_loss_bdt' => 9600000.00,
                'latitude' => 22.7010,
                'longitude' => 90.3535,
            ]);
            $spatial->syncSectorItemGeometry($si);
            $spatial->syncAssessmentGeometry($a3);
        }
    }
}
