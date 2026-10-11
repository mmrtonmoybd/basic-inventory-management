<?php

use App\Enums\MovementType;
use App\Enums\Role;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\VariantAttribute;
use App\Models\Warehouse;
use App\Services\ProductCatalogService;
use App\Services\StockEntryService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/*
|--------------------------------------------------------------------------
| Inventory helpers
|--------------------------------------------------------------------------
*/

/**
 * Creates a user holding the given role, seeding roles & permissions on first use.
 */
function userWithRole(Role $role): User
{
    if (Spatie\Permission\Models\Role::query()->doesntExist()) {
        (new RolesAndPermissionsSeeder)->run();
    }

    return tap(User::factory()->create())->assignRole($role->value);
}

function defaultWarehouse(): Warehouse
{
    return Warehouse::query()->where('is_default', true)->first()
        ?? Warehouse::factory()->default()->create(['code' => 'MAIN']);
}

/**
 * Creates a product whose variants are exactly the given combinations, e.g.
 * makeProduct(['Size' => ['L', 'XL'], 'Color' => ['Red', 'Black']], [['L', 'Red'], ['XL', 'Black']]).
 *
 * @param  array<string, list<string>>  $axes
 * @param  list<list<string>>  $combinations
 */
function makeProduct(array $axes = [], array $combinations = [], int $threshold = 0): Product
{
    $attributes = collect($axes)->map(function (array $values, string $name): VariantAttribute {
        $attribute = VariantAttribute::query()->firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]);

        foreach ($values as $value) {
            $attribute->values()->firstOrCreate(['value' => $value]);
        }

        return $attribute->load('values');
    });

    $names = array_keys($axes);

    return app(ProductCatalogService::class)->create([
        'name' => 'Product '.Str::random(5),
        'code' => 'P'.Str::upper(Str::random(6)),
        'attribute_ids' => $attributes->pluck('id')->values()->all(),
        'variants' => $names === []
            ? [['low_stock_threshold' => $threshold]]
            : array_map(fn (array $combination): array => [
                'value_ids' => array_map(fn (string $axis, string $value): int => $attributes[$axis]->values->firstWhere('value', $value)->id, $names, $combination),
                'low_stock_threshold' => $threshold,
            ], $combinations),
    ], User::factory()->create());
}

function variantNamed(Product $product, string $name): ProductVariant
{
    return $product->variants()->where('name', $name)->firstOrFail();
}

function balanceOf(ProductVariant $variant): StockBalance
{
    return StockBalance::query()->firstOrNew(
        ['product_variant_id' => $variant->id, 'warehouse_id' => defaultWarehouse()->id],
        ['available' => 0, 'inspection' => 0, 'damaged' => 0, 'with_supplier' => 0],
    );
}

/**
 * @param  array<string, int>  $quantities  variant name => quantity
 */
function receiveStock(Product $product, array $quantities, ?User $user = null): StockMovement
{
    [$movement] = app(StockEntryService::class)->post(MovementType::StockIn, [
        'warehouse_id' => defaultWarehouse()->id,
        'lines' => collect($quantities)->map(fn (int $quantity, string $name): array => [
            'variant_id' => variantNamed($product, $name)->id,
            'quantity' => $quantity,
        ])->values()->all(),
    ], $user ?? User::factory()->create());

    return $movement;
}
