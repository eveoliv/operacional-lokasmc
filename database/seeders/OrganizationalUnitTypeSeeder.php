<?php

namespace Database\Seeders;

use App\Models\OrganizationalUnitType;
use Illuminate\Database\Seeder;

class OrganizationalUnitTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'WORLD_I', 'name' => 'Mundial I', 'hierarchy_order' => 1],
            ['code' => 'WORLD_II', 'name' => 'Mundial II', 'hierarchy_order' => 2],
            ['code' => 'BRAZIL_III', 'name' => 'Brasil III', 'hierarchy_order' => 3],
            ['code' => 'REGIONAL_IV', 'name' => 'Regional IV', 'hierarchy_order' => 4],
            ['code' => 'REGIONAL_V', 'name' => 'Regional V', 'hierarchy_order' => 5],
            ['code' => 'DIVISION_VI', 'name' => 'Divisão VI', 'hierarchy_order' => 6],
        ];

        foreach ($types as $type) {
            OrganizationalUnitType::query()->updateOrCreate(
                ['code' => $type['code']],
                [...$type, 'is_active' => true],
            );
        }
    }
}
