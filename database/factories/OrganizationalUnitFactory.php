<?php

namespace Database\Factories;

use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrganizationalUnit> */
class OrganizationalUnitFactory extends Factory
{
    protected $model = OrganizationalUnit::class;

    public function definition(): array
    {
        return [
            'organizational_unit_type_id' => OrganizationalUnitType::factory(),
            'parent_id' => null,
            'code' => fake()->unique()->lexify('unit-????????'),
            'name' => fake()->company(),
            'is_active' => true,
            'archived_at' => null,
        ];
    }

    public function childOf(OrganizationalUnit $parent): static
    {
        return $this->state(fn () => ['parent_id' => $parent->getKey()]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['is_active' => false, 'archived_at' => now()]);
    }
}
