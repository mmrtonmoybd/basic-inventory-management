<?php

use App\Enums\ClaimStatus;
use App\Enums\MovementType;
use App\Enums\Role;
use App\Models\DamageCase;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierExchangeClaim;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

/**
 * Damage 4 units of L / Green and request an exchange from a supplier.
 *
 * @return array{0: Product, 1: SupplierExchangeClaim}
 */
function damagedClaim(int $stock = 10, int $damaged = 4): array
{
    $product = makeProduct(['Size' => ['L'], 'Color' => ['Green', 'Red']], [['L', 'Green'], ['L', 'Red']]);
    receiveStock($product, ['L / Green' => $stock, 'L / Red' => 5]);

    actingAs(userWithRole(Role::StockOperator))->post(route('damage.store'), [
        'warehouse_id' => defaultWarehouse()->id,
        'damage_date' => now('Asia/Dhaka')->toDateString(),
        'reason' => 'Dye bleeding',
        'supplier_id' => Supplier::factory()->create()->id,
        'supplier_message' => 'Please replace',
        'request_exchange' => true,
        'idempotency_key' => (string) Str::uuid(),
        'lines' => [['variant_id' => variantNamed($product, 'L / Green')->id, 'quantity' => $damaged]],
    ])->assertRedirect();

    return [$product, SupplierExchangeClaim::query()->latest('id')->firstOrFail()];
}

function approveClaim(SupplierExchangeClaim $claim, ?int $replacementVariantId = null, ?int $quantity = null): void
{
    actingAs(userWithRole(Role::InventoryManager))->post(route('claims.review', $claim))->assertSessionHasNoErrors();
    actingAs(userWithRole(Role::SupplierCoordinator))->post(route('claims.agreement', $claim), [
        'supplier_reference' => 'RMA-1',
        'lines' => $claim->lines->map(fn ($line) => [
            'line_id' => $line->id,
            'approved_quantity' => $quantity ?? $line->claimed_quantity,
            'replacement_variant_id' => $replacementVariantId,
        ])->all(),
    ])->assertSessionHasNoErrors();
    actingAs(userWithRole(Role::ExchangeApprover))->post(route('claims.approve', $claim))->assertSessionHasNoErrors();
}

it('removes damaged units from available stock immediately', function () {
    [$product] = damagedClaim();

    $balance = balanceOf(variantNamed($product, 'L / Green'));
    expect($balance->available)->toBe(6)
        ->and($balance->damaged)->toBe(4)
        ->and($balance->physicalOnHand())->toBe(10);
});

it('prevents damaged units from being stocked out', function () {
    [$product] = damagedClaim();

    actingAs(userWithRole(Role::StockOperator))->post(route('stock.store', 'out'), [
        'warehouse_id' => defaultWarehouse()->id,
        'business_date' => now('Asia/Dhaka')->toDateString(),
        'idempotency_key' => 'k1',
        'lines' => [['variant_id' => variantNamed($product, 'L / Green')->id, 'quantity' => 7]],
    ])->assertSessionHasErrors('lines.0.quantity');
});

it('does not change stock when a claim is reviewed, agreed and approved', function () {
    [$product, $claim] = damagedClaim();
    $before = StockMovement::query()->count();

    approveClaim($claim);

    $balance = balanceOf(variantNamed($product, 'L / Green'));
    expect($claim->refresh()->status)->toBe(ClaimStatus::Approved)
        ->and(StockMovement::query()->count())->toBe($before)
        ->and($balance->damaged)->toBe(4)
        ->and($balance->available)->toBe(6)
        ->and($claim->approvalRecords()->pluck('action')->all())->toBe(['submitted', 'reviewed', 'supplier_agreed', 'approved']);
});

it('moves dispatched units from damaged to with supplier, outside physical stock', function () {
    [$product, $claim] = damagedClaim();
    approveClaim($claim);

    actingAs(userWithRole(Role::StockOperator))
        ->post(route('claims.dispatch', $claim), ['dispatch_date' => now('Asia/Dhaka')->toDateString()])
        ->assertSessionHasNoErrors();

    $balance = balanceOf(variantNamed($product, 'L / Green'));
    expect($balance->damaged)->toBe(0)
        ->and($balance->with_supplier)->toBe(4)
        ->and($balance->physicalOnHand())->toBe(6)
        ->and($claim->refresh()->status)->toBe(ClaimStatus::Dispatched);
});

it('adds received replacements to available stock with partial receipts', function () {
    [$product, $claim] = damagedClaim();
    approveClaim($claim);
    $operator = userWithRole(Role::StockOperator);
    actingAs($operator)->post(route('claims.dispatch', $claim), ['dispatch_date' => now('Asia/Dhaka')->toDateString()]);
    $line = $claim->lines()->sole();

    $receive = fn (int $quantity, string $key) => actingAs($operator)->post(route('claims.receive', $claim), [
        'received_date' => now('Asia/Dhaka')->toDateString(),
        'idempotency_key' => $key,
        'lines' => [['line_id' => $line->id, 'quantity' => $quantity]],
    ]);

    $receive(3, 'r1')->assertSessionHasNoErrors();
    expect($claim->refresh()->status)->toBe(ClaimStatus::PartiallyReceived)
        ->and(balanceOf(variantNamed($product, 'L / Green'))->available)->toBe(9)
        ->and(balanceOf(variantNamed($product, 'L / Green'))->with_supplier)->toBe(1);

    $receive(3, 'r2')->assertSessionHasErrors('lines.0.quantity');
    $receive(1, 'r3')->assertSessionHasNoErrors();
    $receive(1, 'r3')->assertSessionHasNoErrors();

    expect($claim->refresh()->status)->toBe(ClaimStatus::Closed)
        ->and(balanceOf(variantNamed($product, 'L / Green'))->available)->toBe(10)
        ->and(balanceOf(variantNamed($product, 'L / Green'))->with_supplier)->toBe(0)
        ->and(DamageCase::query()->sole()->status->value)->toBe('resolved');
});

it('receives an approved replacement variant different from the damaged one', function () {
    [$product, $claim] = damagedClaim();
    $red = variantNamed($product, 'L / Red');
    approveClaim($claim, replacementVariantId: $red->id);
    $operator = userWithRole(Role::StockOperator);
    actingAs($operator)->post(route('claims.dispatch', $claim), ['dispatch_date' => now('Asia/Dhaka')->toDateString()]);

    actingAs($operator)->post(route('claims.receive', $claim), [
        'received_date' => now('Asia/Dhaka')->toDateString(),
        'idempotency_key' => 'swap',
        'lines' => [['line_id' => $claim->lines()->sole()->id, 'quantity' => 4]],
    ])->assertSessionHasNoErrors();

    expect(balanceOf(variantNamed($product, 'L / Green'))->with_supplier)->toBe(0)
        ->and(balanceOf(variantNamed($product, 'L / Green'))->available)->toBe(6)
        ->and(balanceOf($red)->available)->toBe(9);
});

it('releases claimed units back to the damage case when a claim is rejected', function () {
    [$product, $claim] = damagedClaim();

    actingAs(userWithRole(Role::ExchangeApprover))
        ->post(route('claims.reject', $claim), ['reason' => 'Out of warranty'])
        ->assertSessionHasNoErrors();

    expect($claim->refresh()->status)->toBe(ClaimStatus::Rejected)
        ->and(DamageCase::query()->sole()->lines()->sole()->claimed_quantity)->toBe(0)
        ->and(DamageCase::query()->sole()->status->value)->toBe('open')
        ->and(balanceOf(variantNamed($product, 'L / Green'))->damaged)->toBe(4);
});

it('writes off outstanding units when a claim is closed short', function () {
    [$product, $claim] = damagedClaim();
    approveClaim($claim);
    actingAs(userWithRole(Role::StockOperator))->post(route('claims.dispatch', $claim), ['dispatch_date' => now('Asia/Dhaka')->toDateString()]);

    actingAs(userWithRole(Role::ExchangeApprover))
        ->post(route('claims.close-short', $claim), ['reason' => 'Supplier closed'])
        ->assertSessionHasNoErrors();

    expect($claim->refresh()->status)->toBe(ClaimStatus::Closed)
        ->and(balanceOf(variantNamed($product, 'L / Green'))->with_supplier)->toBe(0)
        ->and(StockMovement::query()->where('type', MovementType::SupplierWriteOff)->sole()->total_quantity)->toBe(4);
});

it('only lets authorised roles approve claims', function (Role $role) {
    [, $claim] = damagedClaim();
    actingAs(userWithRole(Role::InventoryManager))->post(route('claims.review', $claim));
    actingAs(userWithRole(Role::SupplierCoordinator))->post(route('claims.agreement', $claim), [
        'lines' => $claim->lines->map(fn ($line) => ['line_id' => $line->id, 'approved_quantity' => $line->claimed_quantity])->all(),
    ]);

    actingAs(userWithRole($role))->post(route('claims.approve', $claim))->assertForbidden();

    expect($claim->refresh()->status)->toBe(ClaimStatus::SupplierAgreed);
})->with([
    'stock operator' => Role::StockOperator,
    'inventory manager' => Role::InventoryManager,
    'supplier coordinator' => Role::SupplierCoordinator,
]);

it('refuses to skip workflow steps', function () {
    [, $claim] = damagedClaim();

    actingAs(userWithRole(Role::ExchangeApprover))->post(route('claims.approve', $claim))->assertForbidden();
    actingAs(userWithRole(Role::StockOperator))->post(route('claims.dispatch', $claim), ['dispatch_date' => now()->toDateString()])->assertForbidden();
});
