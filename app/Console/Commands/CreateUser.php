<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateUser extends Command
{
    protected $signature = 'user:create';

    protected $description = 'Cria um usuário de acesso de forma interativa';

    public function handle(): int
    {
        $name = trim((string) $this->ask('Nome'));
        $email = trim((string) $this->ask('E-mail'));
        $password = (string) $this->secret('Senha');
        $confirmation = (string) $this->secret('Confirme a senha');

        $validator = Validator::make(compact('name', 'email', 'password') + [
            'password_confirmation' => $confirmation,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ]);

        $this->info('Usuário criado com sucesso.');

        return self::SUCCESS;
    }
}
