<?php

namespace Database\Seeders;

use App\Core\Models\AdministrativeUnit;
use App\Modules\Beneficiary\Models\DisasterEligibility;
use App\Modules\Beneficiary\Models\DuplicateSuspect;
use App\Modules\Beneficiary\Models\Household;
use App\Modules\Beneficiary\Models\HouseholdMember;
use App\Modules\Beneficiary\Models\HouseholdVerification;
use App\Modules\Beneficiary\Services\VulnerabilityCalculationService;
use App\Modules\Disaster\Models\DisasterEvent;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class BeneficiarySeeder extends Seeder
{
    /**
     * Seed realistic Bangladeshi beneficiary households, family members, verifications, and disaster linkages.
     */
    public function run(): void
    {
        // Clean existing beneficiary tables before seeding
        Household::truncate();
        HouseholdMember::truncate();
        HouseholdVerification::truncate();
        DisasterEligibility::truncate();
        DuplicateSuspect::truncate();

        $vulnerabilityService = new VulnerabilityCalculationService();

        // Retrieve seeded unions (Dacope Upazila, Khulna)
        $unions = AdministrativeUnit::where('tier_level', 'UNION')->get();
        if ($unions->isEmpty()) {
            $unions = AdministrativeUnit::where('tier_level', 'UPAZILA')->get();
        }
        if ($unions->isEmpty()) {
            return;
        }

        $disasters = DisasterEvent::all();
        $remal = $disasters->firstWhere('disaster_code', 'CYC-REMAL-2024');
        $flood = $disasters->firstWhere('disaster_code', 'FLD-MONSOON-2024');

        $sampleBeneficiaries = [
            [
                'hoh_name_en' => 'Mst. Rahima Begum',
                'hoh_name_bn' => 'মোসাঃ রাহিমা বেগম',
                'nid' => '19854712345678901',
                'dob' => '1985-04-12',
                'gender' => 'FEMALE',
                'mobile' => '01712345001',
                'occupation' => 'Day Laborer',
                'income' => 3500.00,
                'income_category' => 'ULTRA_POOR',
                'village' => 'Chalna Purba Para',
                'present_address' => 'Ward 03, Chalna Purba Para, Dacope',
                'permanent_address' => 'Ward 03, Chalna Purba Para, Dacope',
                'lat' => 22.6025,
                'lng' => 89.5318,
                'is_female_headed' => true,
                'is_displaced' => true,
                'housing_type' => 'JHUPRI',
                'status' => 'APPROVED',
                'members' => [
                    [
                        'name_en' => 'Sufia Khatun',
                        'name_bn' => 'সুফিয়া খাতুন',
                        'relation' => 'PARENT',
                        'gender' => 'FEMALE',
                        'dob' => '1952-01-10',
                        'nid' => '19524712345678902',
                        'has_disability' => false,
                        'is_pregnant_lactating' => false,
                    ],
                    [
                        'name_en' => 'Al-Amin',
                        'name_bn' => 'আল-আমিন',
                        'relation' => 'CHILD',
                        'gender' => 'MALE',
                        'dob' => '2021-08-15',
                        'nid' => null,
                        'has_disability' => false,
                        'is_pregnant_lactating' => false,
                    ],
                ],
                'disaster' => $remal,
                'assistance' => [
                    'amount' => 5000.00,
                    'type' => 'CASH_BDT',
                    'date' => '2024-06-02',
                ],
            ],
            [
                'hoh_name_en' => 'Md. Abdul Karim',
                'hoh_name_bn' => 'মোঃ আব্দুল করিম',
                'nid' => '19784712345678903',
                'dob' => '1978-09-23',
                'gender' => 'MALE',
                'mobile' => '01812345002',
                'occupation' => 'Farmer',
                'income' => 4500.00,
                'income_category' => 'ULTRA_POOR',
                'village' => 'Banishanta Bazar',
                'present_address' => 'Banishanta, Dacope, Khulna',
                'permanent_address' => 'Banishanta, Dacope, Khulna',
                'lat' => 22.5412,
                'lng' => 89.5795,
                'is_female_headed' => false,
                'is_displaced' => false,
                'housing_type' => 'KATCHA',
                'status' => 'APPROVED',
                'members' => [
                    [
                        'name_en' => 'Fatema Begum',
                        'name_bn' => 'ফাতেমা বেগম',
                        'relation' => 'SPOUSE',
                        'gender' => 'FEMALE',
                        'dob' => '1988-11-05',
                        'nid' => '19884712345678904',
                        'has_disability' => false,
                        'is_pregnant_lactating' => true,
                    ],
                    [
                        'name_en' => 'Mehedi Hasan',
                        'name_bn' => 'মেহেদী হাসান',
                        'relation' => 'CHILD',
                        'gender' => 'MALE',
                        'dob' => '2010-03-14',
                        'nid' => null,
                        'has_disability' => true,
                        'disability_type' => 'PHYSICAL',
                        'is_pregnant_lactating' => false,
                    ],
                ],
                'disaster' => $remal,
                'assistance' => [
                    'amount' => 5000.00,
                    'type' => 'FOOD_GRAIN_KG',
                    'date' => '2024-06-05',
                ],
            ],
            [
                'hoh_name_en' => 'Md. Rafiqul Islam',
                'hoh_name_bn' => 'মোঃ রফিকুল ইসলাম',
                'nid' => '19824712345678905',
                'dob' => '1982-06-18',
                'gender' => 'MALE',
                'mobile' => '01912345003',
                'occupation' => 'Fisherman',
                'income' => 5500.00,
                'income_category' => 'POOR',
                'village' => 'Sutarkhali Ghat',
                'present_address' => 'Sutarkhali, Dacope, Khulna',
                'permanent_address' => 'Sutarkhali, Dacope, Khulna',
                'lat' => 22.4225,
                'lng' => 89.4520,
                'is_female_headed' => false,
                'is_displaced' => true,
                'housing_type' => 'KATCHA',
                'status' => 'FIELD_VERIFIED',
                'members' => [
                    [
                        'name_en' => 'Mariam Bibi',
                        'name_bn' => 'মরিয়ম বিবি',
                        'relation' => 'SPOUSE',
                        'gender' => 'FEMALE',
                        'dob' => '1989-02-14',
                        'nid' => '19894712345678906',
                        'has_disability' => false,
                        'is_pregnant_lactating' => false,
                    ],
                    [
                        'name_en' => 'Ayesha',
                        'name_bn' => 'আয়েশা',
                        'relation' => 'CHILD',
                        'gender' => 'FEMALE',
                        'dob' => '2023-05-10',
                        'nid' => null,
                        'has_disability' => false,
                        'is_pregnant_lactating' => false,
                    ],
                ],
                'disaster' => $remal,
                'assistance' => null,
            ],
            [
                'hoh_name_en' => 'Jahanara Akter',
                'hoh_name_bn' => 'জাহানারা আক্তার',
                'nid' => '19924712345678907',
                'dob' => '1992-12-01',
                'gender' => 'FEMALE',
                'mobile' => '01612345004',
                'occupation' => 'Handicraft',
                'income' => 4000.00,
                'income_category' => 'ULTRA_POOR',
                'village' => 'Bajua Purbapara',
                'present_address' => 'Bajua Union, Dacope',
                'permanent_address' => 'Bajua Union, Dacope',
                'lat' => 22.5130,
                'lng' => 89.4995,
                'is_female_headed' => true,
                'is_displaced' => false,
                'housing_type' => 'KATCHA',
                'status' => 'PENDING_VERIFICATION',
                'members' => [
                    [
                        'name_en' => 'Riad Hossain',
                        'name_bn' => 'রিয়াদ হোসেন',
                        'relation' => 'CHILD',
                        'gender' => 'MALE',
                        'dob' => '2022-10-10',
                        'nid' => null,
                        'has_disability' => false,
                        'is_pregnant_lactating' => false,
                    ],
                ],
                'disaster' => null,
                'assistance' => null,
            ],
            [
                'hoh_name_en' => 'Md. Babul Sheikh',
                'hoh_name_bn' => 'মোঃ বাবুল শেখ',
                'nid' => '19754712345678908',
                'dob' => '1975-03-30',
                'gender' => 'MALE',
                'mobile' => '01712345005',
                'occupation' => 'Van Driver',
                'income' => 6000.00,
                'income_category' => 'POOR',
                'village' => 'Chalna Uttar Para',
                'present_address' => 'Ward 02, Chalna',
                'permanent_address' => 'Ward 02, Chalna',
                'lat' => 22.6040,
                'lng' => 89.5330,
                'is_female_headed' => false,
                'is_displaced' => false,
                'housing_type' => 'SEMI_PUCCA',
                'status' => 'DRAFT',
                'members' => [],
                'disaster' => null,
                'assistance' => null,
            ],
            [
                'hoh_name_en' => 'Sufia Begum',
                'hoh_name_bn' => 'সুফিয়া বেগম',
                'nid' => '19584712345678909',
                'dob' => '1958-07-15',
                'gender' => 'FEMALE',
                'mobile' => '01812345006',
                'occupation' => 'Unemployed / Elderly',
                'income' => 1500.00,
                'income_category' => 'ULTRA_POOR',
                'village' => 'Banishanta Ward 01',
                'present_address' => 'Banishanta, Dacope',
                'permanent_address' => 'Banishanta, Dacope',
                'lat' => 22.5420,
                'lng' => 89.5800,
                'is_female_headed' => true,
                'is_displaced' => true,
                'housing_type' => 'JHUPRI',
                'status' => 'APPROVED',
                'members' => [],
                'disaster' => $remal,
                'assistance' => [
                    'amount' => 3000.00,
                    'type' => 'CASH_BDT',
                    'date' => '2024-06-10',
                ],
            ],
            [
                'hoh_name_en' => 'Md. Mostafa Kamal',
                'hoh_name_bn' => 'মোঃ মোস্তফা কামাল',
                'nid' => '19804712345678910',
                'dob' => '1980-05-19',
                'gender' => 'MALE',
                'mobile' => '01512345007',
                'occupation' => 'Small Business',
                'income' => 7500.00,
                'income_category' => 'LOWER_MIDDLE',
                'village' => 'Bajua Bazar',
                'present_address' => 'Bajua, Dacope',
                'permanent_address' => 'Bajua, Dacope',
                'lat' => 22.5140,
                'lng' => 89.5010,
                'is_female_headed' => false,
                'is_displaced' => false,
                'housing_type' => 'PUCCA',
                'status' => 'APPROVED',
                'members' => [
                    [
                        'name_en' => 'Salma Khatun',
                        'name_bn' => 'সালমা খাতুন',
                        'relation' => 'SPOUSE',
                        'gender' => 'FEMALE',
                        'dob' => '1986-08-20',
                        'nid' => '19864712345678911',
                        'has_disability' => false,
                        'is_pregnant_lactating' => false,
                    ],
                ],
                'disaster' => $flood,
                'assistance' => [
                    'amount' => 4500.00,
                    'type' => 'EMERGENCY_FOOD',
                    'date' => '2024-08-28',
                ],
            ],
            [
                'hoh_name_en' => 'Mst. Kohinoor Begum',
                'hoh_name_bn' => 'মোসাঃ কোহিনূর বেগম',
                'nid' => '19904712345678912',
                'dob' => '1990-10-11',
                'gender' => 'FEMALE',
                'mobile' => '01712345008',
                'occupation' => 'Domestic Worker',
                'income' => 3800.00,
                'income_category' => 'ULTRA_POOR',
                'village' => 'Sutarkhali Purba',
                'present_address' => 'Sutarkhali, Dacope',
                'permanent_address' => 'Sutarkhali, Dacope',
                'lat' => 22.4230,
                'lng' => 89.4530,
                'is_female_headed' => true,
                'is_displaced' => true,
                'housing_type' => 'JHUPRI',
                'status' => 'FIELD_VERIFIED',
                'members' => [
                    [
                        'name_en' => 'Tania',
                        'name_bn' => 'তানিয়া',
                        'relation' => 'CHILD',
                        'gender' => 'FEMALE',
                        'dob' => '2023-01-05',
                        'nid' => null,
                        'has_disability' => false,
                        'is_pregnant_lactating' => false,
                    ],
                ],
                'disaster' => $remal,
                'assistance' => null,
            ],
        ];

        $createdHouseholds = [];

        foreach ($sampleBeneficiaries as $idx => $data) {
            $union = $unions[$idx % $unions->count()];
            $householdId = (string) Str::uuid();
            $code = sprintf('HH-404712-%05d', $idx + 1);

            $household = new Household();
            $household->id = $householdId;
            $household->household_code = $code;
            $household->administrative_unit_id = $union->id;
            $household->village_ward_name = $data['village'];
            $household->present_address = $data['present_address'];
            $household->permanent_address = $data['permanent_address'];
            $household->hoh_name_en = $data['hoh_name_en'];
            $household->hoh_name_bn = $data['hoh_name_bn'];
            $household->hoh_nid_hash = Household::hashNid($data['nid']);
            $household->hoh_nid_encrypted = Crypt::encryptString($data['nid']);
            $household->hoh_nid_masked = Household::maskNid($data['nid']);
            $household->hoh_dob = $data['dob'];
            $household->hoh_gender = $data['gender'];
            $household->hoh_mobile_hash = Household::hashMobile($data['mobile']);
            $household->hoh_mobile_encrypted = Crypt::encryptString($data['mobile']);
            $household->hoh_mobile_masked = Household::maskMobile($data['mobile']);
            $household->hoh_occupation = $data['occupation'];
            $household->monthly_income_bdt = $data['income'];
            $household->income_category = $data['income_category'];
            $household->family_member_count = 1 + count($data['members']);
            $household->is_female_headed = $data['is_female_headed'];
            $household->is_displaced = $data['is_displaced'];
            $household->latitude = $data['lat'];
            $household->longitude = $data['lng'];
            $household->verification_status = $data['status'];
            $household->nid_verified = in_array($data['status'], ['FIELD_VERIFIED', 'APPROVED']);
            $household->address_verified = in_array($data['status'], ['FIELD_VERIFIED', 'APPROVED']);
            $household->field_verified = in_array($data['status'], ['FIELD_VERIFIED', 'APPROVED']);
            $household->other_vulnerabilities = [
                'housing_condition' => $data['housing_type'],
                'safe_drinking_water' => false,
                'sanitary_latrine' => false,
            ];
            $household->save();

            // Insert family members
            foreach ($data['members'] as $m) {
                $member = new HouseholdMember();
                $member->id = (string) Str::uuid();
                $member->household_id = $householdId;
                $member->full_name_en = $m['name_en'];
                $member->full_name_bn = $m['name_bn'];
                $member->relationship_to_hoh = $m['relation'];
                $member->gender = $m['gender'];
                $member->date_of_birth = $m['dob'];
                $member->age = Carbon::parse($m['dob'])->age;
                if ($m['nid']) {
                    $member->nid_hash = Household::hashNid($m['nid']);
                    $member->nid_encrypted = Crypt::encryptString($m['nid']);
                    $member->nid_masked = Household::maskNid($m['nid']);
                }
                $member->has_disability = $m['has_disability'];
                $member->disability_type = $m['disability_type'] ?? null;
                $member->is_pregnant_lactating = $m['is_pregnant_lactating'];
                $member->save();
            }

            // Recalculate deterministic vulnerability score
            $vulnerabilityService->recalculateForModel($household);

            // Record verification audit history
            if (in_array($data['status'], ['FIELD_VERIFIED', 'APPROVED'])) {
                HouseholdVerification::create([
                    'id' => (string) Str::uuid(),
                    'household_id' => $householdId,
                    'stage' => 'FIELD_SURVEY',
                    'status' => 'VERIFIED',
                    'verified_by' => null,
                    'verified_at' => now()->subDays(5),
                    'remarks' => 'Field survey completed and physical presence verified by enumerator.',
                ]);
            }
            if ($data['status'] === 'APPROVED') {
                HouseholdVerification::create([
                    'id' => (string) Str::uuid(),
                    'household_id' => $householdId,
                    'stage' => 'COMMITTEE_APPROVAL',
                    'status' => 'VERIFIED',
                    'verified_by' => null,
                    'verified_at' => now()->subDays(2),
                    'remarks' => 'Verified against BBS demographic records and approved by Upazila PIO.',
                ]);
            }

            // Disaster Eligibility Linking
            if ($data['disaster']) {
                DisasterEligibility::create([
                    'id' => (string) Str::uuid(),
                    'household_id' => $householdId,
                    'disaster_event_id' => $data['disaster']->id,
                    'eligibility_status' => 'ELIGIBLE',
                    'assistance_count' => $data['assistance'] ? 1 : 0,
                    'last_assistance_at' => $data['assistance'] ? $data['assistance']['date'] : null,
                    'eligibility_criteria' => [
                        'vulnerability_score' => $household->vulnerability_score,
                        'disaster_zone' => true,
                    ],
                ]);
            }

            $createdHouseholds[] = $household;
        }

        // Add 1 Suspected Duplicate Pair for Duplicate Review demonstration
        if (count($createdHouseholds) >= 2) {
            DuplicateSuspect::create([
                'id' => (string) Str::uuid(),
                'household_id' => $createdHouseholds[0]->id,
                'matched_household_id' => $createdHouseholds[1]->id,
                'match_type' => 'MOBILE_MATCH',
                'confidence_score' => 88.50,
                'status' => 'PENDING_REVIEW',
                'resolution_notes' => 'Shared contact number across neighboring villages in Dacope',
            ]);
        }
    }
}
