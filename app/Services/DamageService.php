<?php

namespace App\Services;

use App\Enums\DamageCaseStatus;
use App\Enums\MovementType;
use App\Enums\StockCondition;
use App\Models\DamageCase;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Inventory\InventoryPoster;
use App\Services\Inventory\MovementData;
use App\Services\Inventory\MovementLine;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records damaged stock found in the warehouse. Damaged units leave Available immediately.
 */
class DamageService
{
    public function __construct(
        private InventoryPoster $poster,
        private BusinessCalendar $calendar,
        private Idempotency $idempotency,
        private ActivityLogger $activity,
        private ExchangeClaimService $claims,
    ) {}

    /**
     * @param  array{warehouse_id: int, damage_date?: string|null, reason: string, internal_note?: string|null, supplier_message?: string|null, supplier_id?: int|null, request_exchange?: bool, idempotency_key?: string|null, lines: list<array{variant_id: int, quantity: int|null}>, images?: list<UploadedFile>}  $data
     * @return array{0: DamageCase, 1: bool}
     */
    public function record(array $data, User $user): array
    {
        $data['idempotency_key'] = $this->idempotency->scope($data['idempotency_key'] ?? null, $user);

        $lines = collect($data['lines'])
            ->map(fn (array $line, int $index): array => [...$line, 'index' => $index])
            ->filter(fn (array $line): bool => (int) ($line['quantity'] ?? 0) > 0)
            ->values();

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => 'অন্তত একটি ভ্যারিয়েন্টের ক্ষতিগ্রস্ত পরিমাণ লিখুন।']);
        }

        if (($data['request_exchange'] ?? false) && blank($data['supplier_id'] ?? null)) {
            throw ValidationException::withMessages(['supplier_id' => 'কোন সরবরাহকারীর কাছে বদলি চাইবেন, তা বেছে নিন।']);
        }

        [$case, $created] = $this->idempotency->run(
            $data['idempotency_key'] ?? null,
            fn (string $key): ?DamageCase => StockMovement::query()->where('idempotency_key', $key)->first()?->source,
            fn (): DamageCase => DB::transaction(function () use ($data, $user, $lines): DamageCase {
                $date = $this->calendar->parse($data['damage_date'] ?? null);

                $case = DamageCase::query()->create([
                    'warehouse_id' => $data['warehouse_id'],
                    'supplier_id' => $data['supplier_id'] ?? null,
                    'source' => 'warehouse',
                    'damage_date' => $date->toDateString(),
                    'reason' => $data['reason'],
                    'internal_note' => $data['internal_note'] ?? null,
                    'supplier_message' => $data['supplier_message'] ?? null,
                    'status' => DamageCaseStatus::Open,
                    'reported_by' => $user->id,
                ]);

                $case->update(['case_no' => sprintf('DC-%s-%05d', $date->format('ymd'), $case->id)]);

                foreach ($lines->groupBy('variant_id') as $variantId => $group) {
                    $case->lines()->create(['product_variant_id' => $variantId, 'quantity' => $group->sum('quantity')]);
                }

                $movement = $this->poster->post(new MovementData(
                    type: MovementType::Damage,
                    warehouseId: (int) $data['warehouse_id'],
                    lines: $lines->map(fn (array $line): MovementLine => new MovementLine(
                        variantId: (int) $line['variant_id'],
                        quantity: (int) $line['quantity'],
                        from: StockCondition::Available,
                        to: StockCondition::Damaged,
                        errorKey: "lines.{$line['index']}.quantity",
                    ))->all(),
                    businessDate: $date,
                    user: $user,
                    source: $case,
                    notes: $data['reason'],
                    idempotencyKey: $data['idempotency_key'] ?? null,
                ));

                $case->update(['stock_movement_id' => $movement->id]);

                foreach ($data['images'] ?? [] as $image) {
                    $case->images()->create([
                        'path' => $image->store("damage-cases/{$case->id}", 'local'),
                        'original_name' => $image->getClientOriginalName(),
                        'size' => $image->getSize(),
                    ]);
                }

                $this->activity->log('damage.recorded', "ক্ষতিগ্রস্ত মাল {$case->case_no} লেখা হয়েছে (".Bangla::number($lines->sum('quantity')).' পিস)', $case, user: $user);

                if ($data['request_exchange'] ?? false) {
                    $this->claims->submit($case, [
                        'supplier_id' => (int) $data['supplier_id'],
                        'supplier_message' => $data['supplier_message'] ?? null,
                        'lines' => $case->lines()->get()->map(fn ($line): array => [
                            'damage_case_line_id' => $line->id,
                            'quantity' => $line->quantity,
                        ])->all(),
                    ], $user);
                }

                return $case;
            }),
        );

        return [$case, $created];
    }
}
