<?php

namespace Database\Seeders;

use App\Core\Models\AdministrativeUnit;
use App\Core\Models\User;
use App\Modules\Relief\Models\DistributionPoint;
use App\Modules\Relief\Models\ReliefCategory;
use App\Modules\Warehouse\Models\Commodity;
use App\Modules\Warehouse\Models\StockTransaction;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Warehouse\Models\WarehouseInventory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReliefCatalogAndWarehouseSeeder extends Seeder
{
    /**
     * Seed relief categories, standard MoDMR commodities, godowns/warehouses,
     * initial inventory batches, and field distribution centers.
     */
    public function run(): void
    {
        $categories = [
            [
                'code' => 'FOOD',
                'name_en' => 'Food Assistance (GR Rice & Dry Ration)',
                'name_bn' => 'খাদ্য সহায়তা (জিআর চাল ও শুকনো খাবার)',
                'description' => 'Emergency GR food grain, fortified biscuits, lentils, and cooking staples.',
                'icon' => 'basket',
                'color_hex' => '#15803d',
                'is_system' => true,
            ],
            [
                'code' => 'DRINKING_WATER',
                'name_en' => 'Safe Drinking Water & Purification',
                'name_bn' => 'বিশুদ্ধ পানীয় জল ও পানি বিশুদ্ধকরণ',
                'description' => 'Bottled potable water, WATSAN tablets, and jerrycans.',
                'icon' => 'droplet',
                'color_hex' => '#0284c7',
                'is_system' => true,
            ],
            [
                'code' => 'CASH_ASSISTANCE',
                'name_en' => 'GR Cash Grant & Emergency Stipend',
                'name_bn' => 'জিআর নগদ অর্থ সহায়তা',
                'description' => 'Direct cash relief grant distributed in BDT per MoDMR sanction.',
                'icon' => 'banknote',
                'color_hex' => '#b45309',
                'is_system' => true,
            ],
            [
                'code' => 'CLOTHING',
                'name_en' => 'Clothing & Winter Blankets',
                'name_bn' => 'বস্ত্র ও শীতবস্ত্র (কম্বল)',
                'description' => 'Saree, lungi, children clothing, and winter blankets.',
                'icon' => 'shirt',
                'color_hex' => '#7c3aed',
                'is_system' => true,
            ],
            [
                'code' => 'SHELTER_MATERIALS',
                'name_en' => 'Emergency Shelter & CI Sheet Bundles',
                'name_bn' => 'জরুরি আশ্রয় ও ঢেউটিন সামগ্রী',
                'description' => 'Corrugated iron (CI) sheets, tarpaulins, and emergency shelter kits.',
                'icon' => 'home',
                'color_hex' => '#c2410c',
                'is_system' => true,
            ],
            [
                'code' => 'MEDICINE_SUPPLIES',
                'name_en' => 'Medical & ORS Emergency Kits',
                'name_bn' => 'ওষুধ, ওআরএস ও স্বাস্থ্য সুরক্ষা সামগ্রী',
                'description' => 'Oral rehydration salts (ORS), hygiene kits, and essential emergency medicines.',
                'icon' => 'heart-pulse',
                'color_hex' => '#dc2626',
                'is_system' => true,
            ],
        ];

        $categoryMap = [];
        foreach ($categories as $cat) {
            $model = ReliefCategory::updateOrCreate(
                ['code' => $cat['code']],
                array_merge($cat, ['is_active' => true])
            );
            $categoryMap[$cat['code']] = $model;
        }

        $commodities = [
            [
                'commodity_code' => 'COM-RICE-GR',
                'category_code' => 'FOOD',
                'name_en' => 'GR Rice (Parboiled)',
                'name_bn' => 'জিআর চাল (সিদ্ধ)',
                'unit_of_measure' => 'KG',
                'is_perishable' => false,
            ],
            [
                'commodity_code' => 'COM-DRY-FOOD',
                'category_code' => 'FOOD',
                'name_en' => 'Emergency Dry Food Pack (7-Day Ration)',
                'name_bn' => 'জরুরি শুকনো খাবারের প্যাকেট (৭ দিনের)',
                'unit_of_measure' => 'PACKET',
                'is_perishable' => true,
            ],
            [
                'commodity_code' => 'COM-WATER-5L',
                'category_code' => 'DRINKING_WATER',
                'name_en' => 'Safe Drinking Water (5L Jerrycan)',
                'name_bn' => 'নিরাপদ পানীয় জল (৫ লিটার জার)',
                'unit_of_measure' => 'LITER',
                'is_perishable' => false,
            ],
            [
                'commodity_code' => 'COM-CASH-GR',
                'category_code' => 'CASH_ASSISTANCE',
                'name_en' => 'GR Cash Assistance Fund',
                'name_bn' => 'জিআর নগদ অর্থ সহায়তা তহবিল',
                'unit_of_measure' => 'BDT',
                'is_perishable' => false,
            ],
            [
                'commodity_code' => 'COM-BLANKET-STD',
                'category_code' => 'CLOTHING',
                'name_en' => 'Heavy Winter Blanket',
                'name_bn' => 'শীতকালীন কম্বল',
                'unit_of_measure' => 'PIECE',
                'is_perishable' => false,
            ],
            [
                'commodity_code' => 'COM-CI-BUNDLE',
                'category_code' => 'SHELTER_MATERIALS',
                'name_en' => 'Corrugated Iron (CI) Sheet Bundle',
                'name_bn' => 'ঢেউটিনের বান্ডিল',
                'unit_of_measure' => 'BUNDLE',
                'is_perishable' => false,
            ],
            [
                'commodity_code' => 'COM-ORS-KIT',
                'category_code' => 'MEDICINE_SUPPLIES',
                'name_en' => 'ORS & Water Purification Emergency Kit',
                'name_bn' => 'ওআরএস ও পানি বিশুদ্ধকরণ জরুরি কিট',
                'unit_of_measure' => 'KIT',
                'is_perishable' => true,
            ],
        ];

        $commodityMap = [];
        foreach ($commodities as $item) {
            $catModel = $categoryMap[$item['category_code']];
            $commodity = Commodity::updateOrCreate(
                ['commodity_code' => $item['commodity_code']],
                [
                    'relief_category_id' => $catModel->id,
                    'name_en' => $item['name_en'],
                    'name_bn' => $item['name_bn'],
                    'category' => $catModel->code,
                    'unit_of_measure' => $item['unit_of_measure'],
                    'is_perishable' => $item['is_perishable'],
                    'is_active' => true,
                ]
            );
            $commodityMap[$item['commodity_code']] = $commodity;
        }

        $national = AdministrativeUnit::where('tier_level', 'NATIONAL')->first();
        $khulnaDistrict = AdministrativeUnit::where('tier_level', 'DISTRICT')->where('name_en', 'like', '%Khulna%')->first()
            ?? AdministrativeUnit::where('tier_level', 'DISTRICT')->first();
        $dacopeUpazila = AdministrativeUnit::where('tier_level', 'UPAZILA')->where('name_en', 'like', '%Dacope%')->first()
            ?? AdministrativeUnit::where('tier_level', 'UPAZILA')->first();
        $union = AdministrativeUnit::where('tier_level', 'UNION')->first() ?? $dacopeUpazila;

        if (!$national && !$dacopeUpazila && !$khulnaDistrict) {
            return;
        }

        $warehouseDefs = array_filter([
            $national ? [
                'warehouse_code' => 'WH-NAT-CSD-01',
                'administrative_unit_id' => $national->id,
                'warehouse_name' => 'Central Disaster Relief Godown (Dhaka CSD)',
                'warehouse_name_bn' => 'কেন্দ্রীয় দুর্যোগ ত্রাণ গুদাম (ঢাকা সিএসডি)',
                'tier' => Warehouse::TIER_CENTRAL_CSD,
                'capacity_mt' => 5000.00,
                'latitude' => 23.7805,
                'longitude' => 90.4071,
            ] : null,
            $khulnaDistrict ? [
                'warehouse_code' => 'WH-KHL-GODOWN-01',
                'administrative_unit_id' => $khulnaDistrict->id,
                'warehouse_name' => 'Khulna District Relief Godown',
                'warehouse_name_bn' => 'খুলনা জেলা ত্রাণ গুদাম',
                'tier' => Warehouse::TIER_DISTRICT_GODOWN,
                'capacity_mt' => 1200.00,
                'latitude' => 22.8456,
                'longitude' => 89.5403,
            ] : null,
            $dacopeUpazila ? [
                'warehouse_code' => 'WH-DAC-STORE-01',
                'administrative_unit_id' => $dacopeUpazila->id,
                'warehouse_name' => 'Dacope Upazila Relief Store',
                'warehouse_name_bn' => 'দাকোপ উপজেলা ত্রাণ ভাণ্ডার',
                'tier' => Warehouse::TIER_UPAZILA_STORE,
                'capacity_mt' => 350.00,
                'latitude' => 22.5736,
                'longitude' => 89.5112,
            ] : null,
        ]);

        $warehouses = [];
        $isPgsql = DB::getDriverName() === 'pgsql';

        foreach ($warehouseDefs as $wDef) {
            $wh = Warehouse::updateOrCreate(
                ['warehouse_code' => $wDef['warehouse_code']],
                array_merge($wDef, ['is_active' => true])
            );
            if ($isPgsql && $wDef['latitude'] !== null && $wDef['longitude'] !== null) {
                DB::statement(
                    'UPDATE logistics.warehouses SET location_geom = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?',
                    [$wDef['longitude'], $wDef['latitude'], $wh->id]
                );
            }
            $warehouses[] = $wh;
        }

        // Seed opening stock batches per warehouse
        $systemUser = User::first();
        foreach ($warehouses as $wh) {
            $stockLevels = [
                'COM-RICE-GR' => $wh->tier === Warehouse::TIER_CENTRAL_CSD ? 100000 : ($wh->tier === Warehouse::TIER_DISTRICT_GODOWN ? 25000 : 8000),
                'COM-DRY-FOOD' => $wh->tier === Warehouse::TIER_CENTRAL_CSD ? 5000 : ($wh->tier === Warehouse::TIER_DISTRICT_GODOWN ? 1500 : 400),
                'COM-WATER-5L' => $wh->tier === Warehouse::TIER_CENTRAL_CSD ? 20000 : 4000,
                'COM-CASH-GR' => $wh->tier === Warehouse::TIER_CENTRAL_CSD ? 5000000 : 500000,
                'COM-BLANKET-STD' => $wh->tier === Warehouse::TIER_CENTRAL_CSD ? 8000 : 1000,
                'COM-CI-BUNDLE' => $wh->tier === Warehouse::TIER_CENTRAL_CSD ? 2000 : 250,
                'COM-ORS-KIT' => $wh->tier === Warehouse::TIER_CENTRAL_CSD ? 10000 : 1200,
            ];

            foreach ($stockLevels as $code => $qty) {
                if (!isset($commodityMap[$code])) {
                    continue;
                }
                $commodity = $commodityMap[$code];
                $batchNo = "BATCH-{$wh->warehouse_code}-{$code}-01";

                $inv = WarehouseInventory::firstOrCreate(
                    [
                        'warehouse_id' => $wh->id,
                        'commodity_id' => $commodity->id,
                        'batch_number' => $batchNo,
                    ],
                    [
                        'current_quantity' => $qty,
                        'reserved_quantity' => 0,
                        'received_date' => now()->subDays(10)->toDateString(),
                        'expiry_date' => $commodity->is_perishable ? now()->addMonths(6)->toDateString() : null,
                    ]
                );

                if ($inv->wasRecentlyCreated && $systemUser) {
                    StockTransaction::create([
                        'warehouse_inventory_id' => $inv->id,
                        'transaction_type' => StockTransaction::TYPE_STOCK_IN_GRN,
                        'quantity' => $qty,
                        'balance_after' => $qty,
                        'reference_voucher_no' => "GRN-INIT-{$wh->warehouse_code}-{$code}",
                        'notes' => 'Initial warehouse stock seed batch',
                        'authorized_by' => $systemUser->id,
                    ]);
                }
            }
        }

        // Seed field distribution centers
        $points = array_filter([
            $union ? [
                'point_code' => 'DP-DAC-01',
                'administrative_unit_id' => $union->id,
                'point_name' => 'Dacope Government High School Grounds',
                'point_name_bn' => 'দাকোপ সরকারি উচ্চ বিদ্যালয় মাঠ',
                'address' => 'Chalna Bazar Road, Dacope, Khulna',
                'latitude' => 22.5762,
                'longitude' => 89.5145,
            ] : null,
            $dacopeUpazila ? [
                'point_code' => 'DP-DAC-02',
                'administrative_unit_id' => $dacopeUpazila->id,
                'point_name' => 'Dacope Upazila Parishad Cyclone Shelter',
                'point_name_bn' => 'দাকোপ উপজেলা পরিষদ সাইক্লোন শেল্টার',
                'address' => 'Upazila Parishad Complex, Dacope',
                'latitude' => 22.5718,
                'longitude' => 89.5091,
            ] : null,
            $khulnaDistrict ? [
                'point_code' => 'DP-KHL-01',
                'administrative_unit_id' => $khulnaDistrict->id,
                'point_name' => 'Khulna Zilla Stadium Relief Center',
                'point_name_bn' => 'খুলনা জেলা স্টেডিয়াম ত্রাণ কেন্দ্র',
                'address' => 'Stadium Para, Khulna',
                'latitude' => 22.8112,
                'longitude' => 89.5564,
            ] : null,
        ]);

        foreach ($points as $pt) {
            $point = DistributionPoint::updateOrCreate(
                ['point_code' => $pt['point_code']],
                array_merge($pt, ['is_active' => true])
            );
            if ($isPgsql && $pt['latitude'] !== null && $pt['longitude'] !== null) {
                DB::statement(
                    'UPDATE distribution.distribution_points SET location_geom = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?',
                    [$pt['longitude'], $pt['latitude'], $point->id]
                );
            }
        }
    }
}
