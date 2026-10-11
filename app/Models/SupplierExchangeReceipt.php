<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string|null $receipt_no
 * @property int $supplier_exchange_claim_id
 * @property int|null $stock_movement_id
 * @property CarbonImmutable $received_date
 * @property string|null $notes
 * @property int|null $received_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['receipt_no', 'supplier_exchange_claim_id', 'stock_movement_id', 'received_date', 'notes', 'received_by'])]
class SupplierExchangeReceipt extends Model
{
    protected function casts(): array
    {
        return [
            'received_date' => 'date',
        ];
    }

    /**
     * @return HasMany<SupplierExchangeReceiptLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(SupplierExchangeReceiptLine::class);
    }

    /**
     * @return BelongsTo<SupplierExchangeClaim, $this>
     */
    public function claim(): BelongsTo
    {
        return $this->belongsTo(SupplierExchangeClaim::class, 'supplier_exchange_claim_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * @return BelongsTo<StockMovement, $this>
     */
    public function movement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'stock_movement_id');
    }
}
