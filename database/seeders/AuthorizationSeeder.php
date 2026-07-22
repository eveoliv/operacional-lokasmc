<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AuthorizationSeeder extends Seeder
{
    public function run(): void
    {
        $permissionCodes = [
            'organization.view',
            'organization.manage',
            'people.view',
            'people.manage',
            'users.view',
            'users.manage',
            'access.manage',
            'events.view',
            'events.manage',
            'registrations.view',
            'registrations.manage',
            'attendance.view',
            'attendance.manage',
            'attendance.lock',
            'audit.view',
        ];

        $permissions = collect($permissionCodes)->mapWithKeys(function (string $code): array {
            $permission = Permission::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $code],
            );

            return [$code => $permission->getKey()];
        });

        $roles = [
            'ROOT_ADMIN' => $permissionCodes,
            'STRUCTURE_ADMIN' => ['organization.view', 'organization.manage', 'people.view', 'people.manage', 'users.view', 'events.view', 'events.manage', 'registrations.view', 'registrations.manage', 'attendance.view', 'attendance.manage'],
            'USER_ADMIN' => ['users.view', 'users.manage', 'access.manage', 'people.view'],
            'EVENT_MANAGER' => ['events.view', 'events.manage', 'registrations.view', 'registrations.manage', 'attendance.view', 'attendance.manage', 'attendance.lock'],
            'ATTENDANCE_OPERATOR' => ['events.view', 'registrations.view', 'attendance.view', 'attendance.manage'],
            'AUDITOR' => ['people.view', 'events.view', 'registrations.view', 'attendance.view', 'audit.view'],
            'VIEWER' => ['people.view', 'events.view', 'registrations.view', 'attendance.view'],
        ];

        foreach ($roles as $code => $permissionCodesForRole) {
            $role = Role::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $code,
                    'hierarchy_level' => array_search($code, array_keys($roles), true) + 1,
                    'is_active' => true,
                ],
            );

            $role->permissions()->sync(
                collect($permissionCodesForRole)->map(fn (string $permissionCode) => $permissions->get($permissionCode))->all(),
            );
        }
    }
}
