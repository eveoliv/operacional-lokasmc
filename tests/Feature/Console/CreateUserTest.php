<?php

namespace Tests\Feature\Console;

use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_be_created_interactively_without_hardcoded_password(): void
    {
        $this->artisan('user:create')
            ->expectsQuestion('Nome', 'Usuário Operacional')
            ->expectsQuestion('E-mail', 'operador@example.com')
            ->expectsQuestion('Senha', 'Senha-Segura-123!')
            ->expectsQuestion('Confirme a senha', 'Senha-Segura-123!')
            ->expectsOutput('Usuário criado com sucesso.')
            ->assertSuccessful();

        $user = User::query()->where('email', 'operador@example.com')->firstOrFail();

        $this->assertTrue(Hash::check('Senha-Segura-123!', $user->password));
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_explicit_opt_in_bootstraps_first_root_admin_at_selected_root_and_audits_it(): void
    {
        $root = OrganizationalUnit::factory()->create();
        Role::factory()->create(['code' => 'ROOT_ADMIN', 'hierarchy_level' => 1]);

        $this->artisan("user:create --grant-root-admin --root-unit={$root->getKey()}")
            ->expectsQuestion('Nome', 'Administrador Inicial')
            ->expectsQuestion('E-mail', 'admin@example.com')
            ->expectsQuestion('Senha', 'Senha-Segura-123!')
            ->expectsQuestion('Confirme a senha', 'Senha-Segura-123!')
            ->expectsConfirmation("Conceder o primeiro ROOT_ADMIN em [{$root->name}]?", 'yes')
            ->assertSuccessful();

        $user = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $this->assertDatabaseHas('access_grants', [
            'user_id' => $user->getKey(),
            'organizational_unit_id' => $root->getKey(),
            'granted_by_user_id' => null,
            'delegated_from_grant_id' => null,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'access.root_admin_bootstrapped',
            'organizational_unit_id' => $root->getKey(),
        ]);
    }

    public function test_root_admin_bootstrap_requires_explicit_opt_in_and_rejects_a_second_root_admin(): void
    {
        $root = OrganizationalUnit::factory()->create();
        $role = Role::factory()->create(['code' => 'ROOT_ADMIN', 'hierarchy_level' => 1]);
        $existing = User::factory()->create();
        $existing->accessGrants()->create(['role_id' => $role->getKey(), 'organizational_unit_id' => $root->getKey()]);

        $this->artisan("user:create --grant-root-admin --root-unit={$root->getKey()}")
            ->expectsQuestion('Nome', 'Outro Admin')
            ->expectsQuestion('E-mail', 'outro@example.com')
            ->expectsQuestion('Senha', 'Senha-Segura-123!')
            ->expectsQuestion('Confirme a senha', 'Senha-Segura-123!')
            ->expectsOutput('Já existe um administrador raiz; use o fluxo normal de concessão de acesso.')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'outro@example.com']);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'existente@example.com']);

        $this->artisan('user:create')
            ->expectsQuestion('Nome', 'Outro Usuário')
            ->expectsQuestion('E-mail', 'existente@example.com')
            ->expectsQuestion('Senha', 'Senha-Segura-123!')
            ->expectsQuestion('Confirme a senha', 'Senha-Segura-123!')
            ->assertFailed();

        $this->assertDatabaseCount('users', 1);
    }

    public function test_password_confirmation_must_match(): void
    {
        $this->artisan('user:create')
            ->expectsQuestion('Nome', 'Usuário Operacional')
            ->expectsQuestion('E-mail', 'operador@example.com')
            ->expectsQuestion('Senha', 'Senha-Segura-123!')
            ->expectsQuestion('Confirme a senha', 'senha-diferente')
            ->assertFailed();

        $this->assertDatabaseEmpty('users');
    }
}
