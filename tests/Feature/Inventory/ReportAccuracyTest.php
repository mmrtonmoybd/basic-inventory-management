<?php

use App\Enums\MovementType;
use App\Enums\Role;
use App\Models\Product;
use App\Models\User;
use App\Services\Reports\ReportService;
use App\Services\ReturnService;
use App\Services\StockEntryService;

use function Pest\Laravel\actingAs;

/**
 * @param  array<string, int>  $quantities
 */
function postOn(MovementType $type, Product $product, array $quantities, string $date): void
{
    app(StockEntryService::class)->post($type, [
        'warehouse_id' => defaultWarehouse()->id,
        'business_date' => $date,
        'lines' => collect($quantities)->map(fn (int $quantity, string $name) => ['variant_id' => variantNamed($product, $name)->id, 'quantity' => $quantity])->values()->all(),
    ], User::factory()->create());
}

it('derives opening, movements and closing balances from the ledger', function () {
    $product = makeProduct(['Size' => ['L', 'XL']], [['L'], ['XL']]);
    postOn(MovementType::StockIn, $product, ['L' => 100, 'XL' => 50], '2026-09-01');
    postOn(MovementType::StockOut, $product, ['L' => 30], '2026-09-10');
    postOn(MovementType::StockIn, $product, ['XL' => 20], '2026-10-02');
    postOn(MovementType::StockOut, $product, ['L' => 5, 'XL' => 10], '2026-10-03');

    $report = app(ReportService::class)->run('product-stock', ['from' => '2026-10-01', 'to' => '2026-10-31']);
    $row = $report['rows'][0];

    expect($row['opening'])->toBe(120)
        ->and($row['stock_in'])->toBe(20)
        ->and($row['stock_out'])->toBe(15)
        ->and($row['closing'])->toBe(125)
        ->and($row['available'])->toBe(125);
});

it('does not double count returns that are inspected back into stock', function () {
    $product = makeProduct(['Size' => ['L']], [['L']]);
    postOn(MovementType::StockIn, $product, ['L' => 10], '2026-10-01');

    [$return] = app(ReturnService::class)->create([
        'warehouse_id' => defaultWarehouse()->id,
        'return_date' => '2026-10-02',
        'lines' => [['variant_id' => variantNamed($product, 'L')->id, 'quantity' => 4]],
    ], User::factory()->create());
    app(ReturnService::class)->inspect($return, ['lines' => [['return_line_id' => $return->lines()->sole()->id, 'good_quantity' => 3, 'damaged_quantity' => 1]]], User::factory()->create());

    $daily = collect(app(ReportService::class)->run('daily-in-out', ['from' => '2026-10-01', 'to' => '2026-10-31'])['rows']);
    $variant = app(ReportService::class)->run('variant-stock', ['from' => '2026-10-01', 'to' => '2026-10-31'])['rows'][0];

    expect($daily->sum('returns'))->toBe(4)
        ->and($daily->sum('stock_in'))->toBe(10)
        ->and($daily->last()['closing'])->toBe(14)
        ->and($variant['closing'])->toBe(14)
        ->and($variant['available'])->toBe(13)
        ->and($variant['damaged'])->toBe(1);
});

it('groups stock by size and by color', function () {
    $product = makeProduct(['Size' => ['L', 'XL'], 'Color' => ['Red', 'Black']], [['L', 'Red'], ['L', 'Black'], ['XL', 'Black']]);
    postOn(MovementType::StockIn, $product, ['L / Red' => 5, 'L / Black' => 7, 'XL / Black' => 11], now('Asia/Dhaka')->toDateString());

    $sizes = collect(app(ReportService::class)->run('size-stock', [])['rows'])->pluck('closing', 'value');
    $colors = collect(app(ReportService::class)->run('color-stock', [])['rows'])->pluck('closing', 'value');

    expect($sizes->all())->toBe(['L' => 12, 'XL' => 11])
        ->and($colors->all())->toBe(['Black' => 18, 'Red' => 5]);
});

it('exports a report as csv', function () {
    $product = makeProduct(['Size' => ['L']], [['L']]);
    postOn(MovementType::StockIn, $product, ['L' => 9], now('Asia/Dhaka')->toDateString());

    $response = actingAs(userWithRole(Role::ReportViewer))->get(route('reports.export', ['variant-stock', 'csv']));

    $response->assertOk();
    expect($response->streamedContent())->toContain('SKU')->toContain(variantNamed($product, 'L')->sku);
});

it('forbids report exports without the export permission', function () {
    actingAs(userWithRole(Role::StockOperator))->get(route('reports.export', ['variant-stock', 'csv']))->assertForbidden();
});
