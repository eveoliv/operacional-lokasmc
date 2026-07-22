<?php

namespace Database\Factories;

use App\Models\OrganizationalUnitType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrganizationalUnitType> */
class OrganizationalUnitTypeFactory extends Factory
{
    protected $model = OrganizationalUnitType::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('type-????????'),
            'name' => fake()->unique()->words(2, true),
            'hierarchy_order' => fake()->unique()->numberBetween(1, 1_000_000),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
