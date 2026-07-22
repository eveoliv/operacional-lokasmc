<?php

namespace Database\Factories;

use App\Models\AttendanceSession;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AttendanceSession> */
class AttendanceSessionFactory extends Factory
{
    protected $model = AttendanceSession::class;

    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('-1 month', '+3 months');

        return [
            'event_id' => Event::factory(),
            'name' => fake()->words(3, true),
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify('+2 hours'),
            'locked_at' => null,
            'locked_by_user_id' => null,
            'archived_at' => null,
        ];
    }

    public function locked(): static
    {
        return $this->state(fn () => ['locked_at' => now(), 'locked_by_user_id' => User::factory()]);
    }
}
