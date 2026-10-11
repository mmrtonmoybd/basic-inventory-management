<?php

use App\Enums\Role;
use App\Models\Product;
use App\Models\StockLedgerEntry;
use App\Models\User;
use App\Models\VariantAttribute;
use App\Models\Warehouse;
use App\Services\DemoDataService;
use App\Services\ReconciliationService;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\DemoSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;

function superAdmin(): User
{
    (new CatalogSeeder)->run();

    return userWithRole(Role::SuperAdmin);
}

it('loads demo data into an empty system with a balanced ledger', function () {
    $admin = superAdmin();

    actingAs($admin)->post(route('admin.demo-data.store'))->assertRedirect();

    expect(app(DemoDataService::class)->isLoaded())->toBeTrue()
        ->and(Product::query()->count())->toBe(6)
        ->and(StockLedgerEntry::query()->count())->toBeGreaterThan(0)
        ->and(app(ReconciliationService::class)->discrepancies())->toBeEmpty();
});

it('refuses to load demo data when real inventory exists', function () {
    $admin = superAdmin();
    makeProduct(['Size' => ['L']], [['L']]);

    actingAs($admin)->post(route('admin.demo-data.store'))->assertSessionHasErrors('demo');

    expect(app(DemoDataService::class)->isLoaded())->toBeFalse();
});

it('clears demo data but keeps real users, roles, attributes and warehouses', function () {
    $admin = superAdmin();
    app(DemoDataService::class)->load($admin);
    $realColleague = userWithRole(Role::StockOperator);

    actingAs($admin)->delete(route('admin.demo-data.destroy'), ['confirmation' => 'ডেমো মুছুন'])->assertRedirect();

    expect(Product::query()->exists())->toBeFalse()
        ->and(StockLedgerEntry::query()->exists())->toBeFalse()
        ->and(User::query()->whereIn('email', DemoSeeder::USER_EMAILS)->exists())->toBeFalse()
        ->and($admin->fresh())->not->toBeNull()
        ->and($realColleague->fresh()->hasRole(Role::StockOperator->value))->toBeTrue()
        ->and(VariantAttribute::query()->count())->toBe(3)
        ->and(Warehouse::query()->exists())->toBeTrue()
        ->and(app(DemoDataService::class)->isLoaded())->toBeFalse();
});

it('requires the typed confirmation before clearing', function () {
    $admin = superAdmin();
    app(DemoDataService::class)->load($admin);

    actingAs($admin)->delete(route('admin.demo-data.destroy'), ['confirmation' => 'yes'])->assertSessionHasErrors('confirmation');

    expect(Product::query()->count())->toBe(6);
});

it('never clears inventory that was not loaded as demo data', function () {
    $admin = superAdmin();
    makeProduct(['Size' => ['L']], [['L']]);

    actingAs($admin)->delete(route('admin.demo-data.destroy'), ['confirmation' => 'ডেমো মুছুন'])->assertSessionHasErrors('demo');

    expect(Product::query()->count())->toBe(1);
});

it('lets only a super admin manage demo data', function () {
    superAdmin();
    $manager = userWithRole(Role::InventoryManager);
    $manager->givePermissionTo('users.manage');

    actingAs($manager)->get(route('admin.demo-data.index'))->assertForbidden();
    actingAs($manager)->post(route('admin.demo-data.store'))->assertForbidden();
    actingAs($manager)->delete(route('admin.demo-data.destroy'), ['confirmation' => 'ডেমো মুছুন'])->assertForbidden();
});

it('loads and clears demo data from the command line', function () {
    superAdmin();

    artisan('inventory:demo', ['--force' => true])->assertSuccessful();
    expect(Product::query()->count())->toBe(6);

    artisan('inventory:demo', ['--clear' => true, '--force' => true])->assertSuccessful();
    expect(Product::query()->exists())->toBeFalse();
});
