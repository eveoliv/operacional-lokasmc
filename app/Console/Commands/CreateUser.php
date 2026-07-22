<?php

namespace App\Console\Commands;

use App\Models\AccessGrant;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateUser extends Command
{
    protected $signature = 'user:create
        {--grant-root-admin : Concede ROOT_ADMIN apenas se ainda não existir administrador raiz}
        {--root-unit= : ID explícito da unidade organizacional raiz}';

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

        $grantRootAdmin = (bool) $this->option('grant-root-admin');
        $rootUnit = null;
        $rootRole = null;

        if ($grantRootAdmin) {
            $rootUnitId = filter_var($this->option('root-unit'), FILTER_VALIDATE_INT);

            if ($rootUnitId === false) {
                $this->error('Informe uma unidade raiz válida com --root-unit=ID.');

                return self::FAILURE;
            }

            $rootUnit = OrganizationalUnit::query()
                ->whereKey($rootUnitId)
                ->whereNull('parent_id')
                ->where('is_active', true)
                ->whereNull('archived_at')
                ->first();
            $rootRole = Role::query()->where('code', 'ROOT_ADMIN')->where('is_active', true)->first();

            if ($rootUnit === null) {
                $this->error('A unidade selecionada não é uma unidade raiz ativa.');

                return self::FAILURE;
            }

            if ($rootRole === null) {
                $this->error('A função ROOT_ADMIN ativa não foi encontrada.');

                return self::FAILURE;
            }

            if (AccessGrant::query()->whereNull('revoked_at')->whereHas('role', fn ($query) => $query->where('code', 'ROOT_ADMIN'))->exists()) {
                $this->error('Já existe um administrador raiz; use o fluxo normal de concessão de acesso.');

                return self::FAILURE;
            }

            if (! $this->confirm("Conceder o primeiro ROOT_ADMIN em [{$rootUnit->name}]?", false)) {
                $this->warn('Operação cancelada.');

                return self::FAILURE;
            }
        } elseif ($this->option('root-unit') !== null) {
            $this->error('--root-unit só pode ser usado com --grant-root-admin.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($name, $email, $password, $grantRootAdmin, $rootUnit, $rootRole): void {
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]);

            if (! $grantRootAdmin) {
                return;
            }

            // Serialize bootstrap attempts so only the first system root grant succeeds.
            AccessGrant::query()->whereHas('role', fn ($query) => $query->where('code', 'ROOT_ADMIN'))->lockForUpdate()->get();

            if (AccessGrant::query()->whereNull('revoked_at')->whereHas('role', fn ($query) => $query->where('code', 'ROOT_ADMIN'))->exists()) {
                throw new \RuntimeException('Já existe um administrador raiz.');
            }

            $grant = AccessGrant::query()->create([
                'user_id' => $user->getKey(),
                'role_id' => $rootRole->getKey(),
                'organizational_unit_id' => $rootUnit->getKey(),
            ]);

            app(AuditLogger::class)->log(
                'access.root_admin_bootstrapped',
                $grant,
                scope: $rootUnit,
                newValues: ['user_id' => $user->getKey(), 'role_id' => $rootRole->getKey(), 'organizational_unit_id' => $rootUnit->getKey()],
                metadata: ['source' => 'user:create'],
            );
        });

        $this->info('Usuário criado com sucesso.');

        return self::SUCCESS;
    }
}
