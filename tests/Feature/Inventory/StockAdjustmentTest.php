<?php

use App\Enums\AdjustmentStatus;
use App\Enums\Role;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\User;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

function requestCount(Product $product, int $counted, User $requester): StockAdjustment
{
    actingAs($requester)->post(route('adjustments.store'), [
        'warehouse_id' => defaultWarehouse()->id,
        'count_date' => now('Asia/Dhaka')->toDateString(),
        'reason' => 'Cycle count',
        'idempotency_key' => (string) Str::uuid(),
        'lines' => [['variant_id' => variantNamed($product, 'L')->id, 'condition' => 'available', 'counted_quantity' => $counted]],
    ])->assertRedirect();

    return StockAdjustment::query()->latest('id')->firstOrFail();
}

it('does not change stock until an adjustment is approved', function () {
    $product = makeProduct(['Size' => ['L']], [['L']]);
    receiveStock($product, ['L' => 20]);

    $adjustment = requestCount($product, 17, userWithRole(Role::StockOperator));

    expect($adjustment->lines()->sole()->difference)->toBe(-3)
        ->and(balanceOf(variantNamed($product, 'L'))->available)->toBe(20);

    actingAs(userWithRole(Role::InventoryManager))->post(route('adjustments.approve', $adjustment))->assertSessionHasNoErrors();

    expect($adjustment->refresh()->status)->toBe(AdjustmentStatus::Approved)
        ->and(balanceOf(variantNamed($product, 'L'))->available)->toBe(17);
});

it('forbids approving your own adjustment', function () {
    $product = makeProduct(['Size' => ['L']], [['L']]);
    receiveStock($product, ['L' => 20]);
    $manager = userWithRole(Role::InventoryManager);
    $adjustment = requestCount($product, 25, $manager);

    actingAs($manager)->post(route('adjustments.approve', $adjustment))->assertForbidden();

    expect(balanceOf(variantNamed($product, 'L'))->available)->toBe(20);
});

it('leaves stock untouched when an adjustment is rejected', function () {
    $product = makeProduct(['Size' => ['L']], [['L']]);
    receiveStock($product, ['L' => 20]);
    $adjustment = requestCount($product, 30, userWithRole(Role::StockOperator));

    actingAs(userWithRole(Role::InventoryManager))->post(route('adjustments.reject', $adjustment), ['note' => 'Recount'])->assertSessionHasNoErrors();

    expect($adjustment->refresh()->status)->toBe(AdjustmentStatus::Rejected)
        ->and(balanceOf(variantNamed($product, 'L'))->available)->toBe(20);
});

it('rejects a count that matches the system quantity', function () {
    $product = makeProduct(['Size' => ['L']], [['L']]);
    receiveStock($product, ['L' => 20]);

    actingAs(userWithRole(Role::StockOperator))->post(route('adjustments.store'), [
        'warehouse_id' => defaultWarehouse()->id,
        'count_date' => now('Asia/Dhaka')->toDateString(),
        'reason' => 'Count',
        'idempotency_key' => 'same',
        'lines' => [['variant_id' => variantNamed($product, 'L')->id, 'condition' => 'available', 'counted_quantity' => 20]],
    ])->assertSessionHasErrors('lines');
});
