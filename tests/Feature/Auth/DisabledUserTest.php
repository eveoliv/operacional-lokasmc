<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DisabledUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_user_cannot_log_in(): void
    {
        $user = User::factory()->disabled()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_disabled_reason_is_not_serialized_or_shared_with_authenticated_user(): void
    {
        $user = User::factory()->create(['disabled_reason' => 'Nota administrativa interna']);

        $this->assertArrayNotHasKey('disabled_reason', $user->toArray());

        $this->actingAs($user)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.id', $user->getKey())
                ->missing('auth.user.disabled_reason'));
    }

    public function test_user_with_an_existing_session_is_logged_out_when_disabled(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->update(['disabled_at' => now(), 'disabled_reason' => 'Disabled']);

        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
