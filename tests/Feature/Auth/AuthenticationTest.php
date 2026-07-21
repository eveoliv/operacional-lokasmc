<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get(route('login'))->assertOk();
    }

    public function test_registration_routes_are_not_available(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [])->assertNotFound();
    }

    public function test_users_can_authenticate_and_session_is_regenerated(): void
    {
        $user = User::factory()->create();
        $oldSessionId = Str::random(40);

        $response = $this->withSession(['marker' => true])
            ->withCookie(config('session.cookie'), $oldSessionId)
            ->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', [], false));
        $this->assertNotSame($oldSessionId, session()->getId());
    }

    public function test_invalid_credentials_return_generic_error(): void
    {
        $user = User::factory()->create();

        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors([
            'email' => __('auth.failed'),
        ]);
        $this->assertStringNotContainsString('password', __('auth.failed'));
    }

    public function test_users_are_rate_limited_after_five_attempts(): void
    {
        $user = User::factory()->create();
        $key = Str::transliterate(Str::lower($user->email).'|127.0.0.1');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }

    public function test_authenticated_users_can_access_dashboard_and_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $response = $this->post(route('logout'));

        $response->assertRedirect(route('home'));
        $this->assertGuest();
    }
}
