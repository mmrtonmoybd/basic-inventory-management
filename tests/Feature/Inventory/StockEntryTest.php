<?php

use App\Enums\MovementType;
use App\Enums\Role;
use App\Models\Product;
use App\Models\StockLedgerEntry;
use App\Models\StockMovement;
use App\Models\Supplier;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

function polo(): Product
{
    return makeProduct(
        ['Size' => ['L', 'XL'], 'Color' => ['Red', 'Green', 'Yellow', 'White', 'Black', 'Blue']],
        [['L', 'Red'], ['L', 'Green'], ['L', 'Yellow'], ['XL', 'White'], ['XL', 'Black'], ['XL', 'Blue']],
    );
}

/**
 * @param  array<string, int>  $quantities
 * @return array<string, mixed>
 */
function entryPayload(Product $product, array $quantities, array $extra = []): array
{
    return [
        'warehouse_id' => defaultWarehouse()->id,
        'business_date' => now('Asia/Dhaka')->toDateString(),
        'idempotency_key' => (string) Str::uuid(),
        'lines' => collect($quantities)->map(fn (int $quantity, string $name): array => [
            'variant_id' => variantNamed($product, $name)->id,
            'quantity' => $quantity,
        ])->values()->all(),
        ...$extra,
    ];
}

it('posts a multi-variant stock in and increases each variant independently', function () {
    $product = polo();
    $supplier = Supplier::factory()->create();

    actingAs(userWithRole(Role::StockOperator))
        ->post(route('stock.store', 'in'), entryPayload($product, [
            'L / Red' => 100, 'L / Green' => 80, 'L / Yellow' => 60, 'XL / White' => 120, 'XL / Black' => 90, 'XL / Blue' => 50,
        ], ['supplier_id' => $supplier->id, 'reference' => 'PO-1001']))
        ->assertRedirect();

    $movement = StockMovement::query()->sole();

    expect($movement->type)->toBe(MovementType::StockIn)
        ->and($movement->total_quantity)->toBe(500)
        ->and($movement->supplier_id)->toBe($supplier->id)
        ->and(balanceOf(variantNamed($product, 'L / Red'))->available)->toBe(100)
        ->and(balanceOf(variantNamed($product, 'XL / Blue'))->available)->toBe(50)
        ->and((int) StockLedgerEntry::query()->sum('quantity_change'))->toBe(500);
});

it('reduces available stock on stock out', function () {
    $product = polo();
    receiveStock($product, ['L / Red' => 100]);

    actingAs(userWithRole(Role::StockOperator))
        ->post(route('stock.store', 'out'), entryPayload($product, ['L / Red' => 40]))
        ->assertRedirect();

    expect(balanceOf(variantNamed($product, 'L / Red'))->available)->toBe(60);
});

it('rejects a stock out that would make stock negative and posts nothing', function () {
    $product = polo();
    receiveStock($product, ['L / Red' => 5, 'L / Green' => 10]);

    actingAs(userWithRole(Role::StockOperator))
        ->post(route('stock.store', 'out'), entryPayload($product, ['L / Red' => 6, 'L / Green' => 1]))
        ->assertSessionHasErrors('lines.0.quantity');

    expect(balanceOf(variantNamed($product, 'L / Red'))->available)->toBe(5)
        ->and(balanceOf(variantNamed($product, 'L / Green'))->available)->toBe(10)
        ->and(StockMovement::query()->where('type', MovementType::StockOut)->exists())->toBeFalse();
});

it('ignores a duplicate submission with the same idempotency key', function () {
    $product = polo();
    $user = userWithRole(Role::StockOperator);
    $payload = entryPayload($product, ['L / Red' => 25]);

    actingAs($user)->post(route('stock.store', 'in'), $payload)->assertRedirect();
    actingAs($user)->post(route('stock.store', 'in'), $payload)->assertRedirect();

    expect(StockMovement::query()->count())->toBe(1)
        ->and(balanceOf(variantNamed($product, 'L / Red'))->available)->toBe(25);
});

it('requires at least one positive quantity', function () {
    $product = polo();

    actingAs(userWithRole(Role::StockOperator))
        ->post(route('stock.store', 'in'), entryPayload($product, ['L / Red' => 0]))
        ->assertSessionHasErrors('lines');
});

it('refuses to receive stock into an archived variant', function () {
    $product = polo();
    variantNamed($product, 'L / Red')->update(['status' => 'archived']);

    actingAs(userWithRole(Role::StockOperator))
        ->post(route('stock.store', 'in'), entryPayload($product, ['L / Red' => 5]))
        ->assertSessionHasErrors('lines.0.quantity');
});

it('forbids users without the stock permission', function (Role $role, string $type) {
    $product = polo();

    actingAs(userWithRole($role))
        ->post(route('stock.store', $type), entryPayload($product, ['L / Red' => 5]))
        ->assertForbidden();
})->with([
    'report viewer posting stock in' => [Role::ReportViewer, 'in'],
    'QC operator posting stock out' => [Role::ReturnQcOperator, 'out'],
    'stock operator posting opening stock' => [Role::StockOperator, 'opening'],
]);

it('writes immutable ledger entries with running balances', function () {
    $product = polo();
    receiveStock($product, ['L / Red' => 10]);
    receiveStock($product, ['L / Red' => 5]);

    $entries = StockLedgerEntry::query()->orderBy('id')->get();

    expect($entries->pluck('balance_after')->all())->toBe([10, 15])
        ->and(fn () => $entries->first()->update(['quantity_change' => 999]))->toThrow(LogicException::class)
        ->and(fn () => $entries->first()->delete())->toThrow(LogicException::class);
});
