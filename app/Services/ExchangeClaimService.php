<?php

namespace App\Services;

use App\Enums\ClaimStatus;
use App\Enums\DamageCaseStatus;
use App\Enums\MovementType;
use App\Enums\RecordStatus;
use App\Enums\StockCondition;
use App\Models\ApprovalRecord;
use App\Models\DamageCase;
use App\Models\DamageCaseLine;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\SupplierExchangeClaim;
use App\Models\SupplierExchangeLine;
use App\Models\SupplierExchangeReceipt;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use App\Services\Inventory\InventoryPoster;
use App\Services\Inventory\MovementData;
use App\Services\Inventory\MovementLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Supplier exchange workflow. Approvals change only claim state — stock moves solely on dispatch
 * (Damaged → With Supplier) and on physical receipt of replacements (With Supplier → Available).
 */
class ExchangeClaimService
{
    public function __construct(
        private InventoryPoster $poster,
        private BusinessCalendar $calendar,
        private Idempotency $idempotency,
        private ActivityLogger $activity,
        private Notifier $notifier,
    ) {}

    /**
     * @param  array{supplier_id: int, supplier_message?: string|null, lines: list<array{damage_case_line_id: int, quantity: int|null}>}  $data
     */
    public function submit(DamageCase $case, array $data, User $user): SupplierExchangeClaim
    {
        return DB::transaction(function () use ($case, $data, $user): SupplierExchangeClaim {
            $case = DamageCase::query()->whereKey($case->id)->lockForUpdate()->firstOrFail();
            $caseLines = $case->lines()->lockForUpdate()->get()->keyBy('id');

            $selected = [];
            $errors = [];

            foreach ($data['lines'] as $index => $input) {
                $quantity = (int) ($input['quantity'] ?? 0);

                if ($quantity <= 0) {
                    continue;
                }

                /** @var DamageCaseLine|null $line */
                $line = $caseLines->get((int) $input['damage_case_line_id']);

                if ($line === null) {
                    $errors["lines.{$index}.quantity"] = 'এই লাইনটি এই ক্ষতির কেসের নয়।';
                } elseif ($quantity > $line->unclaimedQuantity()) {
                    $errors["lines.{$index}.quantity"] = "দাবি করার মতো আর মাত্র ".Bangla::number($line->unclaimedQuantity())." পিস আছে।";
                } else {
                    $selected[] = [$line, $quantity];
                }
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            if ($selected === []) {
                throw ValidationException::withMessages(['lines' => 'দাবি করার জন্য অন্তত একটি ক্ষতিগ্রস্ত পরিমাণ লিখুন।']);
            }

            $claim = SupplierExchangeClaim::query()->create([
                'damage_case_id' => $case->id,
                'supplier_id' => $data['supplier_id'],
                'warehouse_id' => $case->warehouse_id,
                'status' => ClaimStatus::PendingReview,
                'supplier_message' => $data['supplier_message'] ?? $case->supplier_message,
                'created_by' => $user->id,
            ]);

            $claim->update(['claim_no' => sprintf('CLM-%s-%05d', $this->calendar->today()->format('ymd'), $claim->id)]);

            foreach ($selected as [$line, $quantity]) {
                $claim->lines()->create([
                    'damage_case_line_id' => $line->id,
                    'product_variant_id' => $line->product_variant_id,
                    'replacement_variant_id' => $line->product_variant_id,
                    'claimed_quantity' => $quantity,
                ]);

                $line->increment('claimed_quantity', $quantity);
            }

            $this->record($claim, 'submitted', null, ClaimStatus::PendingReview, $user, $data['supplier_message'] ?? null);
            $this->refreshDamageCase($case);

            $this->activity->log('claim.submitted', "বদলির দাবি {$claim->claim_no} জমা দেওয়া হয়েছে", $claim, user: $user);
            $this->notifyNext($claim, 'claims.review', 'ম্যানেজারের যাচাইয়ের অপেক্ষায়', $user);

            return $claim;
        });
    }

    public function review(SupplierExchangeClaim $claim, User $user, ?string $comment = null): SupplierExchangeClaim
    {
        return $this->transition($claim, [ClaimStatus::PendingReview], ClaimStatus::Reviewed, 'reviewed', $user, $comment, function (SupplierExchangeClaim $claim) use ($user): void {
            $claim->update(['reviewed_by' => $user->id, 'reviewed_at' => now()]);
            $this->notifyNext($claim, 'claims.supplier_agreement', 'সরবরাহকারীর সম্মতি লেখার জন্য প্রস্তুত', $user);
        });
    }

    /**
     * Records what the supplier agreed to: per-line quantities and (optionally different) replacement variants.
     *
     * @param  array{supplier_reference?: string|null, note?: string|null, lines: list<array{line_id: int, approved_quantity: int|null, replacement_variant_id?: int|null}>}  $data
     */
    public function recordSupplierAgreement(SupplierExchangeClaim $claim, array $data, User $user): SupplierExchangeClaim
    {
        return $this->transition($claim, [ClaimStatus::Reviewed], ClaimStatus::SupplierAgreed, 'supplier_agreed', $user, $data['note'] ?? null, function (SupplierExchangeClaim $claim) use ($data, $user): void {
            $lines = $claim->lines()->lockForUpdate()->get()->keyBy('id');
            $errors = [];

            foreach ($data['lines'] as $index => $input) {
                /** @var SupplierExchangeLine|null $line */
                $line = $lines->get((int) $input['line_id']);
                $quantity = (int) ($input['approved_quantity'] ?? 0);

                if ($line === null) {
                    $errors["lines.{$index}.line_id"] = 'দাবির এই লাইনটি পাওয়া যায়নি।';

                    continue;
                }

                if ($quantity < 0 || $quantity > $line->claimed_quantity) {
                    $errors["lines.{$index}.approved_quantity"] = "রাজি হওয়া পরিমাণ ০ থেকে ".Bangla::number($line->claimed_quantity)." এর মধ্যে হতে হবে।";

                    continue;
                }

                $replacementId = (int) ($input['replacement_variant_id'] ?? $line->product_variant_id);

                if ($replacementId !== $line->product_variant_id
                    && ! ProductVariant::query()->whereKey($replacementId)->where('status', RecordStatus::Active)->exists()) {
                    $errors["lines.{$index}.replacement_variant_id"] = 'বদলি ভ্যারিয়েন্টটি অবশ্যই চালু থাকতে হবে।';

                    continue;
                }

                $line->update(['approved_quantity' => $quantity, 'replacement_variant_id' => $replacementId]);
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            if ($claim->lines()->sum('approved_quantity') === 0) {
                throw ValidationException::withMessages(['lines' => 'সরবরাহকারীকে অন্তত এক পিসে রাজি হতে হবে। না হলে দাবিটি বাতিল করুন।']);
            }

            $claim->update([
                'supplier_reference' => $data['supplier_reference'] ?? null,
                'supplier_agreement_note' => $data['note'] ?? null,
                'supplier_agreed_by' => $user->id,
                'supplier_agreed_at' => now(),
            ]);

            $this->notifyNext($claim, 'claims.approve', 'চূড়ান্ত অনুমোদনের অপেক্ষায়', $user);
        });
    }

    public function approve(SupplierExchangeClaim $claim, User $user, ?string $comment = null): SupplierExchangeClaim
    {
        return $this->transition($claim, [ClaimStatus::SupplierAgreed], ClaimStatus::Approved, 'approved', $user, $comment, function (SupplierExchangeClaim $claim) use ($user): void {
            $this->releaseUnapprovedQuantities($claim);
            $claim->update(['approved_by' => $user->id, 'approved_at' => now()]);
            $this->notifyNext($claim, 'claims.dispatch', 'অনুমোদিত, এখন সরবরাহকারীর কাছে পাঠানো যাবে', $user);
        });
    }

    public function reject(SupplierExchangeClaim $claim, User $user, string $reason): SupplierExchangeClaim
    {
        return $this->transition($claim, ClaimStatus::awaitingApproval(), ClaimStatus::Rejected, 'rejected', $user, $reason, function (SupplierExchangeClaim $claim) use ($user, $reason): void {
            foreach ($claim->lines()->with('damageCaseLine')->get() as $line) {
                $line->damageCaseLine->decrement('claimed_quantity', $line->claimed_quantity);
            }

            $claim->update(['rejection_reason' => $reason, 'rejected_by' => $user->id, 'rejected_at' => now()]);
            $this->refreshDamageCase($claim->damageCase);
        });
    }

    /**
     * Physically sends approved damaged units to the supplier: Damaged → With Supplier.
     *
     * @param  array{dispatch_date?: string|null, notes?: string|null}  $data
     */
    public function dispatch(SupplierExchangeClaim $claim, array $data, User $user): SupplierExchangeClaim
    {
        return $this->transition($claim, [ClaimStatus::Approved], ClaimStatus::Dispatched, 'dispatched', $user, $data['notes'] ?? null, function (SupplierExchangeClaim $claim) use ($data, $user): void {
            $lines = $claim->lines()->where('approved_quantity', '>', 0)->get();

            $movement = $this->poster->post(new MovementData(
                type: MovementType::SupplierDispatch,
                warehouseId: $claim->warehouse_id,
                lines: $lines->map(fn (SupplierExchangeLine $line): MovementLine => new MovementLine(
                    variantId: $line->product_variant_id,
                    quantity: $line->approved_quantity,
                    from: StockCondition::Damaged,
                    to: StockCondition::WithSupplier,
                ))->all(),
                businessDate: $this->calendar->parse($data['dispatch_date'] ?? null),
                user: $user,
                supplierId: $claim->supplier_id,
                source: $claim,
                externalReference: $claim->claim_no,
                notes: $data['notes'] ?? null,
            ));

            foreach ($lines as $line) {
                $line->update(['dispatched_quantity' => $line->approved_quantity]);
            }

            $claim->update(['dispatch_movement_id' => $movement->id, 'dispatched_by' => $user->id, 'dispatched_at' => now()]);
        });
    }

    /**
     * Records replacements physically received: With Supplier (original) → Available (replacement).
     *
     * @param  array{received_date?: string|null, notes?: string|null, idempotency_key?: string|null, lines: list<array{line_id: int, quantity: int|null}>}  $data
     * @return array{0: SupplierExchangeReceipt, 1: bool}
     */
    public function receive(SupplierExchangeClaim $claim, array $data, User $user): array
    {
        $data['idempotency_key'] = $this->idempotency->scope($data['idempotency_key'] ?? null, $user);

        return $this->idempotency->run(
            $data['idempotency_key'] ?? null,
            fn (string $key): ?SupplierExchangeReceipt => SupplierExchangeReceipt::query()
                ->whereIn('stock_movement_id', StockMovement::query()->select('id')->where('idempotency_key', $key))
                ->first(),
            fn (): SupplierExchangeReceipt => DB::transaction(function () use ($claim, $data, $user): SupplierExchangeReceipt {
                $claim = SupplierExchangeClaim::query()->whereKey($claim->id)->lockForUpdate()->firstOrFail();

                if (! in_array($claim->status, [ClaimStatus::Dispatched, ClaimStatus::PartiallyReceived], true)) {
                    throw ValidationException::withMessages(['status' => 'শুধু পাঠানো হয়েছে এমন দাবির বদলি মাল গ্রহণ করা যায়।']);
                }

                $lines = $claim->lines()->lockForUpdate()->get()->keyBy('id');
                $received = [];
                $errors = [];

                foreach ($data['lines'] as $index => $input) {
                    $quantity = (int) ($input['quantity'] ?? 0);

                    if ($quantity <= 0) {
                        continue;
                    }

                    /** @var SupplierExchangeLine|null $line */
                    $line = $lines->get((int) $input['line_id']);

                    if ($line === null) {
                        $errors["lines.{$index}.quantity"] = 'দাবির এই লাইনটি পাওয়া যায়নি।';
                    } elseif ($quantity > $line->outstandingQuantity()) {
                        $errors["lines.{$index}.quantity"] = "সরবরাহকারীর কাছে আর মাত্র ".Bangla::number($line->outstandingQuantity())." পিস বাকি আছে।";
                    } else {
                        $received[] = [$line, $quantity];
                    }
                }

                if ($errors !== []) {
                    throw ValidationException::withMessages($errors);
                }

                if ($received === []) {
                    throw ValidationException::withMessages(['lines' => 'অন্তত একটি লাইনে পাওয়া পরিমাণ লিখুন।']);
                }

                $date = $this->calendar->parse($data['received_date'] ?? null);

                $receipt = $claim->receipts()->create([
                    'received_date' => $date->toDateString(),
                    'notes' => $data['notes'] ?? null,
                    'received_by' => $user->id,
                ]);

                $receipt->update(['receipt_no' => sprintf('SRC-%s-%05d', $date->format('ymd'), $receipt->id)]);

                $movementLines = [];

                foreach ($received as [$line, $quantity]) {
                    $receipt->lines()->create(['supplier_exchange_line_id' => $line->id, 'quantity' => $quantity]);
                    $line->increment('received_quantity', $quantity);

                    $replacementId = $line->replacement_variant_id ?? $line->product_variant_id;

                    if ($replacementId === $line->product_variant_id) {
                        $movementLines[] = new MovementLine($line->product_variant_id, $quantity, StockCondition::WithSupplier, StockCondition::Available, 'বদলি মাল পাওয়া গেছে');
                    } else {
                        $movementLines[] = new MovementLine($line->product_variant_id, $quantity, StockCondition::WithSupplier, null, 'সরবরাহকারীর সাথে বদল হয়েছে');
                        $movementLines[] = new MovementLine($replacementId, $quantity, null, StockCondition::Available, 'বদলি ভ্যারিয়েন্ট পাওয়া গেছে');
                    }
                }

                $movement = $this->poster->post(new MovementData(
                    type: MovementType::SupplierReceipt,
                    warehouseId: $claim->warehouse_id,
                    lines: $movementLines,
                    businessDate: $date,
                    user: $user,
                    supplierId: $claim->supplier_id,
                    source: $receipt,
                    externalReference: $claim->claim_no,
                    notes: $data['notes'] ?? null,
                    idempotencyKey: $data['idempotency_key'] ?? null,
                ));

                $receipt->update(['stock_movement_id' => $movement->id]);

                $outstanding = $claim->lines()->get()->sum(fn (SupplierExchangeLine $line): int => $line->outstandingQuantity());
                $to = $outstanding === 0 ? ClaimStatus::Closed : ClaimStatus::PartiallyReceived;
                $from = $claim->status;

                $claim->update(['status' => $to, ...($to === ClaimStatus::Closed ? ['closed_by' => $user->id, 'closed_at' => now()] : [])]);
                $this->record($claim, 'received', $from, $to, $user, "রসিদ {$receipt->receipt_no}: ".Bangla::number(collect($received)->sum(fn (array $row): int => $row[1])).' পিস');

                if ($to === ClaimStatus::Closed) {
                    $this->refreshDamageCase($claim->damageCase);
                }

                $this->activity->log('claim.received', "দাবি {$claim->claim_no} এর বদলি মাল গ্রহণ হয়েছে (রসিদ {$receipt->receipt_no})", $claim, user: $user);

                return $receipt;
            }),
        );
    }

    /**
     * Closes a claim whose remaining units the supplier will never return, writing them off.
     */
    public function closeShort(SupplierExchangeClaim $claim, User $user, string $reason): SupplierExchangeClaim
    {
        return $this->transition($claim, [ClaimStatus::Dispatched, ClaimStatus::PartiallyReceived], ClaimStatus::Closed, 'closed_short', $user, $reason, function (SupplierExchangeClaim $claim) use ($user, $reason): void {
            $lines = $claim->lines()->lockForUpdate()->get()->filter(fn (SupplierExchangeLine $line): bool => $line->outstandingQuantity() > 0);

            if ($lines->isNotEmpty()) {
                $this->poster->post(new MovementData(
                    type: MovementType::SupplierWriteOff,
                    warehouseId: $claim->warehouse_id,
                    lines: $lines->map(fn (SupplierExchangeLine $line): MovementLine => new MovementLine(
                        variantId: $line->product_variant_id,
                        quantity: $line->outstandingQuantity(),
                        from: StockCondition::WithSupplier,
                    ))->values()->all(),
                    businessDate: $this->calendar->today(),
                    user: $user,
                    supplierId: $claim->supplier_id,
                    source: $claim,
                    externalReference: $claim->claim_no,
                    notes: $reason,
                ));

                foreach ($lines as $line) {
                    $line->update(['written_off_quantity' => $line->written_off_quantity + $line->outstandingQuantity()]);
                }
            }

            $claim->update(['closed_by' => $user->id, 'closed_at' => now()]);
            $this->refreshDamageCase($claim->damageCase);
        });
    }

    /**
     * @param  list<ClaimStatus>  $from
     * @param  callable(SupplierExchangeClaim): void  $apply
     */
    private function transition(SupplierExchangeClaim $claim, array $from, ClaimStatus $to, string $action, User $user, ?string $comment, callable $apply): SupplierExchangeClaim
    {
        return DB::transaction(function () use ($claim, $from, $to, $action, $user, $comment, $apply): SupplierExchangeClaim {
            $claim = SupplierExchangeClaim::query()->whereKey($claim->id)->lockForUpdate()->firstOrFail();

            if (! in_array($claim->status, $from, true)) {
                throw ValidationException::withMessages([
                    'status' => "দাবিটি এখন \"{$claim->status->label()}\" অবস্থায় আছে, তাই এখন {$this->actionNoun($action)} করা যাবে না।",
                ]);
            }

            $previous = $claim->status;
            $claim->update(['status' => $to]);
            $apply($claim);

            $this->record($claim, $action, $previous, $to, $user, $comment);
            $this->activity->log("claim.{$action}", "দাবি {$claim->claim_no}: {$this->actionLabel($action)}", $claim, user: $user);

            return $claim->refresh();
        });
    }

    private function releaseUnapprovedQuantities(SupplierExchangeClaim $claim): void
    {
        foreach ($claim->lines()->with('damageCaseLine')->get() as $line) {
            $unapproved = $line->claimed_quantity - $line->approved_quantity;

            if ($unapproved > 0) {
                $line->damageCaseLine->decrement('claimed_quantity', $unapproved);
            }
        }

        $this->refreshDamageCase($claim->damageCase);
    }

    private function refreshDamageCase(DamageCase $case): void
    {
        $lines = $case->lines()->get();
        $hasOpenClaim = $case->claims()->whereNotIn('status', [ClaimStatus::Closed, ClaimStatus::Rejected])->exists();
        $unclaimed = $lines->sum(fn (DamageCaseLine $line): int => $line->unclaimedQuantity());

        $status = match (true) {
            $hasOpenClaim => DamageCaseStatus::InClaim,
            $unclaimed > 0 => DamageCaseStatus::Open,
            default => DamageCaseStatus::Resolved,
        };

        $case->update(['status' => $status]);
    }

    private function record(SupplierExchangeClaim $claim, string $action, ?ClaimStatus $from, ?ClaimStatus $to, User $user, ?string $comment): void
    {
        ApprovalRecord::query()->create([
            'approvable_type' => $claim->getMorphClass(),
            'approvable_id' => $claim->id,
            'action' => $action,
            'from_status' => $from?->value,
            'to_status' => $to?->value,
            'comment' => $comment,
            'user_id' => $user->id,
        ]);
    }

    private function notifyNext(SupplierExchangeClaim $claim, string $permission, string $message, User $actor): void
    {
        DB::afterCommit(fn () => $this->notifier->notifyPermission($permission, new WorkflowNotification(
            'claim',
            "দাবি {$claim->claim_no}: {$message}",
            "সরবরাহকারী {$claim->supplier()->value('name')} এর কাছে বদলির দাবি।",
            route('claims.show', $claim),
        ), $actor));
    }

    private function actionLabel(string $action): string
    {
        return match ($action) {
            'reviewed' => 'যাচাই করা হয়েছে',
            'supplier_agreed' => 'সরবরাহকারীর সম্মতি লেখা হয়েছে',
            'approved' => 'চূড়ান্ত অনুমোদন দেওয়া হয়েছে',
            'rejected' => 'বাতিল করা হয়েছে',
            'dispatched' => 'সরবরাহকারীর কাছে পাঠানো হয়েছে',
            'closed_short' => 'ঘাটতিসহ বন্ধ করা হয়েছে',
            default => $action,
        };
    }

    private function actionNoun(string $action): string
    {
        return match ($action) {
            'reviewed' => 'যাচাই',
            'supplier_agreed' => 'সরবরাহকারীর সম্মতি লেখা',
            'approved' => 'অনুমোদন',
            'rejected' => 'বাতিল',
            'dispatched' => 'পাঠানো',
            'closed_short' => 'ঘাটতিসহ বন্ধ',
            default => $action,
        };
    }
}
