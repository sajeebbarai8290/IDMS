<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DisasterEventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tableName = DB::getDriverName() === 'pgsql' ? 'disaster.disaster_events' : 'disaster_events';

        $events = [
            [
                'id' => '20000000-0000-0000-0000-000000000001',
                'disaster_code' => 'CYC-REMAL-2024',
                'name_en' => 'Severe Cyclonic Storm Remal',
                'name_bn' => 'প্রবল ঘূর্ণিঝড় রিমাল',
                'disaster_type' => 'CYCLONE',
                'severity_level' => 'GREAT_DANGER',
                'lifecycle_phase' => 'RECOVERY',
                'declaration_date' => '2024-05-26 12:00:00',
            ],
            [
                'id' => '20000000-0000-0000-0000-000000000002',
                'disaster_code' => 'FLD-MONSOON-2024',
                'name_en' => 'Eastern Flash Flood 2024',
                'name_bn' => 'পূর্বাঞ্চলীয় আকস্মিক বন্যা ২০২৪',
                'disaster_type' => 'FLASH_FLOOD',
                'severity_level' => 'DANGER',
                'lifecycle_phase' => 'RESPONSE',
                'declaration_date' => '2024-08-20 08:00:00',
            ],
            [
                'id' => '20000000-0000-0000-0000-000000000003',
                'disaster_code' => 'COLD-WAVE-2025',
                'name_en' => 'Severe Winter Cold Wave 2025',
                'name_bn' => 'তীব্র শৈত্যপ্রবাহ ২০২৫',
                'disaster_type' => 'COLD_WAVE',
                'severity_level' => 'WARNING',
                'lifecycle_phase' => 'ACTIVE',
                'declaration_date' => '2025-01-10 06:00:00',
            ],
        ];

        foreach ($events as $event) {
            DB::table($tableName)->insertOrIgnore(array_merge($event, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}
