<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Enums\RecordStatus;
use App\Enums\StockCondition;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Inventory\InventoryPoster;
use App\Services\Inventory\MovementData;
use App\Services\Inventory\MovementLine;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Posts simple receipts (Stock IN, Opening) and issues (Stock OUT) of available stock.
 */
class StockEntryService
{
    public function __construct(
        private InventoryPoster $poster,
        private BusinessCalendar $calendar,
        private Idempotency $idempotency,
        private ActivityLogger $activity,
    ) {}

    /**
     * @param  array{warehouse_id: int, business_date?: string|null, supplier_id?: int|null, reference?: string|null, notes?: string|null, idempotency_key?: string|null, lines: list<array{variant_id: int, quantity: int|null}>}  $data
     * @return array{0: StockMovement, 1: bool}
     */
    public function post(MovementType $type, array $data, User $user): array
    {
        $data['idempotency_key'] = $this->idempotency->scope($data['idempotency_key'] ?? null, $user);

        if (! in_array($type, [MovementType::StockIn, MovementType::StockOut, MovementType::Opening], true)) {
            throw new InvalidArgumentException("{$type->value} is not a stock entry type.");
        }

        $isInbound = $type !== MovementType::StockOut;

        $lines = collect($data['lines'])
            ->map(fn (array $line, int $index): array => [...$line, 'index' => $index])
            ->filter(fn (array $line): bool => (int) ($line['quantity'] ?? 0) > 0)
            ->values();

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => 'অন্তত একটি ভ্যারিয়েন্টের পরিমাণ লিখুন।']);
        }

        if ($isInbound) {
            $archived = ProductVariant::query()
                ->whereIn('id', $lines->pluck('variant_id'))
                ->where('status', RecordStatus::Archived)
                ->pluck('id');

            if ($archived->isNotEmpty()) {
                $line = $lines->firstWhere('variant_id', $archived->first());

                throw ValidationException::withMessages(["lines.{$line['index']}.quantity" => 'আর্কাইভ করা ভ্যারিয়েন্টে স্টক যোগ করা যাবে না।']);
            }
        }

        return $this->idempotency->run(
            $data['idempotency_key'] ?? null,
            fn (string $key): ?StockMovement => StockMovement::query()->where('idempotency_key', $key)->first(),
            function () use ($type, $data, $user, $lines, $isInbound): StockMovement {
                $movement = $this->poster->post(new MovementData(
                    type: $type,
                    warehouseId: (int) $data['warehouse_id'],
                    lines: $lines->map(fn (array $line): MovementLine => new MovementLine(
                        variantId: (int) $line['variant_id'],
                        quantity: (int) $line['quantity'],
                        from: $isInbound ? null : StockCondition::Available,
                        to: $isInbound ? StockCondition::Available : null,
                        errorKey: "lines.{$line['index']}.quantity",
                    ))->all(),
                    businessDate: $this->calendar->parse($data['business_date'] ?? null),
                    user: $user,
                    supplierId: $data['supplier_id'] ?? null,
                    externalReference: $data['reference'] ?? null,
                    notes: $data['notes'] ?? null,
                    idempotencyKey: $data['idempotency_key'] ?? null,
                ));

                $this->activity->log(
                    "stock.{$type->value}",
                    "{$type->label()} {$movement->reference_no}: ".Bangla::number($movement->total_quantity).' পিস',
                    $movement,
                    user: $user,
                );

                return $movement;
            },
        );
    }
}
