<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DisasterCategorySeeder extends Seeder
{
    public function run(): void
    {
        $tableName = DB::getDriverName() === 'pgsql' ? 'disaster.disaster_categories' : 'disaster_categories';

        $categories = [
            [
                'id' => '30000000-0000-0000-0000-000000000001',
                'code' => 'FLOOD',
                'name_en' => 'Flood',
                'name_bn' => 'বন্যা',
                'icon' => 'bi-water',
                'color_hex' => '#0d6efd',
                'subtypes' => json_encode([
                    ['code' => 'MONSOON_FLOOD', 'name_en' => 'Monsoon River Flood', 'name_bn' => 'মৌসুমি নদ-নদীর বন্যা'],
                    ['code' => 'TIDAL_SURGE', 'name_en' => 'Coastal Tidal Flood', 'name_bn' => 'উপকূলীয় জলোচ্ছ্বাস বন্যা'],
                    ['code' => 'URBAN_WATERLOGGING', 'name_en' => 'Urban Waterlogging', 'name_bn' => 'নগর জলাবদ্ধতা'],
                ]),
            ],
            [
                'id' => '30000000-0000-0000-0000-000000000002',
                'code' => 'FLASH_FLOOD',
                'name_en' => 'Flash Flood',
                'name_bn' => 'আকস্মিক বন্যা',
                'icon' => 'bi-tsunami',
                'color_hex' => '#0dcaf0',
                'subtypes' => json_encode([
                    ['code' => 'HAOR_FLASH_FLOOD', 'name_en' => 'Haor Flash Flood', 'name_bn' => 'হাওর আকস্মিক বন্যা'],
                    ['code' => 'HILL_FLASH_FLOOD', 'name_en' => 'Hill Slope Flash Flood', 'name_bn' => 'পাহাড়ি ঢল'],
                ]),
            ],
            [
                'id' => '30000000-0000-0000-0000-000000000003',
                'code' => 'CYCLONE',
                'name_en' => 'Cyclone',
                'name_bn' => 'ঘূর্ণিঝড়',
                'icon' => 'bi-tornado',
                'color_hex' => '#dc3545',
                'subtypes' => json_encode([
                    ['code' => 'DEPRESSION', 'name_en' => 'Deep Depression', 'name_bn' => 'গভীর নিম্নচাপ'],
                    ['code' => 'SEVERE_CYCLONE', 'name_en' => 'Severe Cyclonic Storm', 'name_bn' => 'প্রবল ঘূর্ণিঝড়'],
                    ['code' => 'SUPER_CYCLONE', 'name_en' => 'Super Cyclonic Storm', 'name_bn' => 'অতি প্রবল ঘূর্ণিঝড়'],
                ]),
            ],
            [
                'id' => '30000000-0000-0000-0000-000000000004',
                'code' => 'DROUGHT',
                'name_en' => 'Drought',
                'name_bn' => 'খরা',
                'icon' => 'bi-sun-fill',
                'color_hex' => '#fd7e14',
                'subtypes' => json_encode([
                    ['code' => 'AGRICULTURAL_DROUGHT', 'name_en' => 'Agricultural Drought', 'name_bn' => 'কৃষি খরা'],
                    ['code' => 'HYDROLOGICAL_DROUGHT', 'name_en' => 'Drinking Water Scarcity', 'name_bn' => 'ভূগর্ভস্থ পানির সংকট'],
                ]),
            ],
            [
                'id' => '30000000-0000-0000-0000-000000000005',
                'code' => 'EARTHQUAKE',
                'name_en' => 'Earthquake',
                'name_bn' => 'ভূমিকম্প',
                'icon' => 'bi-activity',
                'color_hex' => '#6f42c1',
                'subtypes' => json_encode([
                    ['code' => 'MODERATE_QUAKE', 'name_en' => 'Moderate Earthquake', 'name_bn' => 'মাঝারি ভূমিকম্প'],
                    ['code' => 'MAJOR_QUAKE', 'name_en' => 'Major Earthquake', 'name_bn' => 'তীব্র ভূমিকম্প'],
                ]),
            ],
            [
                'id' => '30000000-0000-0000-0000-000000000006',
                'code' => 'LANDSLIDE',
                'name_en' => 'Landslide',
                'name_bn' => 'ভূমিধস',
                'icon' => 'bi-triangle-half',
                'color_hex' => '#795548',
                'subtypes' => json_encode([
                    ['code' => 'HILL_SLOPE_COLLAPSE', 'name_en' => 'Hill Slope Collapse', 'name_bn' => 'পাহাড় ধস'],
                    ['code' => 'MUDSLIDE', 'name_en' => 'Monsoon Mudslide', 'name_bn' => 'কাদা ধস'],
                ]),
            ],
            [
                'id' => '30000000-0000-0000-0000-000000000007',
                'code' => 'FIRE',
                'name_en' => 'Fire Incident',
                'name_bn' => 'অগ্নিকাণ্ড',
                'icon' => 'bi-fire',
                'color_hex' => '#e83e8c',
                'subtypes' => json_encode([
                    ['code' => 'URBAN_FIRE', 'name_en' => 'Urban / Settlement Fire', 'name_bn' => 'শহুরে / বস্তি অগ্নিকাণ্ড'],
                    ['code' => 'INDUSTRIAL_MARKET_FIRE', 'name_en' => 'Industrial / Market Fire', 'name_bn' => 'শিল্প ও বাজার অগ্নিকাণ্ড'],
                ]),
            ],
            [
                'id' => '30000000-0000-0000-0000-000000000008',
                'code' => 'RIVER_EROSION',
                'name_en' => 'Riverbank Erosion',
                'name_bn' => 'নদী ভাঙন',
                'icon' => 'bi-bezier2',
                'color_hex' => '#20c997',
                'subtypes' => json_encode([
                    ['code' => 'RIVERBANK_COLLAPSE', 'name_en' => 'Major Riverbank Collapse', 'name_bn' => 'নদী তীর ভাঙন'],
                    ['code' => 'EMBANKMENT_BREACH', 'name_en' => 'Embankment Breach', 'name_bn' => 'বেড়িবাঁধ ভাঙন'],
                ]),
            ],
            [
                'id' => '30000000-0000-0000-0000-000000000009',
                'code' => 'LIGHTNING',
                'name_en' => 'Lightning & Norwester',
                'name_bn' => 'বজ্রপাত ও কালবৈশাখী',
                'icon' => 'bi-lightning-charge-fill',
                'color_hex' => '#ffc107',
                'subtypes' => json_encode([
                    ['code' => 'LIGHTNING_STRIKE', 'name_en' => 'Severe Lightning Storm', 'name_bn' => 'তীব্র বজ্রপাত'],
                    ['code' => 'KALBOISHAKHI', 'name_en' => 'Norwester Squall', 'name_bn' => 'কালবৈশাখী ঝড়'],
                ]),
            ],
            [
                'id' => '30000000-0000-0000-0000-000000000010',
                'code' => 'COLD_WAVE',
                'name_en' => 'Cold Wave',
                'name_bn' => 'শৈত্যপ্রবাহ',
                'icon' => 'bi-thermometer-snow',
                'color_hex' => '#17a2b8',
                'subtypes' => json_encode([
                    ['code' => 'SEVERE_COLD_WAVE', 'name_en' => 'Severe Cold Wave', 'name_bn' => 'তীব্র শৈত্যপ্রবাহ'],
                ]),
            ],
        ];

        foreach ($categories as $cat) {
            DB::table($tableName)->insertOrIgnore(array_merge($cat, [
                'is_system' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}
