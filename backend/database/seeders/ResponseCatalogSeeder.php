<?php

namespace Database\Seeders;

use App\Core\Models\AdministrativeUnit;
use App\Modules\Response\Models\ResourceAsset;
use App\Modules\Response\Models\ResponseActivityType;
use App\Modules\Response\Models\ResponseCenter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ResponseCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $activities = [
            [
                'code' => 'SEARCH_RESCUE',
                'name_en' => 'Search and Rescue (SAR)',
                'name_bn' => 'অনুসন্ধান ও উদ্ধার অভিযান',
                'description' => 'Water/structural search and life-saving rescue operations.',
                'icon' => 'life-preserver',
                'color_hex' => '#dc2626',
                'is_system' => true,
            ],
            [
                'code' => 'EMERGENCY_EVACUATION',
                'name_en' => 'Emergency Evacuation',
                'name_bn' => 'জরুরি সরিয়ে নেওয়া (ইভাকুয়েশন)',
                'description' => 'Safely evacuating vulnerable residents and livestock to shelters.',
                'icon' => 'signpost-split',
                'color_hex' => '#ea580c',
                'is_system' => true,
            ],
            [
                'code' => 'MEDICAL_ASSISTANCE',
                'name_en' => 'Medical & First Aid Assistance',
                'name_bn' => 'জরুরি চিকিৎসা ও প্রাথমিক স্বাস্থ্যসেবা',
                'description' => 'Mobile medical teams, trauma stabilization, and disease control.',
                'icon' => 'hospital',
                'color_hex' => '#e11d48',
                'is_system' => true,
            ],
            [
                'code' => 'EMERGENCY_SHELTER',
                'name_en' => 'Emergency Shelter Operations',
                'name_bn' => 'জরুরি আশ্রয়কেন্দ্র পরিচালনা',
                'description' => 'Cyclone/flood shelter readiness, lighting, sanitation, and safety.',
                'icon' => 'house-heart',
                'color_hex' => '#7c3aed',
                'is_system' => true,
            ],
            [
                'code' => 'FOOD_WATER_SUPPLY',
                'name_en' => 'Emergency Food & Water Supply',
                'name_bn' => 'জরুরি খাদ্য ও বিশুদ্ধ পানি সরবরাহ',
                'description' => 'Immediate cooked meals, dry ration packets, and mobile WATSAN supply.',
                'icon' => 'droplet',
                'color_hex' => '#0284c7',
                'is_system' => true,
            ],
            [
                'code' => 'ROAD_COMM_RESTORATION',
                'name_en' => 'Road & Communication Restoration',
                'name_bn' => 'সড়ক ও যোগাযোগ ব্যবস্থা পুনরুদ্ধার',
                'description' => 'Clearing fallen trees, temporary bridges, VHF/telecom recovery.',
                'icon' => 'cone-striped',
                'color_hex' => '#d97706',
                'is_system' => true,
            ],
            [
                'code' => 'EMERGENCY_TRANSPORT',
                'name_en' => 'Emergency Transportation & Logistics',
                'name_bn' => 'জরুরি পরিবহন ও লজিস্টিক সহায়তা',
                'description' => 'Speedboats, ambulances, and 4WD trucks transporting teams and aid.',
                'icon' => 'truck-front',
                'color_hex' => '#15803d',
                'is_system' => true,
            ],
        ];

        foreach ($activities as $act) {
            ResponseActivityType::updateOrCreate(
                ['code' => $act['code']],
                array_merge($act, ['is_active' => true])
            );
        }

        $national = AdministrativeUnit::where('tier_level', 'NATIONAL')->first();
        $khulnaDistrict = AdministrativeUnit::where('tier_level', 'DISTRICT')->where('name_en', 'like', '%Khulna%')->first()
            ?? AdministrativeUnit::where('tier_level', 'DISTRICT')->first();
        $dacopeUpazila = AdministrativeUnit::where('tier_level', 'UPAZILA')->where('name_en', 'like', '%Dacope%')->first()
            ?? AdministrativeUnit::where('tier_level', 'UPAZILA')->first();

        $isPgsql = DB::getDriverName() === 'pgsql';

        $centers = array_filter([
            $national ? [
                'center_code' => 'EOC-NEOC-01',
                'administrative_unit_id' => $national->id,
                'center_type' => 'NATIONAL_EOC',
                'name_en' => 'National Emergency Operations Center (NEOC - MoDMR)',
                'name_bn' => 'জাতীয় জরুরি সাড়াদান সমন্বয় কেন্দ্র (NEOC)',
                'address' => 'Bangladesh Secretariat / DDM Bhaban, Dhaka',
                'contact_phone' => '1090',
                'latitude' => 23.7785,
                'longitude' => 90.4084,
            ] : null,
            $khulnaDistrict ? [
                'center_code' => 'EOC-KHL-01',
                'administrative_unit_id' => $khulnaDistrict->id,
                'center_type' => 'DISTRICT_EOC',
                'name_en' => 'Khulna District Emergency Control Room',
                'name_bn' => 'খুলনা জেলা দুর্যোগ নিয়ন্ত্রণ কক্ষ',
                'address' => 'DC Office Complex, Khulna',
                'contact_phone' => '+880-41-720001',
                'latitude' => 22.8152,
                'longitude' => 89.5664,
            ] : null,
            $dacopeUpazila ? [
                'center_code' => 'EOC-DAC-01',
                'administrative_unit_id' => $dacopeUpazila->id,
                'center_type' => 'UPAZILA_COMMAND_POST',
                'name_en' => 'Dacope Upazila Forward Response Command Post',
                'name_bn' => 'দাকোপ উপজেলা অগ্রবর্তী সাড়াদান কমান্ড পোস্ট',
                'address' => 'Dacope Upazila Parishad, Khulna',
                'contact_phone' => '+880-40-265100',
                'latitude' => 22.5724,
                'longitude' => 89.5108,
            ] : null,
        ]);

        foreach ($centers as $cDef) {
            $center = ResponseCenter::updateOrCreate(
                ['center_code' => $cDef['center_code']],
                array_merge($cDef, ['is_active' => true])
            );
            if ($isPgsql && $cDef['latitude'] !== null && $cDef['longitude'] !== null) {
                DB::statement(
                    'UPDATE response.response_centers SET location_geom = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?',
                    [$cDef['longitude'], $cDef['latitude'], $center->id]
                );
            }
        }

        $assets = array_filter([
            $khulnaDistrict ? [
                'asset_code' => 'AST-BOAT-KHL-01',
                'administrative_unit_id' => $khulnaDistrict->id,
                'asset_type' => 'RESCUE_BOAT',
                'name_en' => 'High-Speed SAR Fiberglass Boat #01',
                'name_bn' => 'উচ্চগতির উদ্ধারকারী বোট #০১',
                'registration_no' => 'KHL-SAR-101',
                'capacity_info' => '18 passengers / 1.5 MT gear',
                'latitude' => 22.8101,
                'longitude' => 89.5630,
            ] : null,
            $khulnaDistrict ? [
                'asset_code' => 'AST-4WD-KHL-02',
                'administrative_unit_id' => $khulnaDistrict->id,
                'asset_type' => '4WD_VEHICLE',
                'name_en' => 'DDM Field Response 4WD Pickup',
                'name_bn' => 'ডিডিএম ফিল্ড রেসপন্স ৪ডব্লিউডি পিকআপ',
                'registration_no' => 'DM-THA-14-2091',
                'capacity_info' => '6 personnel / 1 MT relief load',
                'latitude' => 22.8155,
                'longitude' => 89.5672,
            ] : null,
            $dacopeUpazila ? [
                'asset_code' => 'AST-AMB-DAC-01',
                'administrative_unit_id' => $dacopeUpazila->id,
                'asset_type' => 'AMBULANCE',
                'name_en' => 'Dacope Emergency Medical Ambulance',
                'name_bn' => 'দাকোপ জরুরি মেডিকেল অ্যাম্বুলেন্স',
                'registration_no' => 'KHL-CHA-71-0442',
                'capacity_info' => '2 stretchers + oxygen/ALS kit',
                'latitude' => 22.5731,
                'longitude' => 89.5122,
            ] : null,
            $dacopeUpazila ? [
                'asset_code' => 'AST-WTP-DAC-01',
                'administrative_unit_id' => $dacopeUpazila->id,
                'asset_type' => 'WATER_PURIFICATION_UNIT',
                'name_en' => 'Mobile Reverse Osmosis WATSAN Unit',
                'name_bn' => 'ভ্রাম্যমাণ পানি বিশুদ্ধকরণ ইউনিট',
                'registration_no' => 'DPHE-WTP-08',
                'capacity_info' => '3,000 Liters / hour',
                'latitude' => 22.5712,
                'longitude' => 89.5098,
            ] : null,
        ]);

        foreach ($assets as $aDef) {
            ResourceAsset::updateOrCreate(
                ['asset_code' => $aDef['asset_code']],
                array_merge($aDef, ['status' => ResourceAsset::STATUS_AVAILABLE, 'is_active' => true])
            );
        }
    }
}
