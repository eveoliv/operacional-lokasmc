<?php

namespace Database\Factories;

use App\Models\AccessGrant;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AccessGrant> */
class AccessGrantFactory extends Factory
{
    protected $model = AccessGrant::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'role_id' => Role::factory(),
            'organizational_unit_id' => OrganizationalUnit::factory(),
            'starts_at' => now(),
            'ends_at' => null,
            'granted_by_user_id' => null,
            'delegated_from_grant_id' => null,
            'revoked_at' => null,
            'revoked_by_user_id' => null,
            'revocation_reason' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subMonth(),
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn () => [
            'revoked_at' => now(),
            'revoked_by_user_id' => User::factory(),
            'revocation_reason' => fake()->sentence(),
        ]);
    }
}
