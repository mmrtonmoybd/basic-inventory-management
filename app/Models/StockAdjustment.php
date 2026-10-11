<?php

namespace App\Models;

use App\Enums\AdjustmentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 * @property string|null $adjustment_no
 * @property int $warehouse_id
 * @property CarbonImmutable $count_date
 * @property string $reason
 * @property string|null $notes
 * @property AdjustmentStatus $status
 * @property string|null $idempotency_key
 * @property string|null $decision_note
 * @property int|null $stock_movement_id
 * @property int|null $requested_by
 * @property int|null $decided_by
 * @property CarbonImmutable|null $decided_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['adjustment_no', 'idempotency_key', 'warehouse_id', 'count_date', 'reason', 'notes', 'status', 'decision_note', 'stock_movement_id', 'requested_by', 'decided_by', 'decided_at'])]
class StockAdjustment extends Model
{
    protected function casts(): array
    {
        return [
            'count_date' => 'date',
            'status' => AdjustmentStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<StockAdjustmentLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(StockAdjustmentLine::class);
    }

    /**
     * @return MorphMany<ApprovalRecord, $this>
     */
    public function approvalRecords(): MorphMany
    {
        return $this->morphMany(ApprovalRecord::class, 'approvable')->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
