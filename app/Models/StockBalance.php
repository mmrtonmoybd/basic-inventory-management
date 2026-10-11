<?php

namespace App\Models;

use App\Enums\StockCondition;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $available
 * @property int $inspection
 * @property int $damaged
 * @property int $with_supplier
 */
#[Fillable(['product_variant_id', 'warehouse_id', 'available', 'inspection', 'damaged', 'with_supplier'])]
class StockBalance extends Model
{
    protected function casts(): array
    {
        return [
            'available' => 'integer',
            'inspection' => 'integer',
            'damaged' => 'integer',
            'with_supplier' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function quantity(StockCondition $condition): int
    {
        return (int) $this->getAttribute($condition->value);
    }

    public function physicalOnHand(): int
    {
        return $this->available + $this->inspection + $this->damaged;
    }
}
