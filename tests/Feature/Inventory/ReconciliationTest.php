<?php

use App\Enums\Role;
use App\Models\StockBalance;
use App\Services\ReconciliationService;

use function Pest\Laravel\actingAs;

it('reports no discrepancies when balances match the ledger', function () {
    $product = makeProduct(['Size' => ['L']], [['L']]);
    receiveStock($product, ['L' => 12]);

    expect(app(ReconciliationService::class)->discrepancies())->toBeEmpty();
});

it('detects a balance that was changed outside the posting engine', function () {
    $product = makeProduct(['Size' => ['L']], [['L']]);
    receiveStock($product, ['L' => 12]);
    StockBalance::query()->update(['available' => 15]);

    $issues = app(ReconciliationService::class)->discrepancies();

    expect($issues)->toHaveCount(1)
        ->and($issues->first()['difference'])->toBe(3);
});

it('renders the reconciliation page for authorised users', function () {
    actingAs(userWithRole(Role::InventoryManager))->get(route('reconciliation.index'))->assertOk();
    actingAs(userWithRole(Role::StockOperator))->get(route('reconciliation.index'))->assertForbidden();
});
