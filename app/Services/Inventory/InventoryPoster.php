<?php

namespace App\Services\Inventory;

use App\Enums\StockCondition;
use App\Models\ProductVariant;
use App\Models\StockBalance;
use App\Models\StockLedgerEntry;
use App\Models\StockMovement;
use App\Models\StockMovementLine;
use App\Notifications\LowStockAlert;
use App\Services\Bangla;
use App\Services\Notifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * The single writer of stock balances and the immutable stock ledger.
 *
 * Every stock-changing operation in the application goes through {@see post()}. Must be called
 * inside (or will itself open) a database transaction; balance rows are locked FOR UPDATE in a
 * deterministic order so concurrent postings serialise without deadlocking.
 */
class InventoryPoster
{
    public function __construct(private Notifier $notifier) {}

    public function post(MovementData $data): StockMovement
    {
        if ($data->lines === []) {
            throw new InvalidArgumentException('A stock movement needs at least one line.');
        }

        return DB::transaction(function () use ($data): StockMovement {
            $variantIds = collect($data->lines)->pluck('variantId')->unique()->sort()->values();

            $this->guardVariantsExist($variantIds);

            $balances = $this->lockBalances($data->warehouseId, $variantIds);
            $running = $this->applyDeltas($data, $balances);

            $movement = $this->createMovement($data);
            $this->writeLinesAndLedger($movement, $data, $balances);

            $this->queueLowStockAlerts($balances, $running);

            foreach ($balances as $variantId => $balance) {
                $balance->fill($running[$variantId])->save();
            }

            return $movement;
        }, attempts: 3);
    }

    /**
     * @param  Collection<int, int>  $variantIds
     */
    private function guardVariantsExist(Collection $variantIds): void
    {
        $found = ProductVariant::query()->whereIn('id', $variantIds)->count();

        if ($found !== $variantIds->count()) {
            throw ValidationException::withMessages(['lines' => 'বেছে নেওয়া এক বা একাধিক ভ্যারিয়েন্ট আর নেই। পাতাটি রিফ্রেশ করে আবার চেষ্টা করুন।']);
        }
    }

    /**
     * Ensures a balance row exists for every variant, then locks them all in id order.
     *
     * @param  Collection<int, int>  $variantIds
     * @return Collection<int, StockBalance>
     */
    private function lockBalances(int $warehouseId, Collection $variantIds): Collection
    {
        $now = now();

        StockBalance::query()->insertOrIgnore($variantIds->map(fn (int $variantId): array => [
            'product_variant_id' => $variantId,
            'warehouse_id' => $warehouseId,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());

        return StockBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->whereIn('product_variant_id', $variantIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('product_variant_id');
    }

    /**
     * Computes the resulting balances and rejects any posting that would drive a bucket negative.
     *
     * @param  Collection<int, StockBalance>  $balances
     * @return array<int, array<string, int>>
     */
    private function applyDeltas(MovementData $data, Collection $balances): array
    {
        $running = $balances->map(fn (StockBalance $balance): array => [
            StockCondition::Available->value => $balance->available,
            StockCondition::Inspection->value => $balance->inspection,
            StockCondition::Damaged->value => $balance->damaged,
            StockCondition::WithSupplier->value => $balance->with_supplier,
        ])->all();

        $errors = [];

        foreach ($data->lines as $index => $line) {
            if ($line->from !== null) {
                $running[$line->variantId][$line->from->value] -= $line->quantity;

                if ($running[$line->variantId][$line->from->value] < 0) {
                    $current = $balances[$line->variantId]->quantity($line->from);
                    $errors[$line->errorKey ?? "lines.{$index}.quantity"] = sprintf(
                        'যথেষ্ট %s স্টক নেই: আছে %s পিস, চাওয়া হয়েছে %s পিস।',
                        $line->from->label(),
                        Bangla::number($current),
                        Bangla::number($line->quantity),
                    );
                }
            }

            if ($line->to !== null) {
                $running[$line->variantId][$line->to->value] += $line->quantity;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $running;
    }

    private function createMovement(MovementData $data): StockMovement
    {
        $movement = StockMovement::query()->create([
            'type' => $data->type,
            'warehouse_id' => $data->warehouseId,
            'supplier_id' => $data->supplierId,
            'source_type' => $data->source?->getMorphClass(),
            'source_id' => $data->source?->getKey(),
            'business_date' => $data->businessDate->toDateString(),
            'external_reference' => $data->externalReference,
            'notes' => $data->notes,
            'idempotency_key' => $data->idempotencyKey,
            'total_quantity' => array_sum(array_map(fn (MovementLine $line): int => $line->quantity, $data->lines)),
            'created_by' => $data->user?->getKey(),
        ]);

        $referenceNo = sprintf('%s-%s-%05d', $data->type->prefix(), $data->businessDate->format('ymd'), $movement->id);

        StockMovement::query()->whereKey($movement->id)->toBase()->update(['reference_no' => $referenceNo]);
        $movement->setAttribute('reference_no', $referenceNo)->syncOriginal();

        return $movement;
    }

    /**
     * @param  Collection<int, StockBalance>  $balances
     */
    private function writeLinesAndLedger(StockMovement $movement, MovementData $data, Collection $balances): void
    {
        $running = $balances->map(fn (StockBalance $balance): array => [
            StockCondition::Available->value => $balance->available,
            StockCondition::Inspection->value => $balance->inspection,
            StockCondition::Damaged->value => $balance->damaged,
            StockCondition::WithSupplier->value => $balance->with_supplier,
        ])->all();

        $now = now();
        $entries = [];

        foreach ($data->lines as $line) {
            $movementLine = StockMovementLine::query()->create([
                'stock_movement_id' => $movement->id,
                'product_variant_id' => $line->variantId,
                'from_condition' => $line->from,
                'to_condition' => $line->to,
                'quantity' => $line->quantity,
                'note' => $line->note,
            ]);

            $changes = array_filter([
                [$line->from, -$line->quantity],
                [$line->to, $line->quantity],
            ], fn (array $change): bool => $change[0] !== null);

            foreach ($changes as [$condition, $change]) {
                $running[$line->variantId][$condition->value] += $change;

                $entries[] = [
                    'stock_movement_id' => $movement->id,
                    'stock_movement_line_id' => $movementLine->id,
                    'product_variant_id' => $line->variantId,
                    'warehouse_id' => $data->warehouseId,
                    'movement_type' => $data->type->value,
                    'condition' => $condition->value,
                    'quantity_change' => $change,
                    'balance_after' => $running[$line->variantId][$condition->value],
                    'business_date' => $data->businessDate->toDateString(),
                    'created_at' => $now,
                ];
            }
        }

        StockLedgerEntry::query()->insert($entries);
    }

    /**
     * Alerts once, at the moment available stock crosses down through a variant's threshold.
     *
     * @param  Collection<int, StockBalance>  $balances
     * @param  array<int, array<string, int>>  $running
     */
    private function queueLowStockAlerts(Collection $balances, array $running): void
    {
        $available = StockCondition::Available->value;

        $decreased = $balances
            ->filter(fn (StockBalance $balance, int $variantId): bool => $running[$variantId][$available] < $balance->available)
            ->keys();

        if ($decreased->isEmpty()) {
            return;
        }

        $variants = ProductVariant::query()
            ->with('product:id,name')
            ->whereIn('id', $decreased)
            ->where('low_stock_threshold', '>', 0)
            ->get()
            ->filter(fn (ProductVariant $variant): bool => $balances[$variant->id]->available > $variant->low_stock_threshold
                && $running[$variant->id][$available] <= $variant->low_stock_threshold);

        if ($variants->isEmpty()) {
            return;
        }

        DB::afterCommit(function () use ($variants, $running, $available): void {
            foreach ($variants as $variant) {
                $this->notifier->notifyPermission(
                    'stock.in',
                    new LowStockAlert($variant, $running[$variant->id][$available]),
                );
            }
        });
    }
}
