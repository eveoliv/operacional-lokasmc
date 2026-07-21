<?php

namespace Tests\Feature\Console;

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
