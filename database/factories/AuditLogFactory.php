<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AuditLog> */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'actor_user_id' => User::factory(),
            'action' => fake()->randomElement(['created', 'updated', 'archived', 'restored']),
            'auditable_type' => User::class,
            'auditable_id' => User::factory(),
            'organizational_unit_id' => OrganizationalUnit::factory(),
            'correlation_id' => fake()->uuid(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'old_values' => null,
            'new_values' => ['name' => fake()->name()],
            'metadata' => [],
            'created_at' => now(),
        ];
    }
}
