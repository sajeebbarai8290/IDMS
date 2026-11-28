<?php

namespace Database\Seeders;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Modules\Disaster\Models\DisasterEvent;
use App\Modules\Volunteer\Models\SkillCatalog;
use App\Modules\Volunteer\Models\Volunteer;
use App\Modules\Volunteer\Models\VolunteerAttendance;
use App\Modules\Volunteer\Models\VolunteerCategory;
use App\Modules\Volunteer\Models\VolunteerDeployment;
use App\Modules\Volunteer\Models\VolunteerOrganization;
use App\Modules\Volunteer\Models\VolunteerOrganizationCoverage;
use App\Modules\Volunteer\Models\VolunteerSkillAssignment;
use App\Modules\Volunteer\Models\VolunteerTrainingEnrollment;
use App\Modules\Volunteer\Models\VolunteerTrainingProgram;
use App\Modules\Volunteer\Models\VolunteerTrainingSession;
use App\Modules\Volunteer\Models\VolunteerVerificationLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class VolunteerSeeder extends Seeder
{
    public function run(): void
    {
        $categoriesData = [
            [
                'code' => 'SEARCH_AND_RESCUE',
                'name_en' => 'Search and Rescue',
                'name_bn' => 'অনুসন্ধান ও উদ্ধার',
                'description' => 'Coastal, floodwater, and structural search and rescue responders.',
                'color_hex' => '#dc2626',
                'sort_order' => 10,
            ],
            [
                'code' => 'FIRST_AID',
                'name_en' => 'First Aid',
                'name_bn' => 'প্রাথমিক চিকিৎসা',
                'description' => 'Basic trauma care, CPR, triage, and emergency stabilization.',
                'color_hex' => '#e11d48',
                'sort_order' => 20,
            ],
            [
                'code' => 'MEDICAL_SUPPORT',
                'name_en' => 'Medical Support',
                'name_bn' => 'চিকিৎসা সহায়তা',
                'description' => 'Paramedical assistance, mobile clinic support, and maternal/child aid.',
                'color_hex' => '#db2777',
                'sort_order' => 30,
            ],
            [
                'code' => 'EVACUATION_SUPPORT',
                'name_en' => 'Evacuation Support',
                'name_bn' => 'নিরাপদ আশ্রয় ও সরিয়ে নেওয়া',
                'description' => 'Early warning dissemination, shelter guiding, and vulnerable household evacuation.',
                'color_hex' => '#ea580c',
                'sort_order' => 40,
            ],
            [
                'code' => 'RELIEF_DISTRIBUTION',
                'name_en' => 'Relief Distribution',
                'name_bn' => 'ত্রাণ বিতরণ সহায়তা',
                'description' => 'Muster roll assistance, crowd coordination, and shelter food distribution.',
                'color_hex' => '#0284c7',
                'sort_order' => 50,
            ],
            [
                'code' => 'COMMUNICATION',
                'name_en' => 'Communication',
                'name_bn' => 'যোগাযোগ ও সতর্কবার্তা',
                'description' => 'VHF/HF radio, megaphone/siren signaling, and SitRep relay.',
                'color_hex' => '#7c3aed',
                'sort_order' => 60,
            ],
            [
                'code' => 'COMMUNITY_AWARENESS',
                'name_en' => 'Community Awareness',
                'name_bn' => 'কমিউনিটি সচেতনতা',
                'description' => 'Disaster preparedness drills, school safety, and community resilience outreach.',
                'color_hex' => '#16a34a',
                'sort_order' => 70,
            ],
        ];

        $categories = [];
        foreach ($categoriesData as $cat) {
            $categories[$cat['code']] = VolunteerCategory::firstOrCreate(
                ['code' => $cat['code']],
                array_merge($cat, ['is_system' => true, 'is_active' => true])
            );
        }

        $skillsData = [
            ['skill_code' => 'SWIFT_WATER_RESCUE', 'category' => 'SEARCH_AND_RESCUE', 'name_en' => 'Swift Water & Flood Rescue', 'name_bn' => 'বন্যার পানি ও স্রোতে উদ্ধার', 'requires_certification' => true],
            ['skill_code' => 'BOAT_HANDLING', 'category' => 'SEARCH_AND_RESCUE', 'name_en' => 'Rescue Boat Operation', 'name_bn' => 'উদ্ধার নৌকা পরিচালনা', 'requires_certification' => true],
            ['skill_code' => 'FIRST_AID_CPR', 'category' => 'FIRST_AID', 'name_en' => 'First Aid & CPR', 'name_bn' => 'প্রাথমিক চিকিৎসা ও সিপিআর', 'requires_certification' => true],
            ['skill_code' => 'FIELD_TRIAGE', 'category' => 'MEDICAL_SUPPORT', 'name_en' => 'Emergency Field Triage', 'name_bn' => 'জরুরি ফিল্ড ট্রায়াজ সহায়তা', 'requires_certification' => true],
            ['skill_code' => 'CYCLONE_EVACUATION', 'category' => 'EVACUATION_SUPPORT', 'name_en' => 'Cyclone Shelter Evacuation', 'name_bn' => 'ঘূর্ণিঝড় আশ্রয়কেন্দ্রে সরিয়ে নেওয়া', 'requires_certification' => false],
            ['skill_code' => 'RELIEF_LOGISTICS', 'category' => 'RELIEF_DISTRIBUTION', 'name_en' => 'Relief Distribution & Crowd Management', 'name_bn' => 'ত্রাণ বিতরণ ও শৃঙ্খলা ব্যবস্থাপনা', 'requires_certification' => false],
            ['skill_code' => 'VHF_RADIO_OPS', 'category' => 'COMMUNICATION', 'name_en' => 'VHF/HF Wireless Operation', 'name_bn' => 'ভিএইচএফ/এইচএফ ওয়্যারলেস পরিচালনা', 'requires_certification' => true],
            ['skill_code' => 'DRR_DRILL_FACILITATION', 'category' => 'COMMUNITY_AWARENESS', 'name_en' => 'Community DRR Drill Facilitation', 'name_bn' => 'দুর্যোগ মহড়া পরিচালনা', 'requires_certification' => false],
        ];

        $skills = [];
        foreach ($skillsData as $sk) {
            $skills[$sk['skill_code']] = SkillCatalog::firstOrCreate(
                ['skill_code' => $sk['skill_code']],
                [
                    'category_id' => $categories[$sk['category']]->id ?? null,
                    'name_en' => $sk['name_en'],
                    'name_bn' => $sk['name_bn'],
                    'requires_certification' => $sk['requires_certification'],
                    'is_system' => true,
                    'is_active' => true,
                ]
            );
        }

        $khulna = AdministrativeUnit::where('code', '47')->first();
        $dacope = AdministrativeUnit::where('code', '12')->first();
        $chalna = AdministrativeUnit::where('code', '35')->first();
        $barishal = AdministrativeUnit::where('code', '10')->first();

        if (!$khulna || !$dacope || !$chalna) {
            return;
        }

        $seedUser = User::first();
        if (!$seedUser) {
            $seedUser = User::create([
                'id' => (string) Str::uuid(),
                'keycloak_sub' => (string) Str::uuid(),
                'email' => 'system.seed@modmr.gov.bd',
                'full_name_en' => 'IDMS Seed Coordinator',
                'role_code' => 'ROLE_PIO',
                'administrative_unit_id' => $dacope->id,
                'is_active' => true,
            ]);
        }

        $cppOrg = VolunteerOrganization::firstOrCreate(
            ['org_code' => 'ORG-CPP-KHL-01'],
            [
                'name_en' => 'Cyclone Preparedness Programme (CPP) - Dacope Unit',
                'name_bn' => 'ঘূর্ণিঝড় প্রস্তুতি কর্মসূচি (সিপিপি) - দাকোপ ইউনিট',
                'org_type' => 'CPP',
                'registration_no' => 'CPP-MODMR-404712',
                'administrative_unit_id' => $dacope->id,
                'address' => 'Upazila Parishad Complex, Dacope, Khulna',
                'contact_person_name' => 'Md. Kamrul Hasan',
                'contact_person_designation' => 'CPP Upazila Team Leader',
                'contact_person_phone' => '01711000101',
                'contact_person_email' => 'cpp.dacope@modmr.gov.bd',
                'description' => 'MoDMR-BDRCS joint coastal early warning and evacuation volunteer network.',
                'verification_status' => 'VERIFIED',
                'verified_by' => $seedUser->id,
                'verified_at' => now()->subDays(10),
                'is_active' => true,
                'created_by' => $seedUser->id,
            ]
        );

        foreach (array_filter([$dacope->id, $chalna->id]) as $unitId) {
            VolunteerOrganizationCoverage::firstOrCreate([
                'organization_id' => $cppOrg->id,
                'administrative_unit_id' => $unitId,
            ]);
        }

        $bdrcsOrg = VolunteerOrganization::firstOrCreate(
            ['org_code' => 'ORG-BDRCS-KHL-01'],
            [
                'name_en' => 'Bangladesh Red Crescent Society - Khulna Unit',
                'name_bn' => 'বাংলাদেশ রেড ক্রিসেন্ট সোসাইটি - খুলনা ইউনিট',
                'org_type' => 'BDRCS',
                'registration_no' => 'BDRCS-KHL-1973',
                'administrative_unit_id' => $khulna->id,
                'address' => 'Boyra Main Road, Khulna',
                'contact_person_name' => 'Farhana Yasmin',
                'contact_person_designation' => 'Unit Youth & Volunteer Officer',
                'contact_person_phone' => '01711000202',
                'contact_person_email' => 'khulna@bdrcs.example.org',
                'description' => 'District emergency first aid, shelter medical support, and relief coordination volunteers.',
                'verification_status' => 'VERIFIED',
                'verified_by' => $seedUser->id,
                'verified_at' => now()->subDays(8),
                'is_active' => true,
                'created_by' => $seedUser->id,
            ]
        );

        foreach (array_filter([$khulna->id, $dacope->id]) as $unitId) {
            VolunteerOrganizationCoverage::firstOrCreate([
                'organization_id' => $bdrcsOrg->id,
                'administrative_unit_id' => $unitId,
            ]);
        }

        if ($barishal) {
            $scoutOrg = VolunteerOrganization::firstOrCreate(
                ['org_code' => 'ORG-SCOUT-BAR-01'],
                [
                    'name_en' => 'Bangladesh Scouts Disaster Response Rover Crew - Barishal',
                    'name_bn' => 'বাংলাদেশ স্কাউটস দুর্যোগ সাড়াদান রোভার দল - বরিশাল',
                    'org_type' => 'SCOUT',
                    'registration_no' => 'BS-ROVER-10',
                    'administrative_unit_id' => $barishal->id,
                    'address' => 'Band Road, Barishal',
                    'contact_person_name' => 'Tanvir Ahmed',
                    'contact_person_designation' => 'District Rover Leader',
                    'contact_person_phone' => '01711000303',
                    'contact_person_email' => 'rover.barishal@scouts.example.org',
                    'description' => 'Community awareness, evacuation assistance, and logistics support.',
                    'verification_status' => 'VERIFIED',
                    'verified_by' => $seedUser->id,
                    'verified_at' => now()->subDays(6),
                    'is_active' => true,
                    'created_by' => $seedUser->id,
                ]
            );

            VolunteerOrganizationCoverage::firstOrCreate([
                'organization_id' => $scoutOrg->id,
                'administrative_unit_id' => $barishal->id,
            ]);
        }

        // Seed Sample Volunteers (with proper PII hashing & encryption)
        $vol1 = Volunteer::firstOrCreate(
            ['volunteer_code' => 'VOL-CPP-404712-001'],
            [
                'administrative_unit_id' => $chalna->id,
                'organization_id' => $cppOrg->id,
                'primary_category_id' => $categories['EVACUATION_SUPPORT']->id,
                'full_name_en' => 'Md. Jahangir Alam',
                'full_name_bn' => 'মো. জাহাঙ্গীর আলম',
                'gender' => 'MALE',
                'date_of_birth' => '1992-04-15',
                'blood_group' => 'O+',
                'nid_hash' => Volunteer::hashNid('1992471234567'),
                'nid_encrypted' => Crypt::encryptString('1992471234567'),
                'nid_masked' => Volunteer::maskNid('1992471234567'),
                'mobile_hash' => Volunteer::hashMobile('01712345601'),
                'mobile_encrypted' => Crypt::encryptString('01712345601'),
                'mobile_masked' => Volunteer::maskMobile('01712345601'),
                'email_encrypted' => Crypt::encryptString('jahangir.cpp@example.org'),
                'email_masked' => Volunteer::maskEmail('jahangir.cpp@example.org'),
                'present_address' => 'Ward 03, Chalna Union, Dacope, Khulna',
                'permanent_address' => 'Ward 03, Chalna Union, Dacope, Khulna',
                'ward_village' => 'Chalna Bazar Ward 03',
                'emergency_contact_name' => 'Rasheda Begum',
                'emergency_contact_relation' => 'Spouse',
                'emergency_contact_encrypted' => Crypt::encryptString('01812345601'),
                'emergency_contact_masked' => Volunteer::maskMobile('01812345601'),
                'identity_verification_status' => 'VERIFIED',
                'organization_verification_status' => 'VERIFIED',
                'training_verification_status' => 'VERIFIED',
                'overall_verification_status' => Volunteer::VERIFICATION_STATUS_VERIFIED,
                'submitted_by' => $seedUser->id,
                'submitted_at' => now()->subDays(12),
                'verified_by' => $seedUser->id,
                'verified_at' => now()->subDays(11),
                'approved_by' => $seedUser->id,
                'approved_at' => now()->subDays(11),
                'availability_status' => Volunteer::AVAILABILITY_AVAILABLE,
                'latitude' => 22.5964,
                'longitude' => 89.5123,
                'location_consent_given' => true,
                'created_by' => $seedUser->id,
            ]
        );

        VolunteerSkillAssignment::firstOrCreate(
            ['volunteer_id' => $vol1->id, 'skill_id' => $skills['CYCLONE_EVACUATION']->id],
            [
                'proficiency_level' => 'ADVANCED',
                'certification_level' => 'CPP_UNIT_CERTIFIED',
                'certified_by' => 'CPP Directorate',
                'issued_at' => '2024-03-10',
                'verification_status' => 'VERIFIED',
            ]
        );
        VolunteerSkillAssignment::firstOrCreate(
            ['volunteer_id' => $vol1->id, 'skill_id' => $skills['VHF_RADIO_OPS']->id],
            [
                'proficiency_level' => 'INTERMEDIATE',
                'certification_level' => 'LEVEL_2',
                'certified_by' => 'CPP Communications Wing',
                'issued_at' => '2024-04-01',
                'verification_status' => 'VERIFIED',
            ]
        );

        $vol2 = Volunteer::firstOrCreate(
            ['volunteer_code' => 'VOL-BDRCS-4047-002'],
            [
                'administrative_unit_id' => $dacope->id,
                'organization_id' => $bdrcsOrg->id,
                'primary_category_id' => $categories['FIRST_AID']->id,
                'full_name_en' => 'Nusrat Jahan',
                'full_name_bn' => 'নুসরাত জাহান',
                'gender' => 'FEMALE',
                'date_of_birth' => '1997-09-22',
                'blood_group' => 'A+',
                'nid_hash' => Volunteer::hashNid('1997472345678'),
                'nid_encrypted' => Crypt::encryptString('1997472345678'),
                'nid_masked' => Volunteer::maskNid('1997472345678'),
                'mobile_hash' => Volunteer::hashMobile('01712345602'),
                'mobile_encrypted' => Crypt::encryptString('01712345602'),
                'mobile_masked' => Volunteer::maskMobile('01712345602'),
                'email_encrypted' => Crypt::encryptString('nusrat.bdrcs@example.org'),
                'email_masked' => Volunteer::maskEmail('nusrat.bdrcs@example.org'),
                'present_address' => 'Dacope Sadar, Khulna',
                'permanent_address' => 'Dacope Sadar, Khulna',
                'ward_village' => 'Dacope Sadar',
                'emergency_contact_name' => 'Shahidul Islam',
                'emergency_contact_relation' => 'Father',
                'emergency_contact_encrypted' => Crypt::encryptString('01912345602'),
                'emergency_contact_masked' => Volunteer::maskMobile('01912345602'),
                'identity_verification_status' => 'VERIFIED',
                'organization_verification_status' => 'VERIFIED',
                'training_verification_status' => 'VERIFIED',
                'overall_verification_status' => Volunteer::VERIFICATION_STATUS_VERIFIED,
                'submitted_by' => $seedUser->id,
                'submitted_at' => now()->subDays(9),
                'verified_by' => $seedUser->id,
                'verified_at' => now()->subDays(8),
                'approved_by' => $seedUser->id,
                'approved_at' => now()->subDays(8),
                'availability_status' => Volunteer::AVAILABILITY_DEPLOYED,
                'latitude' => 22.5731,
                'longitude' => 89.5145,
                'location_consent_given' => true,
                'created_by' => $seedUser->id,
            ]
        );

        VolunteerSkillAssignment::firstOrCreate(
            ['volunteer_id' => $vol2->id, 'skill_id' => $skills['FIRST_AID_CPR']->id],
            [
                'proficiency_level' => 'ADVANCED',
                'certification_level' => 'BDRCS_FIRST_AID_L2',
                'certified_by' => 'BDRCS Training Institute',
                'issued_at' => '2024-02-15',
                'verification_status' => 'VERIFIED',
            ]
        );

        if ($vol1->verificationLogs()->count() === 0) {
            VolunteerVerificationLog::create([
                'volunteer_id' => $vol1->id,
                'pillar' => 'OVERALL',
                'action' => 'APPROVED',
                'from_status' => 'PENDING_VERIFICATION',
                'to_status' => 'VERIFIED',
                'remarks' => 'Verified CPP unit roster member.',
                'actor_user_id' => $seedUser->id,
                'created_at' => now()->subDays(11),
            ]);
        }

        // Seed Sample Training Program, Session & Enrollment
        $program = VolunteerTrainingProgram::firstOrCreate(
            ['program_code' => 'TRN-PROG-FA-01'],
            [
                'title_en' => 'Emergency First Aid & Cyclone Shelter Triage',
                'title_bn' => 'জরুরি প্রাথমিক চিকিৎসা ও আশ্রয়কেন্দ্র ট্রায়াজ প্রশিক্ষণ',
                'category_id' => $categories['FIRST_AID']->id,
                'skill_id' => $skills['FIRST_AID_CPR']->id,
                'provider_organization_id' => $bdrcsOrg->id,
                'provider_name' => 'Bangladesh Red Crescent Society',
                'duration_hours' => 16,
                'validity_months' => 24,
                'description' => 'Hands-on emergency trauma care, CPR, drowning resuscitation, and shelter triage.',
                'is_active' => true,
                'created_by' => $seedUser->id,
            ]
        );

        $session = VolunteerTrainingSession::firstOrCreate(
            ['session_code' => 'TRN-SES-404712-01'],
            [
                'program_id' => $program->id,
                'administrative_unit_id' => $dacope->id,
                'venue' => 'Dacope Upazila Disaster Training Hall',
                'start_date' => now()->subDays(20)->toDateString(),
                'end_date' => now()->subDays(18)->toDateString(),
                'trainer_name' => 'Dr. Mahbubur Rahman',
                'trainer_contact' => '01711999888',
                'max_participants' => 35,
                'status' => 'COMPLETED',
                'created_by' => $seedUser->id,
            ]
        );

        VolunteerTrainingEnrollment::firstOrCreate(
            ['session_id' => $session->id, 'volunteer_id' => $vol2->id],
            [
                'enrollment_status' => 'ATTENDED',
                'attendance_hours' => 16.0,
                'assessment_score' => 92.5,
                'passed' => true,
                'certificate_no' => 'CERT-BDRCS-2026-0001',
                'issued_at' => now()->subDays(17)->toDateString(),
                'expires_at' => now()->addMonths(24)->toDateString(),
                'verified_by' => $seedUser->id,
                'verified_at' => now()->subDays(17),
            ]
        );

        // Seed Sample Deployment & Attendance linked to Disaster Event if available
        $disaster = DisasterEvent::orderByDesc('declaration_date')->first();
        if ($disaster) {
            $dep = VolunteerDeployment::firstOrCreate(
                ['deployment_no' => 'VDEP-2026-0001'],
                [
                    'volunteer_id' => $vol2->id,
                    'disaster_event_id' => $disaster->id,
                    'administrative_unit_id' => $dacope->id,
                    'deployment_location' => 'Chalna Cyclone Shelter Medical Desk',
                    'latitude' => 22.5960,
                    'longitude' => 89.5120,
                    'assigned_role' => 'FIRST_AID',
                    'assigned_duties' => 'Provide emergency first aid and maternal triage at shelter medical post.',
                    'start_date' => now()->subDays(2)->toDateString(),
                    'end_date' => now()->addDays(5)->toDateString(),
                    'supervisor_name' => 'PIO Dacope',
                    'supervisor_contact' => '01711000404',
                    'deployment_status' => VolunteerDeployment::STATUS_ACTIVE,
                    'assigned_by' => $seedUser->id,
                ]
            );

            VolunteerAttendance::firstOrCreate(
                [
                    'deployment_id' => $dep->id,
                    'volunteer_id' => $vol2->id,
                    'attendance_date' => now()->subDay()->toDateString(),
                ],
                [
                    'administrative_unit_id' => $dacope->id,
                    'check_in_at' => now()->subDay()->setTime(8, 30),
                    'check_out_at' => now()->subDay()->setTime(16, 45),
                    'duration_minutes' => 495,
                    'gps_consent_given' => true,
                    'check_in_lat' => 22.5960,
                    'check_in_lng' => 89.5120,
                    'check_out_lat' => 22.5960,
                    'check_out_lng' => 89.5120,
                    'activity_summary' => 'Treated 28 evacuees for minor injuries and dehydration; distributed ORS packets.',
                    'tasks_completed' => 'Shelter first aid triage and referral coordination.',
                    'beneficiaries_assisted' => 28,
                    'confirmation_status' => VolunteerAttendance::CONFIRMATION_CONFIRMED,
                    'supervisor_confirmed_by' => $seedUser->id,
                    'supervisor_confirmed_at' => now()->subDay()->setTime(18, 0),
                    'supervisor_remarks' => 'Verified by shelter in-charge.',
                    'recorded_by' => $seedUser->id,
                ]
            );
        }
    }
}
