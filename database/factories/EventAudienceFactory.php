<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventAudience;
use App\Models\OrganizationalUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EventAudience> */
class EventAudienceFactory extends Factory
{
    protected $model = EventAudience::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'organizational_unit_id' => OrganizationalUnit::factory(),
            'include_descendants' => false,
        ];
    }

    public function includingDescendants(): static
    {
        return $this->state(fn () => ['include_descendants' => true]);
    }
}
