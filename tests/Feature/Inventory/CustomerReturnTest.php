<?php

use App\Enums\ReturnStatus;
use App\Enums\Role;
use App\Models\CustomerReturn;
use App\Models\DamageCase;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\actingAs;

/**
 * @param  array<string, int>  $quantities
 */
function postReturn(Product $product, array $quantities, ?User $user = null): CustomerReturn
{
    actingAs($user ?? userWithRole(Role::ReturnQcOperator))->post(route('returns.store'), [
        'warehouse_id' => defaultWarehouse()->id,
        'return_date' => now('Asia/Dhaka')->toDateString(),
        'idempotency_key' => (string) Str::uuid(),
        'lines' => collect($quantities)->map(fn (int $quantity, string $name): array => ['variant_id' => variantNamed($product, $name)->id, 'quantity' => $quantity])->values()->all(),
    ])->assertRedirect();

    return CustomerReturn::query()->latest('id')->firstOrFail();
}

/**
 * @param  array<string, array{0: int, 1: int}>  $results  variant name => [good, damaged]
 */
function inspect(CustomerReturn $return, array $results, ?User $user = null): TestResponse
{
    $lines = $return->lines()->with('variant')->get();

    return actingAs($user ?? userWithRole(Role::ReturnQcOperator))->post(route('returns.inspect', $return), [
        'damage_reason' => 'Torn',
        'lines' => collect($results)->map(fn (array $result, string $name): array => [
            'return_line_id' => $lines->first(fn ($line) => $line->variant->name === $name)->id,
            'good_quantity' => $result[0],
            'damaged_quantity' => $result[1],
        ])->values()->all(),
    ]);
}

it('places returned units into inspection stock, not available', function () {
    $product = makeProduct(['Size' => ['L', 'XL'], 'Color' => ['Red', 'Black']], [['L', 'Red'], ['XL', 'Black']]);

    postReturn($product, ['L / Red' => 5, 'XL / Black' => 3]);

    $balance = balanceOf(variantNamed($product, 'L / Red'));
    expect($balance->inspection)->toBe(5)
        ->and($balance->available)->toBe(0)
        ->and(balanceOf(variantNamed($product, 'XL / Black'))->inspection)->toBe(3);
});

it('moves inspected units to available or damaged without changing physical stock', function () {
    $product = makeProduct(['Size' => ['XL'], 'Color' => ['Black']], [['XL', 'Black']]);
    $return = postReturn($product, ['XL / Black' => 3]);

    inspect($return, ['XL / Black' => [1, 2]])->assertRedirect();

    $balance = balanceOf(variantNamed($product, 'XL / Black'));
    expect($balance->inspection)->toBe(0)
        ->and($balance->available)->toBe(1)
        ->and($balance->damaged)->toBe(2)
        ->and($balance->physicalOnHand())->toBe(3)
        ->and($return->refresh()->status)->toBe(ReturnStatus::Inspected);
});

it('supports partial inspections across several sessions', function () {
    $product = makeProduct(['Size' => ['L'], 'Color' => ['Red']], [['L', 'Red']]);
    $return = postReturn($product, ['L / Red' => 10]);

    inspect($return, ['L / Red' => [4, 0]])->assertRedirect();
    expect($return->refresh()->status)->toBe(ReturnStatus::PartiallyInspected)
        ->and(balanceOf(variantNamed($product, 'L / Red'))->inspection)->toBe(6);

    inspect($return, ['L / Red' => [5, 1]])->assertRedirect();
    expect($return->refresh()->status)->toBe(ReturnStatus::Inspected)
        ->and($return->inspections()->count())->toBe(2);
});

it('rejects inspecting more units than remain and never double counts', function () {
    $product = makeProduct(['Size' => ['L'], 'Color' => ['Red']], [['L', 'Red']]);
    $return = postReturn($product, ['L / Red' => 5]);
    inspect($return, ['L / Red' => [5, 0]])->assertRedirect();

    inspect($return, ['L / Red' => [1, 0]])->assertSessionHasErrors('lines.0.good_quantity');

    expect(balanceOf(variantNamed($product, 'L / Red'))->available)->toBe(5);
});

it('opens a damage case for units that fail inspection', function () {
    $product = makeProduct(['Size' => ['XL'], 'Color' => ['Black']], [['XL', 'Black']]);
    $return = postReturn($product, ['XL / Black' => 3]);

    inspect($return, ['XL / Black' => [0, 2]]);

    $case = DamageCase::query()->sole();
    expect($case->customer_return_id)->toBe($return->id)
        ->and($case->lines()->sole()->quantity)->toBe(2);
});

it('forbids stock operators from inspecting returns', function () {
    $product = makeProduct(['Size' => ['L'], 'Color' => ['Red']], [['L', 'Red']]);
    $return = postReturn($product, ['L / Red' => 2]);

    inspect($return, ['L / Red' => [2, 0]], userWithRole(Role::StockOperator))->assertForbidden();
});
