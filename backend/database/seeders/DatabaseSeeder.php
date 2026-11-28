<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AdministrativeUnitSeeder::class,
            DisasterEventSeeder::class,
            BeneficiarySeeder::class,
            ReliefCatalogAndWarehouseSeeder::class,
            WarehouseModuleSeeder::class,
            ResponseCatalogSeeder::class,
            ResourceSeeder::class,
            ProjectSeeder::class,
            DamageAssessmentSeeder::class,
            VolunteerSeeder::class,
            WorkflowSeeder::class,
        ]);
    }
}
