<?php

namespace Database\Factories;

use App\Enums\RegistrationSource;
use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\Person;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Registration> */
class RegistrationFactory extends Factory
{
    protected $model = Registration::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'person_id' => Person::factory(),
            'status' => RegistrationStatus::Active->value,
            'source' => RegistrationSource::SelfService->value,
            'operated_by_user_id' => null,
            'eligible_event_audience_id' => null,
            'eligible_organizational_unit_id' => null,
            'cancelled_at' => null,
            'cancelled_by_user_id' => null,
            'cancellation_reason' => null,
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => RegistrationStatus::Cancelled->value,
            'cancelled_at' => now(),
            'cancelled_by_user_id' => User::factory(),
            'cancellation_reason' => fake()->sentence(),
        ]);
    }
}
