<?php

namespace App\Models;

use App\Enums\MovementType;
use App\Models\Concerns\Immutable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string|null $reference_no
 * @property MovementType $type
 * @property int $warehouse_id
 * @property int|null $supplier_id
 * @property string|null $source_type
 * @property int|null $source_id
 * @property CarbonImmutable $business_date
 * @property string|null $external_reference
 * @property string|null $notes
 * @property string|null $idempotency_key
 * @property int $total_quantity
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['reference_no', 'type', 'warehouse_id', 'supplier_id', 'source_type', 'source_id', 'business_date', 'external_reference', 'notes', 'idempotency_key', 'total_quantity', 'created_by'])]
class StockMovement extends Model
{
    use Immutable;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'business_date' => 'date',
            'total_quantity' => 'integer',
        ];
    }

    /**
     * @return HasMany<StockMovementLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(StockMovementLine::class);
    }

    /**
     * @return HasMany<StockLedgerEntry, $this>
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(StockLedgerEntry::class);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
