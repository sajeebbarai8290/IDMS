<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdministrativeUnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Seeds realistic BBS administrative hierarchy (Division -> District -> Upazila -> Union).
     */
    public function run(): void
    {
        $tableName = DB::getDriverName() === 'pgsql' ? 'core.administrative_units' : 'administrative_units';

        // 1. National Unit
        $nationalId = '10000000-0000-0000-0000-000000000001';
        DB::table($tableName)->insertOrIgnore([
            'id' => $nationalId,
            'parent_id' => null,
            'code' => '00',
            'name_en' => 'National',
            'name_bn' => 'জাতীয় পর্যায়',
            'tier_level' => 'NATIONAL',
            'ancestry_path' => '/',
            'latitude' => 23.684994,
            'longitude' => 90.356331,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Divisions
        $divisions = [
            ['code' => '40', 'name_en' => 'Khulna', 'name_bn' => 'খুলনা', 'lat' => 22.8456, 'lng' => 89.5403],
            ['code' => '10', 'name_en' => 'Barishal', 'name_bn' => 'বরিশাল', 'lat' => 22.7010, 'lng' => 90.3535],
            ['code' => '30', 'name_en' => 'Dhaka', 'name_bn' => 'ঢাকা', 'lat' => 23.8103, 'lng' => 90.4125],
            ['code' => '20', 'name_en' => 'Chattogram', 'name_bn' => 'চট্টগ্রাম', 'lat' => 22.3569, 'lng' => 91.7832],
            ['code' => '55', 'name_en' => 'Rangpur', 'name_bn' => 'রংপুর', 'lat' => 25.7439, 'lng' => 89.2752],
            ['code' => '60', 'name_en' => 'Sylhet', 'name_bn' => 'সিলেট', 'lat' => 24.8949, 'lng' => 91.8687],
        ];

        $divisionMap = [];
        foreach ($divisions as $div) {
            $divId = (string) Str::uuid();
            $path = "/{$div['code']}/";
            DB::table($tableName)->insertOrIgnore([
                'id' => $divId,
                'parent_id' => $nationalId,
                'code' => $div['code'],
                'name_en' => $div['name_en'],
                'name_bn' => $div['name_bn'],
                'tier_level' => 'DIVISION',
                'ancestry_path' => $path,
                'latitude' => $div['lat'],
                'longitude' => $div['lng'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $divisionMap[$div['code']] = ['id' => $divId, 'path' => $path];
        }

        // 3. Khulna District
        $khulnaDistId = (string) Str::uuid();
        $khulnaDistPath = $divisionMap['40']['path'] . '47/';
        DB::table($tableName)->insertOrIgnore([
            'id' => $khulnaDistId,
            'parent_id' => $divisionMap['40']['id'],
            'code' => '47',
            'name_en' => 'Khulna District',
            'name_bn' => 'খুলনা জেলা',
            'tier_level' => 'DISTRICT',
            'ancestry_path' => $khulnaDistPath,
            'latitude' => 22.8456,
            'longitude' => 89.5403,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Satkhira District
        $satkhiraDistId = (string) Str::uuid();
        $satkhiraDistPath = $divisionMap['40']['path'] . '87/';
        DB::table($tableName)->insertOrIgnore([
            'id' => $satkhiraDistId,
            'parent_id' => $divisionMap['40']['id'],
            'code' => '87',
            'name_en' => 'Satkhira District',
            'name_bn' => 'সাতক্ষীরা জেলা',
            'tier_level' => 'DISTRICT',
            'ancestry_path' => $satkhiraDistPath,
            'latitude' => 22.7185,
            'longitude' => 89.0705,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 4. Upazilas in Khulna
        // Dacope Upazila
        $dacopeId = (string) Str::uuid();
        $dacopePath = $khulnaDistPath . '12/';
        DB::table($tableName)->insertOrIgnore([
            'id' => $dacopeId,
            'parent_id' => $khulnaDistId,
            'code' => '12',
            'name_en' => 'Dacope Upazila',
            'name_bn' => 'দাকোপ উপজেলা',
            'tier_level' => 'UPAZILA',
            'ancestry_path' => $dacopePath,
            'latitude' => 22.5721,
            'longitude' => 89.5106,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Koyra Upazila
        $koyraId = (string) Str::uuid();
        $koyraPath = $khulnaDistPath . '40/';
        DB::table($tableName)->insertOrIgnore([
            'id' => $koyraId,
            'parent_id' => $khulnaDistId,
            'code' => '40',
            'name_en' => 'Koyra Upazila',
            'name_bn' => 'কয়রা উপজেলা',
            'tier_level' => 'UPAZILA',
            'ancestry_path' => $koyraPath,
            'latitude' => 22.3413,
            'longitude' => 89.3004,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 5. Unions in Dacope
        $unions = [
            ['code' => '35', 'name_en' => 'Chalna Union', 'name_bn' => 'চালনা ইউনিয়ন', 'lat' => 22.6021, 'lng' => 89.5312],
            ['code' => '42', 'name_en' => 'Banishanta Union', 'name_bn' => 'বানীশান্তা ইউনিয়ন', 'lat' => 22.5401, 'lng' => 89.5789],
            ['code' => '50', 'name_en' => 'Bajua Union', 'name_bn' => 'বাজুয়া ইউনিয়ন', 'lat' => 22.5122, 'lng' => 89.4988],
            ['code' => '65', 'name_en' => 'Sutarkhali Union', 'name_bn' => 'সুতারখালী ইউনিয়ন', 'lat' => 22.4219, 'lng' => 89.4511],
        ];

        foreach ($unions as $union) {
            $unionId = (string) Str::uuid();
            $path = $dacopePath . $union['code'] . '/';
            DB::table($tableName)->insertOrIgnore([
                'id' => $unionId,
                'parent_id' => $dacopeId,
                'code' => $union['code'],
                'name_en' => $union['name_en'],
                'name_bn' => $union['name_bn'],
                'tier_level' => 'UNION',
                'ancestry_path' => $path,
                'latitude' => $union['lat'],
                'longitude' => $union['lng'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
