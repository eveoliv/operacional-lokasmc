<?php

namespace Database\Factories;

use App\Enums\PersonStatus;
use App\Models\OrganizationalUnit;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Person> */
class PersonFactory extends Factory
{
    protected $model = Person::class;

    public function definition(): array
    {
        return [
            'organizational_unit_id' => OrganizationalUnit::factory(),
            'user_id' => null,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'document' => fake()->unique()->numerify('###########'),
            'birth_date' => fake()->dateTimeBetween('-80 years', '-18 years'),
            'status' => PersonStatus::Active->value,
            'archived_at' => null,
        ];
    }

    public function withUser(): static
    {
        return $this->state(fn () => ['user_id' => User::factory()]);
    }

    public function archived(): static
    {
        return $this->state(fn () => [
            'status' => PersonStatus::Archived->value,
            'archived_at' => now(),
        ]);
    }
}
