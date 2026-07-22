<?php

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\OrganizationalUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Event> */
class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('+1 day', '+3 months');

        return [
            'organizational_unit_id' => OrganizationalUnit::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'status' => EventStatus::Draft->value,
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify('+2 hours'),
            'location' => fake()->optional()->address(),
            'capacity' => fake()->optional()->numberBetween(10, 500),
            'archived_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => EventStatus::Published->value]);
    }

    public function withCapacity(int $capacity): static
    {
        return $this->state(fn () => ['capacity' => $capacity]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => EventStatus::Archived->value, 'archived_at' => now()]);
    }
}
