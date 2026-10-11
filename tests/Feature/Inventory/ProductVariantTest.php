<?php

use App\Enums\RecordStatus;
use App\Enums\Role;
use App\Models\ProductVariant;
use App\Models\VariantAttribute;

use function Pest\Laravel\actingAs;

it('creates only the selected size and color combinations', function () {
    $product = makeProduct(
        ['Size' => ['L', 'XL', 'XXL'], 'Color' => ['Red', 'Green', 'Yellow', 'White', 'Black', 'Blue']],
        [['L', 'Red'], ['L', 'Green'], ['L', 'Yellow'], ['XL', 'White'], ['XL', 'Black'], ['XL', 'Blue'], ['XXL', 'Black'], ['XXL', 'Green']],
    );

    expect($product->variants()->pluck('name')->sort()->values()->all())->toBe([
        'L / Green', 'L / Red', 'L / Yellow', 'XL / Black', 'XL / Blue', 'XL / White', 'XXL / Black', 'XXL / Green',
    ]);
});

it('generates a unique SKU per variant from product and value codes', function () {
    $product = makeProduct(['Size' => ['L'], 'Color' => ['Red', 'Black']], [['L', 'Red'], ['L', 'Black']]);

    expect(variantNamed($product, 'L / Red')->sku)->toBe("{$product->code}-L-RED")
        ->and(variantNamed($product, 'L / Black')->sku)->toBe("{$product->code}-L-BLACK");
});

it('creates a single default variant for products without attributes', function () {
    $product = makeProduct();

    expect($product->variants)->toHaveCount(1)
        ->and($product->variants->first()->name)->toBe('সাধারণ')
        ->and($product->variants->first()->sku)->toBe($product->code);
});

it('rejects a combination that already exists for the product', function () {
    $user = userWithRole(Role::InventoryManager);
    $product = makeProduct(['Size' => ['L'], 'Color' => ['Red']], [['L', 'Red']]);
    $valueIds = variantNamed($product, 'L / Red')->attributeValues()->pluck('variant_attribute_values.id')->all();

    actingAs($user)
        ->post(route('variants.store', $product), ['variants' => [['value_ids' => $valueIds]]])
        ->assertSessionHasErrors('variants.0.value_ids');

    expect($product->variants()->count())->toBe(1);
});

it('rejects a variant that is missing a value for one of the product attributes', function () {
    $user = userWithRole(Role::InventoryManager);
    $product = makeProduct(['Size' => ['L', 'XL'], 'Color' => ['Red']], [['L', 'Red']]);
    $xl = VariantAttribute::query()->where('name', 'Size')->first()->values()->where('value', 'XL')->value('id');

    actingAs($user)
        ->post(route('variants.store', $product), ['variants' => [['value_ids' => [$xl]]]])
        ->assertSessionHasErrors('variants.0.value_ids');
});

it('adds a new combination to an existing product', function () {
    $user = userWithRole(Role::InventoryManager);
    $product = makeProduct(['Size' => ['L', 'XL'], 'Color' => ['Red']], [['L', 'Red']]);
    $ids = VariantAttribute::query()->with('values')->get()->flatMap->values->whereIn('value', ['XL', 'Red'])->pluck('id')->all();

    actingAs($user)->post(route('variants.store', $product), ['variants' => [['value_ids' => $ids]]])->assertRedirect();

    expect($product->variants()->pluck('name')->all())->toContain('XL / Red');
});

it('archives a variant while preserving its stock history', function () {
    $user = userWithRole(Role::InventoryManager);
    $product = makeProduct(['Size' => ['L']], [['L']]);
    receiveStock($product, ['L' => 10]);
    $variant = variantNamed($product, 'L');

    actingAs($user)->post(route('variants.archive', $variant))->assertRedirect();

    expect($variant->refresh()->status)->toBe(RecordStatus::Archived)
        ->and(balanceOf($variant)->available)->toBe(10)
        ->and($variant->balances()->exists())->toBeTrue();
});

it('forbids stock operators from creating products', function () {
    actingAs(userWithRole(Role::StockOperator))
        ->post(route('products.store'), ['name' => 'X', 'code' => 'X1'])
        ->assertForbidden();

    expect(ProductVariant::query()->count())->toBe(0);
});
