<?php

namespace Database\Seeders;

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Creates every permission and the seven default roles. Safe to re-run.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionEnum::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        foreach (RoleEnum::cases() as $roleEnum) {
            $role = Role::findOrCreate($roleEnum->value, 'web');

            if ($role->wasRecentlyCreated || $role->permissions()->doesntExist()) {
                $role->syncPermissions(array_map(fn (PermissionEnum $permission): string => $permission->value, $roleEnum->defaultPermissions()));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
