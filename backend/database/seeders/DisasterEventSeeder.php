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
        $this->call(DisasterCategorySeeder::class);

        $isPgsql = DB::getDriverName() === 'pgsql';
        $tableName = $isPgsql ? 'disaster.disaster_events' : 'disaster_events';
        $impactsTable = $isPgsql ? 'disaster.disaster_impacts' : 'disaster_impacts';
        $locationsTable = $isPgsql ? 'disaster.disaster_locations' : 'disaster_locations';
        $warningsTable = $isPgsql ? 'disaster.disaster_warnings' : 'disaster_warnings';
        $timelinesTable = $isPgsql ? 'disaster.disaster_timelines' : 'disaster_timelines';
        $adminUnitsTable = $isPgsql ? 'core.administrative_units' : 'administrative_units';

        $khulnaUnit = DB::table($adminUnitsTable)->where('code', '47')->first();
        $dacopeUnit = DB::table($adminUnitsTable)->where('code', '12')->first();
        $barishalUnit = DB::table($adminUnitsTable)->where('code', '10')->first();

        $events = [
            [
                'id' => '20000000-0000-0000-0000-000000000001',
                'category_id' => '30000000-0000-0000-0000-000000000003',
                'disaster_code' => 'CYC-REMAL-2024',
                'name_en' => 'Severe Cyclonic Storm Remal',
                'name_bn' => 'প্রবল ঘূর্ণিঝড় রিমাল',
                'disaster_type' => 'CYCLONE',
                'subtype' => 'SEVERE_CYCLONE',
                'description_en' => 'Severe cyclonic storm making landfall along the southwestern coastal belt of Khulna and Barishal with storm surge.',
                'description_bn' => 'খুলনা ও বরিশাল উপকূলীয় অঞ্চলে জলোচ্ছ্বাসসহ আঘাত হানা প্রবল ঘূর্ণিঝড় রিমাল।',
                'severity_level' => 'GREAT_DANGER',
                'lifecycle_phase' => 'RECOVERY',
                'declaration_date' => '2024-05-26 12:00:00',
                'start_date' => '2024-05-26',
                'end_date' => '2024-05-29',
                'epicenter_lat' => 22.4500000,
                'epicenter_lng' => 89.5500000,
                'affected_areas_summary' => 'Khulna, Satkhira, Bagerhat, Barishal Coastal Belt',
                'affected_zone_geojson' => json_encode([
                    'type' => 'Polygon',
                    'coordinates' => [[[89.10, 22.10], [89.95, 22.10], [89.95, 22.85], [89.10, 22.85], [89.10, 22.10]]],
                ]),
                'is_national' => true,
                'is_emergency_declared' => true,
                'impact' => [
                    'affected_population' => 3758000,
                    'affected_households' => 820000,
                    'deaths_count' => 16,
                    'injured_count' => 420,
                    'missing_count' => 3,
                    'damaged_houses_fully' => 35483,
                    'damaged_houses_partially' => 114992,
                    'damaged_infrastructure_count' => 640,
                    'agricultural_loss_acres' => 125000.00,
                    'agricultural_loss_bdt' => 450000000.00,
                    'livestock_loss_count' => 18500,
                    'livestock_loss_bdt' => 82000000.00,
                    'total_economic_loss_bdt' => 1250000000.00,
                ],
                'unit_ids' => array_values(array_filter([$khulnaUnit?->id, $dacopeUnit?->id, $barishalUnit?->id])),
            ],
            [
                'id' => '20000000-0000-0000-0000-000000000002',
                'category_id' => '30000000-0000-0000-0000-000000000002',
                'disaster_code' => 'FLD-MONSOON-2024',
                'name_en' => 'Eastern Flash Flood 2024',
                'name_bn' => 'পূর্বাঞ্চলীয় আকস্মিক বন্যা ২০২৪',
                'disaster_type' => 'FLASH_FLOOD',
                'subtype' => 'HILL_FLASH_FLOOD',
                'description_en' => 'Rapid flash flooding across eastern river basins triggered by heavy upstream rainfall.',
                'description_bn' => 'উজানের ভারী বর্ষণের ফলে পূর্বাঞ্চলীয় নদী অববাহিকায় আকস্মিক বন্যা।',
                'severity_level' => 'DANGER',
                'lifecycle_phase' => 'RESPONSE_ONGOING',
                'declaration_date' => '2024-08-20 08:00:00',
                'start_date' => '2024-08-20',
                'end_date' => null,
                'epicenter_lat' => 23.1500000,
                'epicenter_lng' => 91.2800000,
                'affected_areas_summary' => 'Feni, Cumilla, Noakhali, Chattogram, Sylhet',
                'affected_zone_geojson' => json_encode([
                    'type' => 'Polygon',
                    'coordinates' => [[[90.85, 22.80], [91.65, 22.80], [91.65, 23.55], [90.85, 23.55], [90.85, 22.80]]],
                ]),
                'is_national' => true,
                'is_emergency_declared' => false,
                'impact' => [
                    'affected_population' => 1450000,
                    'affected_households' => 310000,
                    'deaths_count' => 27,
                    'injured_count' => 185,
                    'missing_count' => 5,
                    'damaged_houses_fully' => 14200,
                    'damaged_houses_partially' => 48000,
                    'damaged_infrastructure_count' => 310,
                    'agricultural_loss_acres' => 84000.00,
                    'agricultural_loss_bdt' => 290000000.00,
                    'livestock_loss_count' => 9200,
                    'livestock_loss_bdt' => 41000000.00,
                    'total_economic_loss_bdt' => 680000000.00,
                ],
                'unit_ids' => array_values(array_filter([$barishalUnit?->id])),
            ],
            [
                'id' => '20000000-0000-0000-0000-000000000003',
                'category_id' => '30000000-0000-0000-0000-000000000010',
                'disaster_code' => 'COLD-WAVE-2025',
                'name_en' => 'Severe Winter Cold Wave 2025',
                'name_bn' => 'তীব্র শৈত্যপ্রবাহ ২০২৫',
                'disaster_type' => 'COLD_WAVE',
                'subtype' => 'SEVERE_COLD_WAVE',
                'description_en' => 'Prolonged winter cold wave impacting vulnerable rural households across northern and western districts.',
                'description_bn' => 'উত্তর ও পশ্চিমাঞ্চলীয় জেলাগুলোতে প্রবাহিত তীব্র শৈত্যপ্রবাহ।',
                'severity_level' => 'WARNING',
                'lifecycle_phase' => 'ACTIVE',
                'declaration_date' => '2025-01-10 06:00:00',
                'start_date' => '2025-01-10',
                'end_date' => null,
                'epicenter_lat' => 25.7439000,
                'epicenter_lng' => 89.2752000,
                'affected_areas_summary' => 'Rangpur, Dinajpur, Kurigram, Panchagarh',
                'affected_zone_geojson' => json_encode([
                    'type' => 'Polygon',
                    'coordinates' => [[[88.50, 25.30], [89.80, 25.30], [89.80, 26.40], [88.50, 26.40], [88.50, 25.30]]],
                ]),
                'is_national' => true,
                'is_emergency_declared' => false,
                'impact' => [
                    'affected_population' => 420000,
                    'affected_households' => 98000,
                    'deaths_count' => 4,
                    'injured_count' => 65,
                    'missing_count' => 0,
                    'damaged_houses_fully' => 0,
                    'damaged_houses_partially' => 120,
                    'damaged_infrastructure_count' => 15,
                    'agricultural_loss_acres' => 12500.00,
                    'agricultural_loss_bdt' => 42000000.00,
                    'livestock_loss_count' => 850,
                    'livestock_loss_bdt' => 4500000.00,
                    'total_economic_loss_bdt' => 75000000.00,
                ],
                'unit_ids' => array_values(array_filter([$khulnaUnit?->id])),
            ],
        ];

        foreach ($events as $event) {
            $impact = $event['impact'];
            $unitIds = $event['unit_ids'];
            unset($event['impact'], $event['unit_ids']);

            $existing = DB::table($tableName)->where('id', $event['id'])->first();
            if ($existing) {
                DB::table($tableName)->where('id', $event['id'])->update(array_merge($event, [
                    'updated_at' => now(),
                ]));
            } else {
                DB::table($tableName)->insert(array_merge($event, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }

            if ($isPgsql && $event['epicenter_lat'] !== null && $event['epicenter_lng'] !== null) {
                DB::statement(
                    "UPDATE {$tableName} SET location_geom = ST_SetSRID(ST_MakePoint(?, ?), 4326), affected_zone_geom = ST_Multi(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)) WHERE id = ?",
                    [(float) $event['epicenter_lng'], (float) $event['epicenter_lat'], $event['affected_zone_geojson'], $event['id']]
                );
            }

            if (!DB::table($impactsTable)->where('disaster_event_id', $event['id'])->exists()) {
                DB::table($impactsTable)->insert(array_merge($impact, [
                    'id' => (string) Str::uuid(),
                    'disaster_event_id' => $event['id'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }

            foreach ($unitIds as $unitId) {
                DB::table($locationsTable)->insertOrIgnore([
                    'id' => (string) Str::uuid(),
                    'disaster_event_id' => $event['id'],
                    'administrative_unit_id' => $unitId,
                    'latitude' => $event['epicenter_lat'],
                    'longitude' => $event['epicenter_lng'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table($timelinesTable)->insertOrIgnore([
                'id' => (string) Str::uuid(),
                'disaster_event_id' => $event['id'],
                'event_type' => 'CREATED',
                'to_phase' => $event['lifecycle_phase'],
                'title_en' => 'Disaster Event Registered: ' . $event['name_en'],
                'title_bn' => 'দুর্যোগ নিবন্ধিত: ' . $event['name_bn'],
                'notes' => $event['description_en'],
                'occurred_at' => $event['declaration_date'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Seed one active early warning for COLD-WAVE-2025
        DB::table($warningsTable)->insertOrIgnore([
            'id' => '40000000-0000-0000-0000-000000000001',
            'disaster_event_id' => '20000000-0000-0000-0000-000000000003',
            'warning_code' => 'WARN-BMD-2025-01',
            'warning_level' => 'WARNING',
            'title_en' => 'BMD Cold Wave Alert Bulletin #01',
            'title_bn' => 'আবহাওয়া অধিদপ্তর শৈত্যপ্রবাহ সতর্কবার্তা #০১',
            'message_en' => 'Night temperatures expected to drop below 7°C across northern districts. Keep shelters and blankets ready.',
            'message_bn' => 'উত্তরাঞ্চলীয় জেলাগুলোতে রাতের তাপমাত্রা ৭° সেলসিয়াসের নিচে নেমে যেতে পারে। আশ্রয়কেন্দ্র ও শীতবস্ত্র প্রস্তুত রাখুন।',
            'issuing_authority' => 'BMD / NEOC MoDMR',
            'issued_at' => now()->subHours(6),
            'expires_at' => now()->addDays(3),
            'affected_unit_ids' => json_encode([]),
            'status' => 'ACTIVE',
            'notification_dispatch_status' => 'DISPATCHED',
            'notification_reference' => 'MOCK-NOTIFY-SEED-001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
