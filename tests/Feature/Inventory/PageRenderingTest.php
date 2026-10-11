<?php

use App\Enums\Role;
use App\Models\CustomerReturn;
use App\Models\DamageCase;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierExchangeClaim;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\DemoSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

it('renders every screen for a super admin on seeded data', function (string $route, array $parameters) {
    $admin = userWithRole(Role::SuperAdmin);
    $admin->update(['email' => 'admin@example.com']);
    seed([CatalogSeeder::class, DemoSeeder::class]);

    $parameters = array_map(fn ($value) => is_callable($value) ? $value() : $value, $parameters);

    actingAs($admin)->get(route($route, $parameters))->assertOk();
})->with([
    'dashboard' => ['dashboard', []],
    'products' => ['products.index', []],
    'new product' => ['products.create', []],
    'product' => ['products.show', [fn () => Product::query()->value('id')]],
    'stock in' => ['stock.create', ['in']],
    'stock out' => ['stock.create', ['out']],
    'opening stock' => ['stock.create', ['opening']],
    'movements' => ['movements.index', []],
    'movement' => ['movements.show', [fn () => StockMovement::query()->value('id')]],
    'returns' => ['returns.index', []],
    'new return' => ['returns.create', []],
    'return' => ['returns.show', [fn () => CustomerReturn::query()->value('id')]],
    'damage' => ['damage.index', []],
    'new damage' => ['damage.create', []],
    'damage case' => ['damage.show', [fn () => DamageCase::query()->value('id')]],
    'claims' => ['claims.index', []],
    'claim' => ['claims.show', [fn () => SupplierExchangeClaim::query()->value('id')]],
    'suppliers' => ['suppliers.index', []],
    'supplier' => ['suppliers.show', [fn () => Supplier::query()->value('id')]],
    'adjustments' => ['adjustments.index', []],
    'new adjustment' => ['adjustments.create', []],
    'adjustment' => ['adjustments.show', [fn () => StockAdjustment::query()->value('id')]],
    'reports' => ['reports.index', []],
    'daily report' => ['reports.show', ['daily-in-out']],
    'variant report' => ['reports.show', ['variant-stock']],
    'activity report' => ['reports.show', ['user-activity']],
    'activity log' => ['activity.index', []],
    'notifications' => ['notifications.index', []],
    'import' => ['import.create', []],
    'attributes' => ['admin.attributes.index', []],
    'categories' => ['admin.categories.index', []],
    'warehouses' => ['admin.warehouses.index', []],
    'users' => ['admin.users.index', []],
    'roles' => ['admin.roles.index', []],
]);
