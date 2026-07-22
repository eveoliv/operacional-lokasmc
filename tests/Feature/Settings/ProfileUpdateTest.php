<?php

namespace Tests\Feature\Settings;

use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNotNull($user->fresh()->disabled_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.disabled', 'auditable_id' => $user->getKey()]);
    }

    public function test_account_deletion_preserves_history_and_revokes_active_grants(): void
    {
        $user = User::factory()->create();
        $unit = OrganizationalUnit::factory()->create();
        $role = Role::factory()->create();
        $grant = $user->accessGrants()->create(['role_id' => $role->getKey(), 'organizational_unit_id' => $unit->getKey()]);

        $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect(route('home'));

        $this->assertNotNull($user->fresh());
        $this->assertNotNull($user->disabled_at);
        $this->assertNotNull($grant->fresh()->revoked_at);
    }

    public function test_last_root_admin_cannot_disable_their_account(): void
    {
        $user = User::factory()->create();
        $unit = OrganizationalUnit::factory()->create();
        $role = Role::factory()->create(['code' => 'ROOT_ADMIN', 'hierarchy_level' => 1]);
        $user->accessGrants()->create(['role_id' => $role->getKey(), 'organizational_unit_id' => $unit->getKey()]);

        $this->actingAs($user)->from(route('profile.edit'))
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        $this->assertNull($user->fresh()->disabled_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->fresh());
    }
}
