<?php

namespace App\Services;

use App\Enums\StockCondition;
use App\Models\StockBalance;
use App\Models\StockLedgerEntry;
use Illuminate\Support\Collection;

/**
 * Verifies that every stored balance equals the sum of its immutable ledger entries.
 */
class ReconciliationService
{
    /**
     * @return Collection<int, array{variant_id: int, sku: string, product: string, variant: string, warehouse_id: int, condition: string, balance: int, ledger: int, difference: int}>
     */
    public function discrepancies(): Collection
    {
        $ledger = StockLedgerEntry::query()
            ->selectRaw('product_variant_id, warehouse_id, `condition`, SUM(quantity_change) as total')
            ->groupBy('product_variant_id', 'warehouse_id', 'condition')
            ->get()
            ->mapWithKeys(fn ($row): array => ["{$row->product_variant_id}:{$row->warehouse_id}:{$row->condition->value}" => (int) $row->total]);

        $issues = collect();

        StockBalance::query()->with('variant.product:id,name')->orderBy('id')->chunk(500, function (Collection $balances) use ($ledger, $issues): void {
            foreach ($balances as $balance) {
                foreach (StockCondition::cases() as $condition) {
                    $expected = $ledger->get("{$balance->product_variant_id}:{$balance->warehouse_id}:{$condition->value}", 0);
                    $actual = $balance->quantity($condition);

                    if ($expected !== $actual) {
                        $issues->push([
                            'variant_id' => $balance->product_variant_id,
                            'sku' => $balance->variant->sku,
                            'product' => $balance->variant->product->name,
                            'variant' => $balance->variant->name,
                            'warehouse_id' => $balance->warehouse_id,
                            'condition' => $condition->label(),
                            'balance' => $actual,
                            'ledger' => $expected,
                            'difference' => $actual - $expected,
                        ]);
                    }
                }
            }
        });

        return $issues;
    }

    /**
     * @return array{balances: int, ledger_entries: int, movements: int}
     */
    public function summary(): array
    {
        return [
            'balances' => StockBalance::query()->count(),
            'ledger_entries' => StockLedgerEntry::query()->count(),
            'movements' => (int) StockLedgerEntry::query()->distinct()->count('stock_movement_id'),
        ];
    }
}
