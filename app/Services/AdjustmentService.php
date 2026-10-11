<?php

namespace App\Services;

use App\Enums\AdjustmentStatus;
use App\Enums\MovementType;
use App\Enums\StockCondition;
use App\Models\ApprovalRecord;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentLine;
use App\Models\StockBalance;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use App\Services\Inventory\InventoryPoster;
use App\Services\Inventory\MovementData;
use App\Services\Inventory\MovementLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Physical count corrections. A request snapshots system quantities; only approval posts to the ledger.
 */
class AdjustmentService
{
    public function __construct(
        private InventoryPoster $poster,
        private BusinessCalendar $calendar,
        private Idempotency $idempotency,
        private ActivityLogger $activity,
        private Notifier $notifier,
    ) {}

    /**
     * @param  array{warehouse_id: int, count_date?: string|null, reason: string, notes?: string|null, idempotency_key?: string|null, lines: list<array{variant_id: int, condition: string, counted_quantity: int|null}>}  $data
     * @return array{0: StockAdjustment, 1: bool}
     */
    public function request(array $data, User $user): array
    {
        $data['idempotency_key'] = $this->idempotency->scope($data['idempotency_key'] ?? null, $user);

        $keys = collect($data['lines'])->map(fn (array $line): string => $line['variant_id'].':'.$line['condition']);

        if ($keys->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['lines' => 'একটি সমন্বয়ে প্রতিটি ভ্যারিয়েন্ট ও অবস্থা একবারই গণনা করা যাবে।']);
        }

        return $this->idempotency->run(
            $data['idempotency_key'] ?? null,
            fn (string $key): ?StockAdjustment => StockAdjustment::query()->where('idempotency_key', $key)->first(),
            fn (): StockAdjustment => DB::transaction(function () use ($data, $user): StockAdjustment {
                $warehouseId = (int) $data['warehouse_id'];
                $balances = StockBalance::query()
                    ->where('warehouse_id', $warehouseId)
                    ->whereIn('product_variant_id', collect($data['lines'])->pluck('variant_id'))
                    ->get()
                    ->keyBy('product_variant_id');

                $rows = collect($data['lines'])
                    ->filter(fn (array $line): bool => ($line['counted_quantity'] ?? null) !== null && $line['counted_quantity'] !== '')
                    ->map(function (array $line) use ($balances): array {
                        $condition = StockCondition::from($line['condition']);
                        $system = $balances->get($line['variant_id'])?->quantity($condition) ?? 0;

                        return [
                            'product_variant_id' => (int) $line['variant_id'],
                            'condition' => $condition,
                            'system_quantity' => $system,
                            'counted_quantity' => (int) $line['counted_quantity'],
                            'difference' => (int) $line['counted_quantity'] - $system,
                        ];
                    })
                    ->filter(fn (array $row): bool => $row['difference'] !== 0)
                    ->values();

                if ($rows->isEmpty()) {
                    throw ValidationException::withMessages(['lines' => 'গণনা আর সিস্টেমের হিসাব একই, তাই সমন্বয়ের কিছু নেই।']);
                }

                $date = $this->calendar->parse($data['count_date'] ?? null);

                $adjustment = StockAdjustment::query()->create([
                    'warehouse_id' => $warehouseId,
                    'count_date' => $date->toDateString(),
                    'reason' => $data['reason'],
                    'notes' => $data['notes'] ?? null,
                    'status' => AdjustmentStatus::Pending,
                    'idempotency_key' => $data['idempotency_key'] ?? null,
                    'requested_by' => $user->id,
                ]);

                $adjustment->update(['adjustment_no' => sprintf('ADJ-%s-%05d', $date->format('ymd'), $adjustment->id)]);
                $adjustment->lines()->createMany($rows->all());

                $this->record($adjustment, 'requested', null, AdjustmentStatus::Pending, $user, $data['notes'] ?? null);
                $this->activity->log('adjustment.requested', "স্টক সমন্বয় {$adjustment->adjustment_no} এর অনুরোধ করা হয়েছে", $adjustment, user: $user);

                DB::afterCommit(fn () => $this->notifier->notifyPermission('adjustments.approve', new WorkflowNotification(
                    'adjustment',
                    "স্টক সমন্বয় {$adjustment->adjustment_no} অনুমোদনের অপেক্ষায়",
                    Bangla::number($rows->count())."টি গণনার পার্থক্য: {$adjustment->reason}।",
                    route('adjustments.show', $adjustment),
                ), $user));

                return $adjustment;
            }),
        );
    }

    public function approve(StockAdjustment $adjustment, User $user, ?string $note = null): StockAdjustment
    {
        return DB::transaction(function () use ($adjustment, $user, $note): StockAdjustment {
            $adjustment = $this->lockPending($adjustment);

            $movement = $this->poster->post(new MovementData(
                type: MovementType::Adjustment,
                warehouseId: $adjustment->warehouse_id,
                lines: $adjustment->lines()->get()->map(fn (StockAdjustmentLine $line, int $index): MovementLine => new MovementLine(
                    variantId: $line->product_variant_id,
                    quantity: abs($line->difference),
                    from: $line->difference < 0 ? $line->condition : null,
                    to: $line->difference > 0 ? $line->condition : null,
                    note: 'গণনা '.Bangla::number($line->counted_quantity).', সিস্টেমে '.Bangla::number($line->system_quantity),
                    errorKey: "lines.{$index}",
                ))->all(),
                businessDate: $this->calendar->today(),
                user: $user,
                source: $adjustment,
                externalReference: $adjustment->adjustment_no,
                notes: $adjustment->reason,
            ));

            $adjustment->update([
                'status' => AdjustmentStatus::Approved,
                'stock_movement_id' => $movement->id,
                'decided_by' => $user->id,
                'decided_at' => now(),
                'decision_note' => $note,
            ]);

            $this->record($adjustment, 'approved', AdjustmentStatus::Pending, AdjustmentStatus::Approved, $user, $note);
            $this->activity->log('adjustment.approved', "স্টক সমন্বয় {$adjustment->adjustment_no} অনুমোদিত হয়েছে", $adjustment, user: $user);

            return $adjustment;
        });
    }

    public function reject(StockAdjustment $adjustment, User $user, string $note): StockAdjustment
    {
        return DB::transaction(function () use ($adjustment, $user, $note): StockAdjustment {
            $adjustment = $this->lockPending($adjustment);

            $adjustment->update([
                'status' => AdjustmentStatus::Rejected,
                'decided_by' => $user->id,
                'decided_at' => now(),
                'decision_note' => $note,
            ]);

            $this->record($adjustment, 'rejected', AdjustmentStatus::Pending, AdjustmentStatus::Rejected, $user, $note);
            $this->activity->log('adjustment.rejected', "স্টক সমন্বয় {$adjustment->adjustment_no} বাতিল হয়েছে", $adjustment, user: $user);

            return $adjustment;
        });
    }

    private function lockPending(StockAdjustment $adjustment): StockAdjustment
    {
        $adjustment = StockAdjustment::query()->whereKey($adjustment->id)->lockForUpdate()->firstOrFail();

        if ($adjustment->status !== AdjustmentStatus::Pending) {
            throw ValidationException::withMessages(['status' => "এই সমন্বয়টি আগেই {$adjustment->status->label()} হয়েছে।"]);
        }

        return $adjustment;
    }

    private function record(StockAdjustment $adjustment, string $action, ?AdjustmentStatus $from, AdjustmentStatus $to, User $user, ?string $comment): void
    {
        ApprovalRecord::query()->create([
            'approvable_type' => $adjustment->getMorphClass(),
            'approvable_id' => $adjustment->id,
            'action' => $action,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'comment' => $comment,
            'user_id' => $user->id,
        ]);
    }
}
