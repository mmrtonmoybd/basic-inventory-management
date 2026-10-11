<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use App\Services\DemoDataService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            CatalogSeeder::class,
        ]);

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Super Admin', 'password' => 'password'],
        );
        $admin->forceFill(['email_verified_at' => $admin->email_verified_at ?? now()])->save();

        $admin->assignRole(Role::SuperAdmin->value);

        if (! app()->isProduction() && ! app(DemoDataService::class)->hasInventoryData()) {
            app(DemoDataService::class)->load($admin);
        }
    }
}
