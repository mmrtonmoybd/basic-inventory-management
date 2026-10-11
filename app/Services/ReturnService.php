<?php

namespace App\Services;

use App\Enums\DamageCaseStatus;
use App\Enums\MovementType;
use App\Enums\ReturnStatus;
use App\Enums\StockCondition;
use App\Models\CustomerReturn;
use App\Models\DamageCase;
use App\Models\ReturnInspection;
use App\Models\ReturnLine;
use App\Models\StockMovement;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use App\Services\Inventory\InventoryPoster;
use App\Services\Inventory\MovementData;
use App\Services\Inventory\MovementLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Customer returns land in Inspection stock; inspection releases them to Available or Damaged.
 */
class ReturnService
{
    public function __construct(
        private InventoryPoster $poster,
        private BusinessCalendar $calendar,
        private Idempotency $idempotency,
        private ActivityLogger $activity,
        private Notifier $notifier,
    ) {}

    /**
     * @param  array{warehouse_id: int, return_date?: string|null, customer_name?: string|null, reference?: string|null, reason?: string|null, notes?: string|null, idempotency_key?: string|null, lines: list<array{variant_id: int, quantity: int|null, reason?: string|null, note?: string|null}>}  $data
     * @return array{0: CustomerReturn, 1: bool}
     */
    public function create(array $data, User $user): array
    {
        $data['idempotency_key'] = $this->idempotency->scope($data['idempotency_key'] ?? null, $user);

        $lines = collect($data['lines'])->filter(fn (array $line): bool => (int) ($line['quantity'] ?? 0) > 0)->values();

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => 'অন্তত একটি ভ্যারিয়েন্টের ফেরত আসা পরিমাণ লিখুন।']);
        }

        if ($lines->pluck('variant_id')->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['lines' => 'একটি রিটার্নে প্রতিটি ভ্যারিয়েন্ট একবারই দেওয়া যাবে।']);
        }

        return $this->idempotency->run(
            $data['idempotency_key'] ?? null,
            fn (string $key): ?CustomerReturn => StockMovement::query()->where('idempotency_key', $key)->first()?->source,
            fn (): CustomerReturn => DB::transaction(function () use ($data, $user, $lines): CustomerReturn {
                $date = $this->calendar->parse($data['return_date'] ?? null);

                $return = CustomerReturn::query()->create([
                    'warehouse_id' => $data['warehouse_id'],
                    'return_date' => $date->toDateString(),
                    'customer_name' => $data['customer_name'] ?? null,
                    'reference' => $data['reference'] ?? null,
                    'reason' => $data['reason'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'status' => ReturnStatus::PendingInspection,
                    'created_by' => $user->id,
                ]);

                $return->update(['return_no' => sprintf('RET-%s-%05d', $date->format('ymd'), $return->id)]);

                foreach ($lines as $line) {
                    $return->lines()->create([
                        'product_variant_id' => $line['variant_id'],
                        'quantity' => (int) $line['quantity'],
                        'reason' => $line['reason'] ?? null,
                        'note' => $line['note'] ?? null,
                    ]);
                }

                $this->poster->post(new MovementData(
                    type: MovementType::ReturnReceipt,
                    warehouseId: (int) $data['warehouse_id'],
                    lines: $lines->map(fn (array $line): MovementLine => new MovementLine(
                        variantId: (int) $line['variant_id'],
                        quantity: (int) $line['quantity'],
                        to: StockCondition::Inspection,
                    ))->all(),
                    businessDate: $date,
                    user: $user,
                    source: $return,
                    externalReference: $data['reference'] ?? null,
                    notes: $data['notes'] ?? null,
                    idempotencyKey: $data['idempotency_key'] ?? null,
                ));

                $this->activity->log('return.created', "রিটার্ন {$return->return_no} লেখা হয়েছে (".Bangla::number($lines->sum('quantity')).' পিস)', $return, user: $user);

                DB::afterCommit(fn () => $this->notifier->notifyPermission('returns.inspect', new WorkflowNotification(
                    'return_pending',
                    "রিটার্ন {$return->return_no} পরীক্ষার অপেক্ষায়",
                    Bangla::number($lines->sum('quantity')).' পিস ফেরত মাল পরীক্ষাধীন স্টকে আছে।',
                    route('returns.show', $return),
                ), $user));

                return $return;
            }),
        );
    }

    /**
     * Records a (possibly partial) inspection. Damaged units automatically open a damage case.
     *
     * @param  array{notes?: string|null, damage_reason?: string|null, lines: list<array{return_line_id: int, good_quantity?: int|null, damaged_quantity?: int|null}>}  $data
     */
    public function inspect(CustomerReturn $return, array $data, User $user): ReturnInspection
    {
        return DB::transaction(function () use ($return, $data, $user): ReturnInspection {
            $return = CustomerReturn::query()->whereKey($return->id)->lockForUpdate()->firstOrFail();
            $returnLines = $return->lines()->lockForUpdate()->get()->keyBy('id');

            $inspected = [];
            $errors = [];

            foreach ($data['lines'] as $index => $input) {
                $good = (int) ($input['good_quantity'] ?? 0);
                $damaged = (int) ($input['damaged_quantity'] ?? 0);

                if ($good === 0 && $damaged === 0) {
                    continue;
                }

                /** @var ReturnLine|null $line */
                $line = $returnLines->get((int) $input['return_line_id']);

                if ($line === null) {
                    $errors["lines.{$index}.return_line_id"] = 'এই লাইনটি এই রিটার্নের নয়।';

                    continue;
                }

                if ($good + $damaged > $line->pendingQuantity()) {
                    $errors["lines.{$index}.good_quantity"] = "পরীক্ষা করার মতো আর মাত্র ".Bangla::number($line->pendingQuantity())." পিস বাকি আছে।";

                    continue;
                }

                $inspected[] = compact('line', 'good', 'damaged');
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            if ($inspected === []) {
                throw ValidationException::withMessages(['lines' => 'অন্তত একটি লাইনে ভালো বা ক্ষতিগ্রস্ত পরিমাণ লিখুন।']);
            }

            $inspection = $return->inspections()->create([
                'inspected_by' => $user->id,
                'notes' => $data['notes'] ?? null,
            ]);

            $movementLines = [];

            foreach ($inspected as ['line' => $line, 'good' => $good, 'damaged' => $damaged]) {
                $inspection->lines()->create([
                    'return_line_id' => $line->id,
                    'good_quantity' => $good,
                    'damaged_quantity' => $damaged,
                    'damage_reason' => $damaged > 0 ? ($data['damage_reason'] ?? null) : null,
                ]);

                $line->increment('good_quantity', $good);
                $line->increment('damaged_quantity', $damaged);

                if ($good > 0) {
                    $movementLines[] = new MovementLine($line->product_variant_id, $good, StockCondition::Inspection, StockCondition::Available, 'পরীক্ষায় ভালো পাওয়া গেছে');
                }

                if ($damaged > 0) {
                    $movementLines[] = new MovementLine($line->product_variant_id, $damaged, StockCondition::Inspection, StockCondition::Damaged, 'পরীক্ষায় ক্ষতিগ্রস্ত পাওয়া গেছে');
                }
            }

            $movement = $this->poster->post(new MovementData(
                type: MovementType::ReturnInspection,
                warehouseId: $return->warehouse_id,
                lines: $movementLines,
                businessDate: $this->calendar->today(),
                user: $user,
                source: $return,
                notes: $data['notes'] ?? null,
            ));

            $inspection->update(['stock_movement_id' => $movement->id]);

            $damagedLines = collect($inspected)->filter(fn (array $row): bool => $row['damaged'] > 0);

            if ($damagedLines->isNotEmpty()) {
                $case = DamageCase::query()->create([
                    'warehouse_id' => $return->warehouse_id,
                    'customer_return_id' => $return->id,
                    'stock_movement_id' => $movement->id,
                    'source' => 'customer_return',
                    'damage_date' => $this->calendar->today()->toDateString(),
                    'reason' => $data['damage_reason'] ?? 'রিটার্ন পরীক্ষায় ক্ষতিগ্রস্ত পাওয়া গেছে',
                    'internal_note' => "রিটার্ন {$return->return_no} থেকে",
                    'status' => DamageCaseStatus::Open,
                    'reported_by' => $user->id,
                ]);

                $case->update(['case_no' => sprintf('DC-%s-%05d', $this->calendar->today()->format('ymd'), $case->id)]);

                foreach ($damagedLines as ['line' => $line, 'damaged' => $damaged]) {
                    $case->lines()->create(['product_variant_id' => $line->product_variant_id, 'quantity' => $damaged]);
                }
            }

            $remaining = $return->lines()->get()->sum(fn (ReturnLine $line): int => $line->pendingQuantity());

            $return->update(['status' => $remaining === 0 ? ReturnStatus::Inspected : ReturnStatus::PartiallyInspected]);

            $this->activity->log('return.inspected', "রিটার্ন {$return->return_no} পরীক্ষা করা হয়েছে", $return, [
                'good' => collect($inspected)->sum('good'),
                'damaged' => collect($inspected)->sum('damaged'),
            ], $user);

            return $inspection;
        });
    }
}
